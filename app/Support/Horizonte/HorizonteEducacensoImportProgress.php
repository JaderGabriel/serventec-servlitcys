<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/** Progresso incremental da fase Educacenso Horizonte (um ou mais anos por invocação). */
final class HorizonteEducacensoImportProgress
{
    private const CACHE_KEY = 'horizonte:educacenso_import:progress';

    private const CACHE_KEY_FAILED = 'horizonte:educacenso_import:last_failed';

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

    public static function lastFailedYear(): ?int
    {
        $year = Cache::get(self::CACHE_KEY_FAILED);

        return is_numeric($year) ? (int) $year : null;
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

    /**
     * @param  list<int>  $allYears
     * @return list<int>
     */
    public static function orderedRemainingYears(array $allYears): array
    {
        $remaining = self::remainingYears($allYears);
        sort($remaining, SORT_NUMERIC);

        $failed = self::lastFailedYear();
        if ($failed !== null && count($remaining) > 1 && in_array($failed, $remaining, true)) {
            $remaining = array_values(array_filter(
                $remaining,
                static fn (int $year): bool => $year !== $failed,
            ));
            $remaining[] = $failed;
        }

        return $remaining;
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
        self::storeDone($done);
        self::clearFailed();
    }

    public static function markFailed(int $year): void
    {
        $ttl = self::cacheTtl();
        Cache::put(self::CACHE_KEY_FAILED, $year, now()->addSeconds($ttl));
    }

    public static function clearFailed(): void
    {
        Cache::forget(self::CACHE_KEY_FAILED);
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_FAILED);
    }

    /**
     * @param  list<int>  $done
     */
    private static function storeDone(array $done): void
    {
        Cache::put(self::CACHE_KEY, $done, now()->addSeconds(self::cacheTtl()));
    }

    private static function cacheTtl(): int
    {
        return max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
    }
}
