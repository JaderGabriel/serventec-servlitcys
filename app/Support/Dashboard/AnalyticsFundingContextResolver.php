<?php

namespace App\Support\Dashboard;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;

/**
 * Evita repetir fundingImpactSnapshot no mesmo pedido HTTP (várias abas Finanças / preload).
 */
final class AnalyticsFundingContextResolver
{
    /** @var array<string, ?array<string, mixed>> */
    private array $cache = [];

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(City $city, IeducarFilterState $filters, DiscrepanciesRepository $repository): ?array
    {
        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);
        $key = (int) $city->id.':'.md5(json_encode($params));

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $this->cache[$key] = $repository->fundingImpactSnapshot($city, $filters);

        return $this->cache[$key];
    }
}
