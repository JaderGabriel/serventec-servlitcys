<?php

namespace App\Services\AdminSync;

use App\Models\AdminSyncTask;
use App\Models\City;
use App\Services\Fundeb\FundebImportMode;
use App\Services\Fundeb\FundebImportProgress;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Funding\MunicipalTransferImportService;
use App\Services\Inep\InepCensoMunicipioMatriculasIndexer;
use App\Services\Inep\SaebOfficialMunicipalImportService;
use App\Support\AdminSync\WeeklyMassSyncCheckpoint;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Support\Facades\Artisan;

/**
 * Sincronização massiva semanal: geo (i-Educar + INEP), FUNDEB (VAAF/VAAT/VAAR), repasses, Censo e SAEB.
 * Suporta checkpoint por fase e por município (geo e repasses).
 */
final class WeeklyMassSyncOrchestrator
{
    public function __construct(
        private FundebOpenDataImportService $fundebImport,
        private MunicipalTransferImportService $transferImport,
        private InepCensoMunicipioMatriculasIndexer $censoMatriculasIndexer,
        private SaebOfficialMunicipalImportService $saebOfficial,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $phpLimit = max(3600, (int) config('ieducar.weekly_mass_sync.php_time_limit', 14400));
        @set_time_limit($phpLimit);

        $cityIds = AdminSyncTaskCitiesResolver::resolveCityIdsForTask($task);
        if ($cityIds === []) {
            return [
                'success' => false,
                'message' => __('Nenhum município com analytics ativo e base i-Educar configurada.'),
                'output' => (string) ($task->output_log ?? ''),
            ];
        }

        $checkpoint = WeeklyMassSyncCheckpoint::fromTask($task);
        if ($checkpoint->completedPhases !== [] || $checkpoint->geoCompletedCityIds !== []) {
            $progress->explain(__('Retomada: fases concluídas: :f', [
                'f' => $checkpoint->completedPhases !== []
                    ? implode(', ', $checkpoint->completedPhases)
                    : __('(nenhuma)'),
            ]));
        }

        $phaseStats = [];
        $phases = $this->enabledPhases();

        foreach ($phases as $phaseKey => $phaseLabel) {
            if ($checkpoint->isPhaseComplete($phaseKey)) {
                $progress->detail(__('Fase ignorada (já concluída): :p', ['p' => $phaseLabel]));
                continue;
            }

            $progress->step(count($checkpoint->completedPhases) + 1, count($phases), __('Fase: :label', ['label' => $phaseLabel]));

            $phaseResult = match ($phaseKey) {
                'geo_pipeline' => $this->runGeoPipelinePhase($task, $progress, $cityIds, $checkpoint),
                'fundeb_sync' => $this->runFundebPhase($task, $progress, $cityIds),
                'funding_transfers' => $this->runFundingTransfersPhase($task, $progress, $cityIds, $checkpoint),
                'funding_censo_matriculas' => $this->runFundingCensoPhase($task, $progress),
                'pedagogical_saeb' => $this->runPedagogicalPhase($task, $progress),
                'censo_geo_agg' => $this->runCensoGeoAggPhase($task, $progress),
                default => ['success' => false, 'message' => __('Fase desconhecida: :k', ['k' => $phaseKey])],
            };

            $phaseStats[$phaseKey] = $phaseResult;

            if (! (bool) ($phaseResult['success'] ?? false)) {
                $checkpoint->persist($task);

                return [
                    'success' => false,
                    'message' => (string) ($phaseResult['message'] ?? __('Falha na fase :p.', ['p' => $phaseLabel])),
                    'failed_phase' => $phaseKey,
                    'phases' => $phaseStats,
                    'checkpoint' => $checkpoint->toArray(),
                    'output' => (string) ($task->output_log ?? ''),
                ];
            }

            $checkpoint->markPhaseComplete($phaseKey);
            $checkpoint->persist($task);
            $progress->success(__('Fase concluída: :p', ['p' => $phaseLabel]));
        }

        $checkpoint->clear($task);

        return [
            'success' => true,
            'message' => __('Sincronização massiva semanal concluída para :n município(s).', ['n' => (string) count($cityIds)]),
            'cities_count' => count($cityIds),
            'phases' => $phaseStats,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @return array<string, string> chave => rótulo
     */
    private function enabledPhases(): array
    {
        $all = [
            'geo_pipeline' => __('Geográfico — pipeline i-Educar + INEP'),
            'fundeb_sync' => __('FUNDEB — VAAF, VAAT, VAAR (multi-ano)'),
            'funding_transfers' => __('Repasses — Tesouro / Transparência'),
            'funding_censo_matriculas' => __('Censo — matrículas por município (microdados)'),
            'pedagogical_saeb' => __('Pedagógico — SAEB (microdados + INEP→escola)'),
            'censo_geo_agg' => __('Censo — agregados geográficos'),
        ];

        $enabled = config('ieducar.weekly_mass_sync.phases');
        if (! is_array($enabled) || $enabled === []) {
            return $all;
        }

        $out = [];
        foreach ($all as $key => $label) {
            if (filter_var($enabled[$key] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $out[$key] = $label;
            }
        }

        return $out !== [] ? $out : $all;
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<string, mixed>
     */
    private function runGeoPipelinePhase(
        AdminSyncTask $task,
        AdminSyncTaskProgress $progress,
        array $cityIds,
        WeeklyMassSyncCheckpoint $checkpoint,
    ): array {
        $command = 'app:sync-school-unit-geos-pipeline';
        $threshold = (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        $baseArgs = [
            '--skip-ieducar' => '0',
            '--ieducar-only-missing' => config('ieducar.weekly_mass_sync.geo_ieducar_only_missing', true) ? '1' : '0',
            '--official-only-missing' => config('ieducar.weekly_mass_sync.geo_official_only_missing', true) ? '1' : '0',
            '--threshold' => (string) max(0, $threshold),
            '--dry-run' => '0',
            '--skip-microdados-if-missing' => '0',
            '--microdados-also-map-coords' => '1',
            '--microdados-fetch' => config('ieducar.weekly_mass_sync.geo_microdados_fetch', true) ? '1' : '0',
        ];

        $completed = $checkpoint->geoCompletedCityIds;
        $pending = array_values(array_filter(
            $cityIds,
            static fn (int $id): bool => ! in_array($id, $completed, true),
        ));

        if ($completed !== []) {
            $progress->explain(__('Geo: :done/:total municípios já processados no checkpoint.', [
                'done' => (string) count($completed),
                'total' => (string) count($cityIds),
            ]));
        }

        foreach ($pending as $index => $cityId) {
            $city = City::query()->find($cityId);
            $cityLabel = $city !== null ? $city->name : '#'.$cityId;

            $progress->detail(__('Geo pipeline — :city (:i/:n)…', [
                'city' => $cityLabel,
                'i' => (string) ($index + 1),
                'n' => (string) count($pending),
            ]));

            $args = array_merge($baseArgs, ['--city' => (string) $cityId]);
            $exitCode = Artisan::call($command, $args);
            $output = Artisan::output();

            if (trim($output) !== '') {
                $progress->appendBlock($output);
            }

            if ($exitCode !== 0) {
                $checkpoint->geoCompletedCityIds = $completed;
                $checkpoint->persist($task);

                return [
                    'success' => false,
                    'message' => __('Pipeline geo falhou em :city (código :code).', [
                        'city' => $cityLabel,
                        'code' => (string) $exitCode,
                    ]),
                    'failed_city_id' => $cityId,
                    'exit_code' => $exitCode,
                ];
            }

            $completed[] = $cityId;
            $checkpoint->geoCompletedCityIds = $completed;
            $checkpoint->persist($task);
        }

        return [
            'success' => true,
            'message' => __('Pipeline geo concluído para :n município(s).', ['n' => (string) count($cityIds)]),
            'cities_processed' => count($cityIds),
        ];
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<string, mixed>
     */
    private function runFundebPhase(AdminSyncTask $task, AdminSyncTaskProgress $progress, array $cityIds): array
    {
        $payload = is_array($task->payload) ? $task->payload : [];
        $years = $payload['fundeb_years'] ?? null;
        if (! is_array($years) || $years === []) {
            $years = array_values(array_unique(array_merge(
                FundebOpenDataImportService::yearsForPlanningProfile(),
                $this->fundebImport->resolveSyncYears(
                    (int) ($payload['ano_from'] ?? 0),
                    (int) ($payload['ano_to'] ?? 0),
                    true,
                    true,
                ),
            )));
            sort($years);
        }

        $progress->explain(__('FUNDEB — anos: :anos · municípios: :n', [
            'anos' => implode(', ', array_map('strval', $years)),
            'n' => (string) count($cityIds),
        ]));

        $fundebLog = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulkForYears(
            array_map('intval', $years),
            (bool) ($payload['use_nearest_year'] ?? false),
            $cityIds,
            $fundebLog,
            FundebImportMode::normalize($payload['import_mode'] ?? FundebImportMode::UPDATE),
        );

        $success = (bool) ($result['success'] ?? false);
        $failed = is_array($result['failed'] ?? null) ? count($result['failed']) : 0;

        return [
            'success' => $success,
            'message' => (string) ($result['message'] ?? ''),
            'failed_count' => $failed,
            'bulk' => $result,
        ];
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<string, mixed>
     */
    private function runFundingTransfersPhase(
        AdminSyncTask $task,
        AdminSyncTaskProgress $progress,
        array $cityIds,
        WeeklyMassSyncCheckpoint $checkpoint,
    ): array {
        $years = $this->transferYears();
        $completed = $checkpoint->transfersCompletedCityIds;
        $pending = array_values(array_filter(
            $cityIds,
            static fn (int $id): bool => ! in_array($id, $completed, true),
        ));

        $ok = 0;
        $fail = 0;

        foreach ($pending as $cityId) {
            $city = City::query()->find($cityId);
            if ($city === null) {
                continue;
            }

            foreach ($years as $ano) {
                $progress->detail(__('Repasses — :city, ano :ano…', [
                    'city' => $city->name,
                    'ano' => (string) $ano,
                ]));

                $result = $this->transferImport->importForCityYear($city, $ano);
                if ((bool) ($result['success'] ?? false)) {
                    $ok++;
                } else {
                    $fail++;
                    $progress->warn((string) ($result['message'] ?? __('Falha ao importar repasses.')));
                }
            }

            $completed[] = $cityId;
            $checkpoint->transfersCompletedCityIds = $completed;
            $checkpoint->persist($task);
        }

        $allowPartial = filter_var(
            config('ieducar.weekly_mass_sync.transfers_allow_partial_failures', true),
            FILTER_VALIDATE_BOOLEAN,
        );

        return [
            'success' => $allowPartial || $fail === 0,
            'message' => __('Repasses: :ok sucesso(s), :fail falha(s) em :n município(s).', [
                'ok' => (string) $ok,
                'fail' => (string) $fail,
                'n' => (string) count($cityIds),
            ]),
            'ok' => $ok,
            'fail' => $fail,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundingCensoPhase(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        if ($path === null || ! is_readable($path)) {
            $skip = filter_var(
                config('ieducar.weekly_mass_sync.censo_matriculas_skip_if_missing', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('CSV microdados INEP indisponível — matrículas Censo não indexadas (execute após o pipeline geo).');
            if ($skip) {
                $progress->warn($msg);

                return ['success' => true, 'message' => $msg, 'skipped' => true];
            }

            return ['success' => false, 'message' => $msg];
        }

        $indexed = $this->censoMatriculasIndexer->indexFromMicrodadosCsv($path);

        return [
            'success' => $indexed > 0 || filter_var(
                config('ieducar.weekly_mass_sync.censo_matriculas_allow_empty', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'message' => $indexed > 0
                ? __(':n combinações município/ano indexadas.', ['n' => (string) $indexed])
                : __('Nenhuma matrícula municipal agregada no CSV.'),
            'indexed' => $indexed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalPhase(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = is_array($task->payload) ? $task->payload : [];
        $year = isset($payload['saeb_year']) && is_numeric($payload['saeb_year'])
            ? (int) $payload['saeb_year']
            : max(2000, (int) date('Y') - 1);

        $options = [
            'year' => $year,
            'auto_microdados' => (bool) ($payload['saeb_auto_microdados'] ?? true),
            'resolve_inep' => (bool) ($payload['saeb_resolve_inep'] ?? true),
        ];

        $progress->explain(__('SAEB — ano :year, microdados automáticos e INEP→cod_escola.', [
            'year' => (string) $year,
        ]));

        $result = $this->saebOfficial->importFromOfficialTemplate(null, $options);

        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'details' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runCensoGeoAggPhase(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $exitCode = Artisan::call('app:index-inep-censo-geo-agg');
        $output = Artisan::output();
        if (trim($output) !== '') {
            $progress->appendBlock($output);
        }

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? __('Agregados geográficos Censo indexados.')
                : __('Falha ao indexar agregados Censo (código :code).', ['code' => (string) $exitCode]),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @return list<int>
     */
    private function transferYears(): array
    {
        $span = max(1, min(10, (int) config('ieducar.weekly_mass_sync.transfer_year_span', 3)));
        $current = (int) date('Y');
        $years = [];
        for ($i = 0; $i < $span; $i++) {
            $years[] = $current - $i;
        }

        return $years;
    }

    private function fundebProgress(AdminSyncTask $task): FundebImportProgress
    {
        return new FundebImportProgress(function (string $level, string $message) use ($task): void {
            $line = '['.now()->format('H:i:s').'] ['.$level.'] '.$message;
            $existing = (string) ($task->output_log ?? '');
            $task->output_log = $existing === '' ? $line : $existing."\n".$line;
            $task->saveQuietly();
        });
    }
}
