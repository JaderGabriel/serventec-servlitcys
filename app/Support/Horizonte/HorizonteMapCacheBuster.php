<?php

namespace App\Support\Horizonte;

use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Support\Facades\Cache;

/** Invalida caches do payload do mapa Horizonte após alterações aos dados / SGE. */
final class HorizonteMapCacheBuster
{
    public const FINGERPRINT_CACHE_KEY = 'horizonte:map:data_fingerprint:v1';

    public static function bust(): void
    {
        Cache::put('horizonte:map:cache_bust', (string) microtime(true), now()->addDays(30));
        self::forgetFingerprint();
    }

    public static function forgetFingerprint(): void
    {
        AdminHomeMapCache::repository()->forget(self::FINGERPRINT_CACHE_KEY);
        Cache::forget(self::FINGERPRINT_CACHE_KEY);
    }

    /**
     * Memoiza o fingerprint do mapa (evita ~11 COUNT/MAX em cada map-data).
     *
     * @param  callable(): string  $compute
     */
    public static function rememberFingerprint(callable $compute): string
    {
        $ttl = max(15, min(300, (int) config('horizonte.map_display.fingerprint_cache_seconds', 45)));
        $repo = AdminHomeMapCache::repository();
        $cached = $repo->get(self::FINGERPRINT_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $value = (string) $compute();
        if ($value !== '') {
            $repo->put(self::FINGERPRINT_CACHE_KEY, $value, $ttl);
        }

        return $value;
    }

    public static function token(): string
    {
        return (string) Cache::get('horizonte:map:cache_bust', '0');
    }
}
