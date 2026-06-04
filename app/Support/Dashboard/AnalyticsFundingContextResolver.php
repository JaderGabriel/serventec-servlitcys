<?php

namespace App\Support\Dashboard;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;

/**
 * Evita repetir consultas financeiras no mesmo pedido HTTP (várias abas Finanças / preload).
 *
 * - {@see lightContext}: matrículas + VAAF (Tempo Real, Comparativo, buildReport leve).
 * - {@see snapshot}: resumo Discrepâncias (perda/ganho) quando necessário.
 */
final class AnalyticsFundingContextResolver
{
    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    /**
     * Contexto leve: matrículas activas + referência VAAF (sem rotinas de discrepância).
     *
     * @return array<string, mixed>
     */
    public function lightContext(City $city, IeducarFilterState $filters, DiscrepanciesRepository $repository): array
    {
        $key = $this->cacheKey($city, $filters).':light';

        if (array_key_exists($key, $this->cache) && is_array($this->cache[$key])) {
            return $this->cache[$key];
        }

        $this->cache[$key] = $repository->lightFundingContext($city, $filters);

        return $this->cache[$key];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(City $city, IeducarFilterState $filters, DiscrepanciesRepository $repository): ?array
    {
        $key = $this->cacheKey($city, $filters).':impact';

        if (array_key_exists($key, $this->cache)) {
            $cached = $this->cache[$key];

            return is_array($cached) ? $cached : null;
        }

        $this->cache[$key] = $repository->fundingImpactSnapshot($city, $filters);

        $cached = $this->cache[$key];

        return is_array($cached) ? $cached : null;
    }

    private function cacheKey(City $city, IeducarFilterState $filters): string
    {
        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);

        return (int) $city->id.':'.md5(json_encode($params));
    }
}
