<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso incremental da importação SIDRA (1 UF por invocação). */
final class HorizonteSidraImportProgress
{
    private const CACHE_KEY = 'horizonte:sidra_import:progress';

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
        return count(array_diff(IbgeMunicipalityCatalog::brazilianUfs(), self::doneUfs())) === 0;
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
        return array_values(array_diff(IbgeMunicipalityCatalog::brazilianUfs(), self::doneUfs()));
    }
}
