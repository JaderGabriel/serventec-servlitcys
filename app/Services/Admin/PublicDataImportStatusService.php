<?php

namespace App\Services\Admin;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Inep\SaebHistoricoDatabase;
use App\Support\Admin\PublicDataImportCatalog;
use App\Support\InepMicrodadosCadastroEscolasPath;

/**
 * Métricas de cobertura local para o hub de importação de dados públicos.
 */
final class PublicDataImportStatusService
{
    public function __construct(
        private FundebOpenDataImportService $fundebImport,
    ) {}

    /**
     * @return array{
     *     reference_year: int,
     *     sync_years: list<int>,
     *     cities_total: int,
     *     cities_with_ibge: int,
     *     fundeb: array<string, mixed>,
     *     censo: array<string, mixed>,
     *     transfers: array<string, mixed>,
     *     saeb: array<string, mixed>,
     *     microdados: array<string, mixed>,
     *     sources: list<array<string, mixed>>
     * }
     */
    public function build(): array
    {
        $refYear = FundebOpenDataImportService::suggestedImportYear();
        $syncYears = FundebOpenDataImportService::configuredSyncYears();
        if ($syncYears === []) {
            $syncYears = [$refYear, $refYear - 1];
        }

        $cities = City::query()->forAnalytics()->orderBy('name')->get(['id', 'name', 'ibge_municipio']);
        $ibgeList = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge !== null) {
                $ibgeList[] = $ibge;
            }
        }
        $ibgeList = array_values(array_unique($ibgeList));

        $fundebDiag = $this->fundebImport->apiDiagnostics();
        $coverage = $this->fundebImport->localCoverageForYears($syncYears);
        $withAnyFundeb = count(array_filter($coverage, static fn (array $r): bool => ($r['years_with_reference'] ?? 0) > 0));
        $withFullFundeb = count(array_filter(
            $coverage,
            static fn (array $r): bool => ($r['years_with_reference'] ?? 0) >= count($syncYears) && count($syncYears) > 0,
        ));

        $censoRows = InepCensoMunicipioMatricula::query()
            ->when($ibgeList !== [], fn ($q) => $q->whereIn('ibge_municipio', $ibgeList))
            ->get(['ibge_municipio', 'ano']);
        $censoPairs = $censoRows->count();
        $censoMunicipios = $censoRows->pluck('ibge_municipio')->unique()->count();

        $transferRows = MunicipalTransferSnapshot::query()
            ->when($ibgeList !== [], fn ($q) => $q->whereIn('ibge_municipio', $ibgeList))
            ->get(['ibge_municipio', 'ano']);
        $transferPairs = $transferRows->count();
        $transferMunicipios = $transferRows->pluck('ibge_municipio')->unique()->count();

        $saeb = app(SaebHistoricoDatabase::class);
        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $mdPath = InepMicrodadosCadastroEscolasPath::resolve($rel);

        $sources = [];
        foreach (PublicDataImportCatalog::sources() as $source) {
            $sources[] = array_merge($source, [
                'status' => $this->statusForSource($source['id'], [
                    'cities_with_ibge' => count($ibgeList),
                    'with_any_fundeb' => $withAnyFundeb,
                    'censo_municipios' => $censoMunicipios,
                    'transfer_municipios' => $transferMunicipios,
                    'saeb_points' => $saeb->pointsCount(),
                    'microdados_ok' => $mdPath !== null && is_readable($mdPath),
                ]),
            ]);
        }

        return [
            'reference_year' => $refYear,
            'sync_years' => $syncYears,
            'cities_total' => $cities->count(),
            'cities_with_ibge' => count($ibgeList),
            'fundeb' => [
                'diagnostics' => $fundebDiag,
                'refs_total' => FundebMunicipioReference::query()->count(),
                'cities_with_any' => $withAnyFundeb,
                'cities_with_all_years' => $withFullFundeb,
            ],
            'censo' => [
                'pairs' => $censoPairs,
                'municipios' => $censoMunicipios,
                'latest_import' => InepCensoMunicipioMatricula::query()->max('imported_at'),
            ],
            'transfers' => [
                'pairs' => $transferPairs,
                'municipios' => $transferMunicipios,
                'latest_import' => MunicipalTransferSnapshot::query()->max('imported_at'),
            ],
            'saeb' => [
                'points' => $saeb->pointsCount(),
                'meta' => $saeb->meta(),
            ],
            'microdados' => [
                'configured_path' => $rel,
                'resolved_path' => $mdPath,
                'readable' => $mdPath !== null && is_readable($mdPath),
            ],
            'sources' => $sources,
        ];
    }

    /**
     * @param  array<string, int|bool>  $ctx
     * @return array{level: string, label: string, detail: string}
     */
    private function statusForSource(string $id, array $ctx): array
    {
        return match ($id) {
            'fundeb_fnde' => $this->fundebStatus($ctx),
            'censo_inep_matriculas' => $this->censoStatus($ctx),
            'repasses_tesouro' => $this->transfersStatus($ctx),
            'saeb_inep' => $this->saebStatus($ctx),
            'geo_inep' => [
                'level' => 'info',
                'label' => __('Ver sincronização geográfica'),
                'detail' => __('Use o mapa na consultoria após pipeline geo.'),
            ],
            'weekly_mass_sync' => [
                'level' => 'info',
                'label' => __('Orquestração'),
                'detail' => __('Executa todas as fases configuradas em ieducar.weekly_mass_sync.'),
            ],
            default => [
                'level' => 'neutral',
                'label' => __('—'),
                'detail' => '',
            ],
        };
    }

    /**
     * @param  array<string, int|bool>  $ctx
     * @return array{level: string, label: string, detail: string}
     */
    private function fundebStatus(array $ctx): array
    {
        $with = (int) ($ctx['with_any_fundeb'] ?? 0);
        $ibge = (int) ($ctx['cities_with_ibge'] ?? 0);
        if ($ibge === 0) {
            return [
                'level' => 'warn',
                'label' => __('Sem IBGE'),
                'detail' => __('Cadastre código IBGE de 7 dígitos em cada cidade.'),
            ];
        }
        if ($with === 0) {
            return [
                'level' => 'warn',
                'label' => __('Sem referências'),
                'detail' => __('Execute importação FUNDEB (CKAN ou CSV FNDE).'),
            ];
        }
        if ($with < $ibge) {
            return [
                'level' => 'partial',
                'label' => __('Parcial'),
                'detail' => __(':n de :total municípios com pelo menos um ano importado.', [
                    'n' => (string) $with,
                    'total' => (string) $ibge,
                ]),
            ];
        }

        return [
            'level' => 'ok',
            'label' => __('Cobertura municipal'),
            'detail' => __('Todos os municípios com IBGE têm referência FUNDEB em pelo menos um ano.'),
        ];
    }

    /**
     * @param  array<string, int|bool>  $ctx
     * @return array{level: string, label: string, detail: string}
     */
    private function censoStatus(array $ctx): array
    {
        if (! ($ctx['microdados_ok'] ?? false)) {
            return [
                'level' => 'warn',
                'label' => __('Microdados em falta'),
                'detail' => __('CSV INEP não encontrado — execute pipeline geo ou coloque ficheiro em storage/app/inep/.'),
            ];
        }
        $mun = (int) ($ctx['censo_municipios'] ?? 0);
        $ibge = (int) ($ctx['cities_with_ibge'] ?? 0);
        if ($mun === 0) {
            return [
                'level' => 'warn',
                'label' => __('Não indexado'),
                'detail' => __('Ficheiro existe mas inep_censo_municipio_matriculas está vazio — execute indexação.'),
            ];
        }
        if ($ibge > 0 && $mun < $ibge) {
            return [
                'level' => 'partial',
                'label' => __('Parcial'),
                'detail' => __(':n municípios indexados no Censo (cadastro: :total).', [
                    'n' => (string) $mun,
                    'total' => (string) $ibge,
                ]),
            ];
        }

        return [
            'level' => 'ok',
            'label' => __('Indexado'),
            'detail' => __(':n município(s) com matrículas Censo agregadas.', ['n' => (string) $mun]),
        ];
    }

    /**
     * @param  array<string, int|bool>  $ctx
     * @return array{level: string, label: string, detail: string}
     */
    private function transfersStatus(array $ctx): array
    {
        $mun = (int) ($ctx['transfer_municipios'] ?? 0);
        if ($mun === 0) {
            return [
                'level' => 'warn',
                'label' => __('Sem snapshots'),
                'detail' => __('Importe repasses Tesouro/Transparência por município e ano.'),
            ];
        }

        return [
            'level' => 'ok',
            'label' => __('Com dados'),
            'detail' => __(':n município(s) com repasses gravados.', ['n' => (string) $mun]),
        ];
    }

    /**
     * @param  array<string, int|bool>  $ctx
     * @return array{level: string, label: string, detail: string}
     */
    private function saebStatus(array $ctx): array
    {
        $points = (int) ($ctx['saeb_points'] ?? 0);
        if ($points === 0) {
            return [
                'level' => 'warn',
                'label' => __('Sem pontos SAEB'),
                'detail' => __('Use Sincronização pedagógica (microdados ou CSV).'),
            ];
        }

        return [
            'level' => 'ok',
            'label' => __(':n pontos', ['n' => (string) $points]),
            'detail' => __('Indicadores disponíveis na aba Desempenho.'),
        ];
    }
}
