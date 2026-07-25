<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Collection;

/**
 * Enrich de emendas educação (Portal) para municípios com consultoria activa.
 */
final class ConsultoriaEmendasEnrichmentService
{
    public function __construct(
        private MunicipalEmendaImportService $imports,
    ) {}

    /**
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
     *   catalog_rows: int,
     *   matched_rows: int,
     *   upserted: int,
     *   documentos_fetched: int,
     *   success: bool,
     *   message: string,
     *   by_city: list<array<string, mixed>>
     * }
     */
    public function enrich(int $year, ?array $cityIds = null, bool $dryRun = false): array
    {
        $cities = $this->consultoriaCities($cityIds);
        if ($cities->isEmpty()) {
            return [
                'year' => $year,
                'dry_run' => $dryRun,
                'cities' => 0,
                'catalog_rows' => 0,
                'matched_rows' => 0,
                'upserted' => 0,
                'documentos_fetched' => 0,
                'success' => true,
                'message' => __('Nenhum município de consultoria encontrado.'),
                'by_city' => [],
            ];
        }

        $result = $this->imports->importForCities($cities, $year, $dryRun);

        return [
            'year' => $year,
            'dry_run' => $dryRun,
            'cities' => $cities->count(),
            'catalog_rows' => (int) ($result['catalog_rows'] ?? 0),
            'matched_rows' => (int) ($result['matched_rows'] ?? 0),
            'upserted' => (int) ($result['upserted'] ?? 0),
            'documentos_fetched' => (int) ($result['documentos_fetched'] ?? 0),
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'by_city' => is_array($result['by_city'] ?? null) ? $result['by_city'] : [],
        ];
    }
}
