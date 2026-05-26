<?php

namespace App\Support\Dashboard;

use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Partilha payloads de abas de Finanças já carregados (mesmos filtros/cidade)
 * para o Diagnóstico estratégico evitar consultas duplicadas.
 *
 * Só grava payloads completos — evita «envenenar» o cache com estruturas vazias
 * (ex. fallback de erro silencioso), que deixavam abas em branco e índice 100%.
 */
final class AnalyticsTabPayloadCache
{
    public const DISCREPANCIES = 'discrepancies';

    public const FUNDEB = 'fundeb';

    public const OTHER_FUNDING = 'other_funding';

    public const WORK_DONE = 'work_done';

    public const INCLUSION = 'inclusion';

    private const CACHE_VERSION = 2;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(string $tab, City $city, IeducarFilterState $filters, array $payload): void
    {
        if (($payload['error'] ?? null) !== null || ! self::isComplete($tab, $payload)) {
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
            if (! is_array($cached) || ! self::isComplete($tab, $cached)) {
                return null;
            }

            return $cached;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isComplete(string $tab, array $payload): bool
    {
        return match ($tab) {
            self::DISCREPANCIES => self::isCompleteDiscrepancies($payload),
            self::FUNDEB => self::isCompleteFundeb($payload),
            self::OTHER_FUNDING => self::isCompleteOtherFunding($payload),
            self::WORK_DONE => self::isCompleteWorkDone($payload),
            self::INCLUSION => self::isCompleteInclusion($payload),
            default => false,
        };
    }

    public static function key(string $tab, City $city, IeducarFilterState $filters): string
    {
        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);

        return 'analytics:tab_payload:v'.self::CACHE_VERSION.':'.$tab.':'.(int) $city->id.':'.md5(json_encode($params));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isCompleteDiscrepancies(array $payload): bool
    {
        $dimensions = is_array($payload['dimensions'] ?? null) ? $payload['dimensions'] : [];
        $checks = is_array($payload['checks'] ?? null) ? $payload['checks'] : [];

        if ($dimensions === [] && $checks === []) {
            return false;
        }

        return filled($payload['year_label'] ?? null) || filled($payload['intro'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isCompleteFundeb(array $payload): bool
    {
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];

        return filled($payload['intro'] ?? null)
            && $modules !== []
            && is_array($payload['resource_projection'] ?? null)
            && is_array($payload['complementacao_informe'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isCompleteOtherFunding(array $payload): bool
    {
        return filled($payload['city_name'] ?? null)
            && (filled($payload['intro'] ?? null) || count(is_array($payload['programs'] ?? null) ? $payload['programs'] : []) > 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isCompleteWorkDone(array $payload): bool
    {
        return array_key_exists('activity_available', $payload)
            && is_array($payload['periods'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isCompleteInclusion(array $payload): bool
    {
        return filled($payload['city_name'] ?? null) || filled($payload['year_label'] ?? null);
    }

    private static function ttl(): int
    {
        return max(0, (int) config('analytics.municipality_health_cache_seconds', 300));
    }
}
