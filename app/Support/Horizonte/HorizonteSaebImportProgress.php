<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/** Progresso incremental da importação SAEB Horizonte (um ou mais anos por invocação). */
final class HorizonteSaebImportProgress
{
    private const CACHE_KEY = 'horizonte:saeb_import:progress';

    /**
     * @return list<int>
     */
    public static function doneYears(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached)
            ? array_values(array_unique(array_filter(array_map('intval', $cached))))
            : [];
    }

    /**
     * @param  list<int>  $allYears
     * @return list<int>
     */
    public static function remainingYears(array $allYears): array
    {
        $done = array_flip(self::doneYears());

        return array_values(array_filter(
            array_map('intval', $allYears),
            static fn (int $year): bool => ! isset($done[$year]),
        ));
    }

    public static function isComplete(array $allYears): bool
    {
        return self::remainingYears($allYears) === [];
    }

    public static function markDone(int $year): void
    {
        $done = self::doneYears();
        if (! in_array($year, $done, true)) {
            $done[] = $year;
        }
        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $done, now()->addSeconds($ttl));
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
