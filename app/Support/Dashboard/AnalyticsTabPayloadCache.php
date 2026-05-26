<?php

namespace App\Support\Dashboard;

use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Partilha payloads de abas de Finanças já carregados (mesmos filtros/cidade)
 * para o Diagnóstico estratégico evitar consultas duplicadas.
 */
final class AnalyticsTabPayloadCache
{
    public const DISCREPANCIES = 'discrepancies';

    public const FUNDEB = 'fundeb';

    public const OTHER_FUNDING = 'other_funding';

    public const WORK_DONE = 'work_done';

    public const INCLUSION = 'inclusion';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(string $tab, City $city, IeducarFilterState $filters, array $payload): void
    {
        if (($payload['error'] ?? null) !== null) {
            return;
        }

        $ttl = self::ttl();
        if ($ttl <= 0) {
            return;
        }

        try {
            Cache::put(self::key($tab, $city, $filters), $payload, $ttl);
        } catch (\Throwable $e) {
            Log::warning('analytics.tab_payload_cache_put_failed', [
                'tab' => $tab,
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $tab, City $city, IeducarFilterState $filters): ?array
    {
        if (self::ttl() <= 0) {
            return null;
        }

        try {
            $cached = Cache::get(self::key($tab, $city, $filters));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function key(string $tab, City $city, IeducarFilterState $filters): string
    {
        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);

        return 'analytics:tab_payload:'.$tab.':'.(int) $city->id.':'.md5(json_encode($params));
    }

    private static function ttl(): int
    {
        return max(0, (int) config('analytics.municipality_health_cache_seconds', 300));
    }
}
