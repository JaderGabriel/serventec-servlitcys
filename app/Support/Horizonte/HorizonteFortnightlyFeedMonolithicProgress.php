<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Progresso do modo monolítico (--all) para retomar com --continue. */
final class HorizonteFortnightlyFeedMonolithicProgress
{
    private const CACHE_KEY = 'horizonte:fortnightly_feed:monolithic';

    public static function isRunning(): bool
    {
        $state = self::get();

        return is_array($state) && ($state['status'] ?? '') === 'running';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  list<string>  $queue
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    public static function start(array $queue, array $options): array
    {
        $state = [
            'run_id' => now()->format('Ymd-His').'-'.Str::lower(Str::random(6)),
            'status' => $queue === [] ? 'completed' : 'running',
            'phase_queue' => $queue,
            'completed_phases' => [],
            'options' => $options,
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'completed_at' => $queue === [] ? now()->toIso8601String() : null,
        ];

        self::put($state);

        return $state;
    }

    public static function markPhaseDone(string $phaseKey): void
    {
        $state = self::get();
        if ($state === null) {
            return;
        }

        $completed = is_array($state['completed_phases'] ?? null) ? $state['completed_phases'] : [];
        if (! in_array($phaseKey, $completed, true)) {
            $completed[] = $phaseKey;
        }

        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $allDone = count(array_intersect($queue, $completed)) >= count($queue);

        $state['completed_phases'] = $completed;
        $state['status'] = $allDone ? 'completed' : 'running';
        $state['updated_at'] = now()->toIso8601String();
        $state['completed_at'] = $allDone ? now()->toIso8601String() : null;

        self::put($state);
    }

    /**
     * @return list<string>
     */
    public static function remainingPhases(): array
    {
        $state = self::get();
        if ($state === null) {
            return [];
        }

        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $done = is_array($state['completed_phases'] ?? null) ? $state['completed_phases'] : [];

        return array_values(array_diff($queue, $done));
    }

    /**
     * @return list<string>
     */
    public static function completedPhases(): array
    {
        $state = self::get();

        return is_array($state['completed_phases'] ?? null) ? $state['completed_phases'] : [];
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function put(array $state): void
    {
        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $state, now()->addSeconds($ttl));
    }
}
