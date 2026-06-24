<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso incremental da importação Tesouro por UF (`horizonte:sync-repasses-tesouro`). */
final class HorizonteTesouroRepassesSyncProgress
{
    /**
     * @param  list<int>  $years
     */
    public static function runKey(array $years): string
    {
        $normalized = array_values(array_unique(array_map('intval', $years)));
        sort($normalized);

        return implode('-', array_map('strval', $normalized));
    }

    /**
     * @param  list<int>  $years
     * @return list<string>
     */
    public static function doneUfs(array $years): array
    {
        $cached = Cache::get(self::cacheKey($years));

        return is_array($cached) ? array_values(array_filter($cached)) : [];
    }

    /**
     * @param  list<int>  $years
     */
    public static function isComplete(array $years): bool
    {
        return self::remainingUfs($years) === [];
    }

    /**
     * @param  list<string>  $ufs
     * @param  list<int>  $years
     */
    public static function markDone(array $ufs, array $years): void
    {
        $done = array_values(array_unique(array_merge(
            self::doneUfs($years),
            array_map(static fn (string $uf): string => strtoupper(trim($uf)), $ufs),
        )));
        $ttl = max(3600, (int) config('horizonte.tesouro_repasses_sync.progress_ttl', 604800));
        Cache::put(self::cacheKey($years), $done, now()->addSeconds($ttl));
    }

    /**
     * @param  list<int>  $years
     */
    public static function reset(array $years): void
    {
        Cache::forget(self::cacheKey($years));
    }

    /**
     * @param  list<int>  $years
     * @return list<string>
     */
    public static function remainingUfs(array $years): array
    {
        $all = IbgeMunicipalityCatalog::brazilianUfs();

        return array_values(array_diff($all, self::doneUfs($years)));
    }

    /**
     * @param  list<int>  $years
     */
    private static function cacheKey(array $years): string
    {
        return 'horizonte:tesouro_repasses:'.self::runKey($years);
    }
}
