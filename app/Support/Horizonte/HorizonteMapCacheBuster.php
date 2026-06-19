<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/** Invalida caches do payload do mapa Horizonte após alterações ao registo SGE. */
final class HorizonteMapCacheBuster
{
    public static function bust(): void
    {
        Cache::put('horizonte:map:cache_bust', (string) microtime(true), now()->addDays(30));
    }

    public static function token(): string
    {
        return (string) Cache::get('horizonte:map:cache_bust', '0');
    }
}
