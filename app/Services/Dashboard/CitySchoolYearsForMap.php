<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Ieducar\IeducarWorkActivityQueries;

/**
 * Anos letivos por município para o mapa do Início (cache + leitura i-Educar).
 */
final class CitySchoolYearsForMap
{
    public function __construct(
        private readonly CityDataConnection $cityData,
        private readonly FilterOptionsService $filterOptions,
    ) {}

    /**
     * @return list<array{year: int, state: string, state_label: string}>
     */
    public function forCity(City $city): array
    {
        if (! $city->hasDataSetup()) {
            return [];
        }

        return AdminHomeMapCache::remember(
            'dashboard.map.school_years.v3.'.$city->id,
            function () use ($city): array {
                try {
                    $fallback = $this->filterOptions->distinctSchoolYears($city);

                    return $this->cityData->run(
                        $city,
                        fn ($db) => IeducarWorkActivityQueries::schoolYearsCatalog($db, $city, $fallback),
                    );
                } catch (\Throwable) {
                    return [];
                }
            },
        );
    }
}
