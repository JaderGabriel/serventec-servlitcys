<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/**
 * Cache do índice IBGE → sistema de gestão educacional (SGE) para o Horizonte.
 */
final class HorizonteMunicipalSgeCache
{
    private const CACHE_KEY = 'horizonte:sge_registry:index';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     */
    public static function put(array $index): void
    {
        $ttl = max(3600, (int) config('horizonte.sge.registry_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $index, $ttl);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
