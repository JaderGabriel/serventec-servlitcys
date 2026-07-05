<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

/** Progresso incremental da sincronização SICONFI nacional (`horizonte:sync-siconfi`). */
final class HorizonteSiconfiSyncProgress
{
    private const CACHE_PREFIX = 'horizonte:siconfi_sync:';

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
        $state = self::get($year, $period);

        return is_array($state) && ($state['status'] ?? '') === 'complete';
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

    private static function ttl(): int
    {
        return max(3600, (int) config('horizonte.siconfi_sync.progress_ttl', 7776000));
    }
}
