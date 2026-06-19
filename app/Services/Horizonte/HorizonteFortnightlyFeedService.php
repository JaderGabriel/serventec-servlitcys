<?php

namespace App\Services\Horizonte;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Inep\InepCensoMunicipioMatriculasIndexer;
use App\Services\Inep\SaebPlanilhaInepImportService;
use App\Support\Brazil\IbgeMunicipalityCatalog;
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
        private readonly InepCensoMunicipioMatriculasIndexer $censoIndexer,
        private readonly SaebPlanilhaInepImportService $saebPlanilhas,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function run(array $options = []): array
    {
        if (! (bool) config('horizonte.enabled', true)) {
            return [
                'success' => false,
                'phases' => [],
                'message' => __('Horizonte desactivado (HORIZONTE_ENABLED=false).'),
            ];
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $phases = [];
        $allOk = true;

        $steps = [
            'fundeb' => ! ($options['skip_fundeb'] ?? false),
            'censo' => ! ($options['skip_censo'] ?? false),
            'saeb' => ! ($options['skip_saeb'] ?? false),
            'ibge' => ! ($options['skip_ibge'] ?? false),
            'verify' => ! ($options['skip_verify'] ?? false),
        ];

        if ($steps['fundeb']) {
            $result = $dryRun
                ? ['success' => true, 'message' => __('[dry-run] Sincronizar FUNDEB receita nacional (CSV FNDE).'), 'skipped' => true]
                : $this->syncFundebReceitaNacional($refYear);
            $phases[] = array_merge(['key' => 'fundeb_receita'], $result);
            $allOk = $allOk && ($result['success'] ?? false);
        }

        if ($steps['censo']) {
            $result = $dryRun
                ? ['success' => true, 'message' => __('[dry-run] Indexar matrículas Censo (microdados INEP).'), 'skipped' => true]
                : $this->indexCensoMatriculas();
            $phases[] = array_merge(['key' => 'censo_matriculas'], $result);
            $allOk = $allOk && ($result['success'] ?? false);
        }

        if ($steps['saeb']) {
            $result = $dryRun
                ? ['success' => true, 'message' => __('[dry-run] Importar planilhas SAEB INEP (todos os municípios).'), 'skipped' => true]
                : $this->importSaebPlanilhasNacional();
            $phases[] = array_merge(['key' => 'saeb_planilhas'], $result);
            $allOk = $allOk && ($result['success'] ?? false);
        }

        if ($steps['ibge']) {
            $result = $dryRun
                ? ['success' => true, 'message' => __('[dry-run] Aquecer catálogo IBGE (27 UFs).'), 'skipped' => true]
                : $this->warmIbgeCatalog();
            $phases[] = array_merge(['key' => 'ibge_catalog'], $result);
            $allOk = $allOk && ($result['success'] ?? false);
        }

        if ($steps['verify']) {
            $result = $dryRun
                ? ['success' => true, 'message' => __('[dry-run] Verificação de fontes oficiais (--no-notify).'), 'skipped' => true]
                : $this->runOfficialCheck();
            $phases[] = array_merge(['key' => 'official_check'], $result);
            $allOk = $allOk && ($result['success'] ?? false);
        }

        Log::info('horizonte.fortnightly_feed', [
            'success' => $allOk,
            'phases' => array_map(static fn (array $p): string => (string) ($p['key'] ?? '?'), $phases),
        ]);

        $result = [
            'success' => $allOk,
            'phases' => $phases,
            'message' => $allOk
                ? __('Abastecimento Horizonte concluído — cache do mapa invalida-se automaticamente pelo fingerprint dos dados.')
                : __('Abastecimento Horizonte concluído com falhas — reveja os logs e o hub Dados públicos.'),
        ];

        if (! $dryRun) {
            $this->storeFeedResult($result);
        }

        return $result;
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

                FundebMunicipioReference::query()->updateOrCreate(
                    ['ibge_municipio' => $ibgeNorm, 'ano' => $ano],
                    [
                        'city_id' => $cityIbgeMap[$ibgeNorm] ?? null,
                        'receita_total' => $totalReceita,
                        'complementacao_vaaf' => $row['complementacao_vaaf'] ?? null,
                        'complementacao_vaat' => $row['complementacao_vaat'] ?? null,
                        'complementacao_vaar' => $row['complementacao_vaar'] ?? null,
                        'fonte' => 'fnde_portaria_receita_horizonte',
                        'url_portaria' => $row['csv_url'] ?? null,
                        'imported_at' => now(),
                    ],
                );
                $imported++;
            }
        }

        if ($imported === 0 && $emptyYears !== []) {
            return [
                'success' => false,
                'message' => __('CSV receita FNDE indisponível para os anos: :anos.', [
                    'anos' => implode(', ', array_map('strval', $emptyYears)),
                ]),
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
                'success' => false,
                'message' => __('Configure horizonte.fortnightly_feed.saeb_years ou saeb.planilha_resultados_urls.'),
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
            'success' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'details' => $result['detalhes'] ?? null,
        ];
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
