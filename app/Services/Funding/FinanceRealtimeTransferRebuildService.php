<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Funding\FundebTransferScope;
use Illuminate\Support\Collection;

/**
 * Purga e reimporta snapshots de repasse para Finanças → Tempo Real (por município/ano).
 */
final class FinanceRealtimeTransferRebuildService
{
    public function __construct(
        private MunicipalTransferImportService $import,
    ) {}

    /**
     * @param  list<int>  $years
     * @param  list<int>|null  $cityIds  null = todos com IBGE
     * @return array{
     *   purged: int,
     *   purged_uf_publicacao: int,
     *   cities: int,
     *   imported: int,
     *   failed: int,
     *   rows_written: int,
     *   results: list<array{slug: string, city_id: int, city: string, year: int, ok: bool, rows: int, message: string}>
     * }
     */
    public function rebuild(array $years, ?array $cityIds = null, bool $purgeBeforeImport = true): array
    {
        $years = array_values(array_unique(array_filter(array_map('intval', $years), static fn (int $y): bool => $y >= 2000)));
        if ($years === []) {
            return $this->emptyResult();
        }

        $purged = 0;
        $purgedUf = 0;

        if ($purgeBeforeImport) {
            $purge = $this->purge($years, $cityIds);
            $purged = $purge['total'];
            $purgedUf = $purge['uf_publicacao'];
        }

        $cities = $this->resolveCities($cityIds);
        $results = [];
        $imported = 0;
        $failed = 0;
        $rowsWritten = 0;

        foreach ($cities as $city) {
            foreach ($years as $year) {
                $slug = FundebTransferScope::cityYearSlug($city, $year);
                $result = $this->import->importForCityYear($city, $year, financeRealtimeRebuild: true);
                $ok = (bool) ($result['success'] ?? false);
                $rows = (int) ($result['rows'] ?? 0);

                if ($ok) {
                    $imported++;
                    $rowsWritten += $rows;
                } else {
                    $failed++;
                }

                $results[] = [
                    'slug' => $slug,
                    'city_id' => (int) $city->id,
                    'city' => (string) $city->name,
                    'uf' => (string) ($city->uf ?? ''),
                    'year' => $year,
                    'ok' => $ok,
                    'rows' => $rows,
                    'message' => (string) ($result['message'] ?? ''),
                    'by_fonte' => is_array($result['by_fonte'] ?? null) ? $result['by_fonte'] : [],
                ];
            }
        }

        return [
            'purged' => $purged,
            'purged_uf_publicacao' => $purgedUf,
            'cities' => $cities->count(),
            'imported' => $imported,
            'failed' => $failed,
            'rows_written' => $rowsWritten,
            'results' => $results,
        ];
    }

    /**
     * @param  list<int>  $years
     * @param  list<int>|null  $cityIds
     * @return array{total: int, uf_publicacao: int}
     */
    public function purge(array $years, ?array $cityIds = null): array
    {
        $years = array_values(array_unique(array_filter(array_map('intval', $years), static fn (int $y): bool => $y >= 2000)));
        if ($years === []) {
            return ['total' => 0, 'uf_publicacao' => 0];
        }

        $q = MunicipalTransferSnapshot::query()->whereIn('ano', $years);
        if ($cityIds !== null && $cityIds !== []) {
            $ibges = City::query()
                ->whereIn('id', $cityIds)
                ->pluck('ibge_municipio')
                ->map(static fn ($v) => MunicipalTransferSnapshotRepository::normalizeIbge((string) $v))
                ->filter()
                ->values()
                ->all();
            if ($ibges === []) {
                return ['total' => 0, 'uf_publicacao' => 0];
            }
            $q->whereIn('ibge_municipio', $ibges);
        }

        $purgedUf = (int) (clone $q)->where('fonte', 'tesouro_publicacao')->count();
        $total = (int) $q->count();
        $q->delete();

        return ['total' => $total, 'uf_publicacao' => $purgedUf];
    }

    /**
     * @param  list<int>|null  $cityIds
     * @return Collection<int, City>
     */
    private function resolveCities(?array $cityIds): Collection
    {
        $q = City::query()->forAnalytics()->orderBy('name');

        if ($cityIds !== null && $cityIds !== []) {
            $q->whereIn('id', $cityIds);
        }

        return $q->get()->filter(static function (City $city): bool {
            return MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio) !== null;
        })->values();
    }

    /**
     * @return array{purged: int, purged_uf_publicacao: int, cities: int, imported: int, failed: int, rows_written: int, results: list<array>}
     */
    private function emptyResult(): array
    {
        return [
            'purged' => 0,
            'purged_uf_publicacao' => 0,
            'cities' => 0,
            'imported' => 0,
            'failed' => 0,
            'rows_written' => 0,
            'results' => [],
        ];
    }
}
