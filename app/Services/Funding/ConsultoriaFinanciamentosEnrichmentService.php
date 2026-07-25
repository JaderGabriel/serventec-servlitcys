<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Support\Collection;

/**
 * Enriquece Finanças → Financiamentos (além do FUNDEB) para municípios com consultoria activa.
 */
final class ConsultoriaFinanciamentosEnrichmentService
{
    public function __construct(
        private MunicipalTransferImportService $transfers,
        private MunicipalFundingPublicSnapshotService $publicSnapshot,
    ) {}

    /**
     * Cidades activas com i-Educar configurado e IBGE (tier consultoria_active no Horizonte).
     *
     * @param  list<int>|null  $cityIds
     * @return Collection<int, City>
     */
    public function consultoriaCities(?array $cityIds = null): Collection
    {
        $query = City::query()->active()->orderBy('name');
        if ($cityIds !== null) {
            $query->whereIn('id', $cityIds);
        }

        return $query->get()->filter(static function (City $city): bool {
            if (! $city->hasDataSetup()) {
                return false;
            }

            return FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) !== null;
        })->values();
    }

    /**
     * @param  list<int>|null  $cityIds
     * @return array{
     *   year: int,
     *   dry_run: bool,
     *   cities: int,
     *   imported_rows: int,
     *   warmed: int,
     *   failed: int,
     *   results: list<array<string, mixed>>
     * }
     */
    public function enrich(
        int $year,
        ?array $cityIds = null,
        bool $dryRun = false,
        bool $skipImport = false,
        bool $skipWarm = false,
    ): array {
        $cities = $this->consultoriaCities($cityIds);
        $results = [];
        $importedRows = 0;
        $warmed = 0;
        $failed = 0;

        foreach ($cities as $city) {
            $row = [
                'city_id' => (int) $city->id,
                'city' => (string) $city->name,
                'uf' => (string) $city->uf,
                'ibge' => FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio),
                'import' => null,
                'warm' => null,
                'ok' => true,
            ];

            if ($dryRun) {
                $row['import'] = $skipImport
                    ? __('omitido (--skip-import)')
                    : __('importaria Portal/Tesouro (sem FUNDEB)');
                $row['warm'] = $skipWarm
                    ? __('omitido (--skip-warm)')
                    : __('aqueceria consultas públicas + cache da aba');
                $results[] = $row;

                continue;
            }

            if (! $skipImport) {
                try {
                    $import = $this->transfers->importComplementaryForCityYear($city, $year);
                    $row['import'] = $import;
                    $importedRows += (int) ($import['rows'] ?? 0);
                    if (! ($import['success'] ?? false) && (int) ($import['rows'] ?? 0) === 0) {
                        // Sem linhas não é falha fatal (fonte pode estar vazia).
                    }
                } catch (\Throwable $e) {
                    $row['ok'] = false;
                    $row['import'] = ['success' => false, 'message' => $e->getMessage(), 'rows' => 0];
                    $failed++;
                }
            }

            if (! $skipWarm) {
                try {
                    $filters = IeducarFilterState::fromStoredParams(['ano_letivo' => (string) $year]);
                    AnalyticsTabPayloadCache::forget(AnalyticsTabPayloadCache::OTHER_FUNDING, $city, $filters);
                    $snapshot = $this->publicSnapshot->refresh($city, $filters);
                    $queries = is_array($snapshot['queries'] ?? null) ? $snapshot['queries'] : [];
                    $okQueries = count(array_filter(
                        $queries,
                        static fn ($q): bool => is_array($q) && ($q['status'] ?? '') === 'success',
                    ));
                    $row['warm'] = [
                        'success' => (bool) ($snapshot['available'] ?? false),
                        'queries_ok' => $okQueries,
                        'queries_total' => count($queries),
                        'fetched_at' => $snapshot['fetched_at'] ?? null,
                    ];
                    if ($snapshot['available'] ?? false) {
                        $warmed++;
                    }
                } catch (\Throwable $e) {
                    $row['ok'] = false;
                    $row['warm'] = ['success' => false, 'message' => $e->getMessage()];
                    $failed++;
                }
            }

            $results[] = $row;
        }

        return [
            'year' => $year,
            'dry_run' => $dryRun,
            'cities' => $cities->count(),
            'imported_rows' => $importedRows,
            'warmed' => $warmed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
