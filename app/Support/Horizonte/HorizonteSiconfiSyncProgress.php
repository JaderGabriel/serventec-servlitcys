<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso incremental da sincronização SICONFI (`horizonte:sync-siconfi` e fase `siconfi_sync`). */
final class HorizonteSiconfiSyncProgress
{
    private const CACHE_PREFIX = 'horizonte:siconfi_sync:';

    private const UFS_CACHE_PREFIX = 'horizonte:siconfi_sync:ufs:';

    public static function runKey(int $year, int $period): string
    {
        return $year.'-'.$period;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(int $year, int $period): ?array
    {
        $cached = Cache::get(self::cacheKey($year, $period));

        return is_array($cached) ? $cached : null;
    }

    public static function isActive(int $year, int $period): bool
    {
        $state = self::get($year, $period);

        return is_array($state) && ($state['status'] ?? '') === 'running';
    }

    public static function isComplete(int $year, int $period): bool
    {
        return self::remainingUfs($year, $period) === [];
    }

    public static function start(int $year, int $period, ?string $uf = null): void
    {
        $ttl = self::ttl();
        Cache::put(self::cacheKey($year, $period), [
            'year' => $year,
            'period' => $period,
            'uf' => HorizonteUfScope::normalize($uf),
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'completed_at' => null,
        ], now()->addSeconds($ttl));
    }

    public static function markComplete(int $year, int $period): void
    {
        $state = self::get($year, $period) ?? [
            'year' => $year,
            'period' => $period,
            'uf' => null,
            'started_at' => now()->toIso8601String(),
        ];

        $ttl = self::ttl();
        Cache::put(self::cacheKey($year, $period), array_merge($state, [
            'status' => 'complete',
            'updated_at' => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
        ]), now()->addSeconds($ttl));
    }

    public static function reset(int $year, int $period): void
    {
        Cache::forget(self::cacheKey($year, $period));
        self::resetUfs($year, $period);
    }

    /**
     * @return list<string>
     */
    public static function doneUfs(int $year, int $period): array
    {
        $cached = Cache::get(self::ufsCacheKey($year, $period));

        return is_array($cached) ? array_values(array_filter($cached)) : [];
    }

    /**
     * @return list<string>
     */
    public static function remainingUfs(int $year, int $period): array
    {
        $all = IbgeMunicipalityCatalog::brazilianUfs();

        return array_values(array_diff($all, self::doneUfs($year, $period)));
    }

    /**
     * @param  list<string>  $ufs
     */
    public static function markUfsDone(array $ufs, int $year, int $period): void
    {
        $normalized = array_values(array_filter(array_map(
            static fn (string $uf): string => strtoupper(trim($uf)),
            $ufs,
        )));
        if ($normalized === []) {
            return;
        }

        $done = array_values(array_unique(array_merge(self::doneUfs($year, $period), $normalized)));
        Cache::put(self::ufsCacheKey($year, $period), $done, now()->addSeconds(self::ttl()));
    }

    /**
     * @param  list<string>  $ufs
     */
    public static function unmarkUfs(array $ufs, int $year, int $period): void
    {
        $remove = array_fill_keys(array_map(
            static fn (string $uf): string => strtoupper(trim($uf)),
            $ufs,
        ), true);
        if ($remove === []) {
            return;
        }

        $done = array_values(array_filter(
            self::doneUfs($year, $period),
            static fn (string $uf): bool => ! isset($remove[strtoupper(trim($uf))]),
        ));
        Cache::put(self::ufsCacheKey($year, $period), $done, now()->addSeconds(self::ttl()));
    }

    public static function resetUfs(int $year, int $period): void
    {
        Cache::forget(self::ufsCacheKey($year, $period));
    }

    /**
     * @return array{pending: int, total: int, done: int, status: string|null}
     */
    public static function coverageSummary(int $year, int $period, int $totalMunicipalities, int $pending): array
    {
        $done = max(0, $totalMunicipalities - $pending);
        $state = self::get($year, $period);

        return [
            'pending' => $pending,
            'total' => $totalMunicipalities,
            'done' => $done,
            'status' => is_array($state) ? (string) ($state['status'] ?? '') : null,
        ];
    }

    private static function cacheKey(int $year, int $period): string
    {
        return self::CACHE_PREFIX.self::runKey($year, $period);
    }

    private static function ufsCacheKey(int $year, int $period): string
    {
        return self::UFS_CACHE_PREFIX.self::runKey($year, $period);
    }

    private static function ttl(): int
    {
        return max(3600, (int) config('horizonte.siconfi_sync.progress_ttl', 7776000));
    }
}
