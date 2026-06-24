<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/**
 * Cache IBGE → alertas oficiais MEC/FNDE para o mapa Horizonte.
 */
final class HorizonteMunicipalAlertsCache
{
    private const INDEX_KEY = 'horizonte:municipal_alerts:index';

    private const META_KEY = 'horizonte:municipal_alerts:meta';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getIndex(): array
    {
        $cached = Cache::get(self::INDEX_KEY);

        return is_array($cached) ? $cached : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getMeta(): ?array
    {
        $cached = Cache::get(self::META_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, mixed>  $meta
     */
    public static function put(array $index, array $meta): void
    {
        $ttl = max(3600, (int) config('horizonte.municipal_alerts.cache_ttl', 604800));
        Cache::put(self::INDEX_KEY, $index, $ttl);
        Cache::put(self::META_KEY, $meta, $ttl);
    }

    public static function forget(): void
    {
        Cache::forget(self::INDEX_KEY);
        Cache::forget(self::META_KEY);
    }
}
