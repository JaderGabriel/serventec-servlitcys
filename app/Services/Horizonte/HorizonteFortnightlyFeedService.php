<?php

namespace App\Services\Horizonte;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Inep\InepCensoMunicipioMatriculasIndexer;
use App\Services\Inep\SaebPlanilhaInepImportService;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Abastecimento quinzenal de dados públicos para o mapa Horizonte (cobertura nacional).
 */
final class HorizonteFortnightlyFeedService
{
    public function __construct(
        private readonly FundebFndeReceitaCsvService $fundebReceita,
        private readonly FundebMunicipioReferenceRepository $fundebReferences,
        private readonly InepCensoMunicipioMatriculasIndexer $censoIndexer,
        private readonly SaebPlanilhaInepImportService $saebPlanilhas,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
        private readonly HorizonteMunicipalSgeRegistryService $sgeRegistry,
        private readonly HorizonteFortnightlyFeedNotifier $notifier,
    ) {}

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, pipeline?: array<string, mixed>|null, idle?: bool, phase?: array<string, mixed>|null}
     */
    public function runStaged(array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        $skipOptions = $this->normalizedSkipOptions($options);
        $reset = (bool) ($options['reset'] ?? false);
        $continue = (bool) ($options['continue'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        if ($reset) {
            HorizonteFortnightlyFeedPipeline::forget();
            $state = HorizonteFortnightlyFeedPipeline::start($skipOptions);
        } elseif ($continue) {
            $state = HorizonteFortnightlyFeedPipeline::get();
            if ($state === null || ($state['status'] ?? '') !== 'running') {
                return [
                    'success' => true,
                    'phases' => [],
                    'message' => __('Nenhum abastecimento Horizonte em curso.'),
                    'idle' => true,
                    'pipeline' => $state,
                ];
            }
        } else {
            $state = HorizonteFortnightlyFeedPipeline::get();
            if ($state === null || ! in_array($state['status'] ?? '', ['running', 'partial'], true)) {
                $state = HorizonteFortnightlyFeedPipeline::start($skipOptions);
            }
        }

        if (($state['status'] ?? '') === 'completed' || ($state['status'] ?? '') === 'partial') {
            return $this->pipelineResponse($state);
        }

        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $index = (int) ($state['current_index'] ?? 0);
        if ($index >= count($queue)) {
            return $this->pipelineResponse($state);
        }

        $phaseKey = (string) $queue[$index];
        $state = HorizonteFortnightlyFeedPipeline::markPhaseRunning($state, $phaseKey);

        $phaseResult = $dryRun
            ? $this->dryRunPhase($phaseKey)
            : $this->runPhase($phaseKey);

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, $phaseResult);

        if (! $dryRun) {
            $this->notifier->phaseFinished(
                (string) ($state['run_id'] ?? ''),
                $phaseResult,
                $index + 1,
                count($queue),
            );
            if (! in_array($state['status'] ?? '', ['running'], true)) {
                $this->notifier->cycleFinished($state);
            }
        }

        Log::info('horizonte.fortnightly_feed.staged', [
            'run_id' => $state['run_id'] ?? null,
            'phase' => $phaseKey,
            'status' => $state['status'] ?? null,
            'success' => $phaseResult['success'] ?? false,
        ]);

        return $this->pipelineResponse($state, $phaseResult);
    }

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function runSinglePhase(string $phaseKey, array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $phaseResult = $dryRun ? $this->dryRunPhase($phaseKey) : $this->runPhase($phaseKey);

        if (! $dryRun) {
            $this->notifier->phaseFinished('manual-'.$phaseKey, $phaseResult, 1, 1);
        }

        return [
            'success' => (bool) ($phaseResult['success'] ?? false),
            'phases' => [$phaseResult],
            'message' => (string) ($phaseResult['message'] ?? ''),
            'phase' => $phaseResult,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runPhase(string $phaseKey): array
    {
        $this->applyResourceLimits();
        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);

        $result = match ($phaseKey) {
            'fundeb_receita' => $this->syncFundebReceitaNacional($refYear),
            'censo_matriculas' => $this->indexCensoMatriculas(),
            'saeb_planilhas' => $this->importSaebPlanilhasNacional(),
            'ibge_catalog' => $this->warmIbgeCatalog(),
            'sge_registry' => $this->syncSgeRegistry(),
            'official_check' => $this->runOfficialCheck(),
            default => [
                'success' => false,
                'message' => __('Fase Horizonte desconhecida: :key', ['key' => $phaseKey]),
            ],
        };

        return array_merge(['key' => $phaseKey], $result);
    }

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function run(array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $phases = [];

        foreach (HorizonteFortnightlyFeedPhaseCatalog::queueFromOptions($this->normalizedSkipOptions($options)) as $phaseKey) {
            $result = $dryRun ? $this->dryRunPhase($phaseKey) : $this->runPhase($phaseKey);
            $phases[] = $result;
            if (! $dryRun) {
                gc_collect_cycles();
            }
        }

        Log::info('horizonte.fortnightly_feed', [
            'success' => collect($phases)->every(static fn (array $p): bool => (bool) ($p['success'] ?? false)),
            'phases' => array_map(static fn (array $p): string => (string) ($p['key'] ?? '?'), $phases),
        ]);

        $hasWarnings = collect($phases)->contains(
            static fn (array $p): bool => (bool) ($p['skipped'] ?? false) || ! ($p['success'] ?? false),
        );
        $usable = $this->feedHasUsableOutput($phases);

        $result = [
            'success' => $usable,
            'phases' => $phases,
            'message' => $usable
                ? ($hasWarnings
                    ? __('Abastecimento Horizonte concluído com avisos — mapa usa os dados disponíveis (fases em falha não bloqueiam).')
                    : __('Abastecimento Horizonte concluído — cache do mapa invalida-se automaticamente pelo fingerprint dos dados.'))
                : __('Abastecimento Horizonte concluído sem dados novos — reveja os logs e o hub Dados públicos.'),
            'staged' => false,
        ];

        if (! $dryRun) {
            $runId = 'monolithic-'.now()->format('Ymd-His');
            $result['run_id'] = $runId;
            $this->storeFeedResult($result);
            foreach ($phases as $i => $phase) {
                $this->notifier->phaseFinished($runId, $phase, $i + 1, count($phases));
            }
            $this->notifier->cycleFinished([
                'run_id' => $runId,
                'success' => $usable,
                'message' => $result['message'],
                'phase_queue' => array_column($phases, 'key'),
            ]);
        }

        return $result;
    }

    /**
     * @return array{success: bool, message: string, phases: list<array<string, mixed>>}|null
     */
    private function disabledPayload(): ?array
    {
        if (! (bool) config('horizonte.enabled', true)) {
            return [
                'success' => false,
                'phases' => [],
                'message' => __('Horizonte desactivado (HORIZONTE_ENABLED=false).'),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $lastPhase
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, pipeline?: array<string, mixed>, phase?: array<string, mixed>|null}
     */
    private function pipelineResponse(array $state, ?array $lastPhase = null): array
    {
        $phaseResults = [];
        foreach (is_array($state['phases'] ?? null) ? $state['phases'] : [] as $row) {
            if (is_array($row['result'] ?? null)) {
                $phaseResults[] = $row['result'];
            }
        }

        $running = ($state['status'] ?? '') === 'running';
        $message = $running
            ? __('Fase :label concluída — :done/:total. Próxima etapa pelo agendador.', [
                'label' => HorizonteFortnightlyFeedPhaseCatalog::label((string) ($lastPhase['key'] ?? '')),
                'done' => (string) count($phaseResults),
                'total' => (string) count(is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : []),
            ])
            : (string) ($state['message'] ?? __('Pipeline Horizonte actualizado.'));

        return [
            'success' => $running ? (bool) ($lastPhase['success'] ?? true) : (bool) ($state['success'] ?? false),
            'phases' => $phaseResults,
            'message' => $message,
            'pipeline' => $state,
            'phase' => $lastPhase,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dryRunPhase(string $phaseKey): array
    {
        $label = HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey);

        return [
            'key' => $phaseKey,
            'success' => true,
            'skipped' => true,
            'message' => __('[dry-run] :label', ['label' => $label]),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, bool>
     */
    private function normalizedSkipOptions(array $options): array
    {
        return [
            'skip_fundeb' => (bool) ($options['skip_fundeb'] ?? false),
            'skip_censo' => (bool) ($options['skip_censo'] ?? false),
            'skip_saeb' => (bool) ($options['skip_saeb'] ?? false),
            'skip_ibge' => (bool) ($options['skip_ibge'] ?? false),
            'skip_sge' => (bool) ($options['skip_sge'] ?? false),
            'skip_verify' => (bool) ($options['skip_verify'] ?? false),
        ];
    }

    private function applyResourceLimits(): void
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
        @set_time_limit(max(60, (int) config('horizonte.fortnightly_feed.time_limit', 900)));
    }

    /**
     * @param  array{success: bool, phases: list<array<string, mixed>>, message: string}  $result
     */
    private function storeFeedResult(array $result): void
    {
        \App\Support\Horizonte\HorizonteFortnightlyFeedCache::put($result);
    }

    /**
     * @return array{success: bool, message: string, imported?: int, years?: list<int>}
     */
    private function syncFundebReceitaNacional(int $refYear): array
    {
        $yearsConfig = config('horizonte.fortnightly_feed.fundeb_years');
        $years = is_array($yearsConfig) && $yearsConfig !== []
            ? array_values(array_unique(array_filter(array_map('intval', $yearsConfig))))
            : [$refYear, $refYear - 1];

        $cityIbgeMap = [];
        foreach (City::query()->whereNotNull('ibge_municipio')->get(['id', 'ibge_municipio']) as $city) {
            $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge((string) $city->ibge_municipio);
            if ($ibgeNorm !== null) {
                $cityIbgeMap[$ibgeNorm] = (int) $city->id;
            }
        }

        $imported = 0;
        $emptyYears = [];

        foreach ($years as $ano) {
            $index = $this->fundebReceita->loadYearIndex($ano);
            if ($index === []) {
                $emptyYears[] = $ano;
                continue;
            }

            foreach ($index as $ibge => $row) {
                $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge((string) $ibge);
                if ($ibgeNorm === null) {
                    continue;
                }

                $totalReceita = (float) ($row['total_receita'] ?? 0);
                if ($totalReceita <= 0) {
                    continue;
                }

                $this->fundebReferences->upsertHorizontePortariaReceita(
                    $ibgeNorm,
                    $ano,
                    $cityIbgeMap[$ibgeNorm] ?? null,
                    [
                        'receita_total' => $totalReceita,
                        'complementacao_vaaf' => $row['complementacao_vaaf'] ?? null,
                        'complementacao_vaat' => $row['complementacao_vaat'] ?? null,
                        'complementacao_vaar' => $row['complementacao_vaar'] ?? null,
                        'fonte' => 'fnde_portaria_receita_horizonte',
                        'url_portaria' => $row['csv_url'] ?? null,
                    ],
                );
                $imported++;
            }
        }

        if ($imported === 0 && $emptyYears !== []) {
            $allowEmpty = filter_var(
                config('horizonte.fortnightly_feed.fundeb_allow_empty', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('CSV receita FNDE indisponível para os anos: :anos.', [
                'anos' => implode(', ', array_map('strval', $emptyYears)),
            ]);

            if ($allowEmpty) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => $msg,
                    'imported' => 0,
                    'years' => $years,
                ];
            }

            return [
                'success' => false,
                'message' => $msg,
                'imported' => 0,
                'years' => $years,
            ];
        }

        return [
            'success' => true,
            'message' => __('FUNDEB: :n registo(s) municipal(is) actualizados (receita portaria FNDE).', [
                'n' => (string) $imported,
            ]),
            'imported' => $imported,
            'years' => $years,
        ];
    }

    /**
     * @return array{success: bool, message: string, indexed?: int}
     */
    private function indexCensoMatriculas(): array
    {
        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        if ($path === null || ! is_readable($path)) {
            $allowSkip = filter_var(
                config('horizonte.fortnightly_feed.censo_skip_if_missing', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('Microdados INEP indisponíveis — matrículas Censo não indexadas. Execute o pipeline geo ou coloque o CSV em storage/app/inep/.');
            if ($allowSkip) {
                return ['success' => true, 'message' => $msg, 'indexed' => 0, 'skipped' => true];
            }

            return ['success' => false, 'message' => $msg];
        }

        $indexed = $this->censoIndexer->indexFromMicrodadosCsv($path);

        return [
            'success' => $indexed > 0 || filter_var(
                config('horizonte.fortnightly_feed.censo_allow_empty', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'message' => $indexed > 0
                ? __('Censo: :n combinações município/ano indexadas.', ['n' => (string) $indexed])
                : __('Censo: nenhuma matrícula agregada no CSV.'),
            'indexed' => $indexed,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function importSaebPlanilhasNacional(): array
    {
        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'message' => __('SAEB desactivado — fase ignorada.'),
                'skipped' => true,
            ];
        }

        $years = $this->resolveSaebYears();

        if ($years === []) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SAEB: anos não configurados — configure horizonte.fortnightly_feed.saeb_years ou saeb.planilha_resultados_urls.'),
            ];
        }

        $result = $this->saebPlanilhas->importYearsNational(
            $years,
            download: true,
            merge: true,
            resolveInep: false,
            keepCache: true,
        );

        return [
            'success' => (bool) ($result['ok'] ?? false) || filter_var(
                config('horizonte.fortnightly_feed.censo_skip_if_missing', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'message' => (string) ($result['message'] ?? ''),
            'details' => $result['detalhes'] ?? null,
            'skipped' => ! (bool) ($result['ok'] ?? false),
        ];
    }

    /**
     * @return array{success: bool, message: string, matched?: int, skipped?: bool}
     */
    private function syncSgeRegistry(): array
    {
        try {
            return $this->sgeRegistry->sync();
        } catch (\Throwable $e) {
            Log::warning('horizonte.sge_registry_failed', ['message' => $e->getMessage()]);

            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SGE: registo externo indisponível — mapa continua com catálogo ServLITCYS e dados públicos.'),
                'matched' => 0,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $phases
     */
    private function feedHasUsableOutput(array $phases): bool
    {
        foreach ($phases as $phase) {
            if (! ($phase['success'] ?? false)) {
                continue;
            }
            if (($phase['imported'] ?? 0) > 0
                || ($phase['indexed'] ?? 0) > 0
                || ($phase['matched'] ?? 0) > 0
                || ($phase['ufs'] ?? 0) > 0) {
                return true;
            }
            if (($phase['skipped'] ?? false) && ($phase['key'] ?? '') !== 'sge_registry') {
                return true;
            }
        }

        return collect($phases)->contains(fn (array $p): bool => (bool) ($p['success'] ?? false));
    }

    /**
     * @return array{success: bool, message: string, ufs?: int}
     */
    private function warmIbgeCatalog(): array
    {
        $ufs = IbgeMunicipalityCatalog::brazilianUfs();
        $this->ibgeCatalog->warmForUfs($ufs);

        return [
            'success' => true,
            'message' => __('Catálogo IBGE aquecido para :n UFs (coordenadas para prospectos).', [
                'n' => (string) count($ufs),
            ]),
            'ufs' => count($ufs),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function runOfficialCheck(): array
    {
        if (! (bool) config('public_data_availability.enabled', true)) {
            return [
                'success' => true,
                'message' => __('Verificação oficial desactivada — fase ignorada.'),
                'skipped' => true,
            ];
        }

        $exitCode = Artisan::call('public-data:check-official', ['--no-notify' => true]);
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? __('Verificação de fontes oficiais concluída (sem notificação).')
                : __('Verificação de fontes oficiais falhou (código :code).', ['code' => (string) $exitCode]),
            'output' => $output !== '' ? $output : null,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveSaebYears(): array
    {
        $raw = config('horizonte.fortnightly_feed.saeb_years');
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }
        if (is_string($raw) && trim($raw) !== '') {
            return SaebPlanilhaInepImportService::parseYearsOption($raw);
        }

        return SaebPlanilhaInepImportService::parseYearsOption(null);
    }
}
