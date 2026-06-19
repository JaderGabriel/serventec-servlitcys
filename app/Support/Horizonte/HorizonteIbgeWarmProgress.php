<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso incremental do aquecimento IBGE (uma ou mais UFs por invocação). */
final class HorizonteIbgeWarmProgress
{
    private const CACHE_KEY = 'horizonte:ibge_warm:progress';

    /**
     * @return list<string>
     */
    public static function doneUfs(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? array_values(array_filter($cached)) : [];
    }

    public static function isComplete(): bool
    {
        $all = IbgeMunicipalityCatalog::brazilianUfs();
        $done = self::doneUfs();

        return count(array_diff($all, $done)) === 0;
    }

    /**
     * @param  list<string>  $ufs
     */
    public static function markDone(array $ufs): void
    {
        $done = array_values(array_unique(array_merge(self::doneUfs(), array_map('strtoupper', $ufs))));
        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $done, now()->addSeconds($ttl));
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    public static function remainingUfs(): array
    {
        $all = IbgeMunicipalityCatalog::brazilianUfs();

        return array_values(array_diff($all, self::doneUfs()));
    }
}
