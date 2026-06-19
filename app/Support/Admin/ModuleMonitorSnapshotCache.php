<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\Cache;

final class ModuleMonitorSnapshotCache
{
    private const CACHE_KEY = 'module_monitor:snapshot:last';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function put(array $snapshot): void
    {
        $ttl = max(3600, (int) config('module_monitor.snapshot.cache_ttl', 172800));

        Cache::put(self::CACHE_KEY, $snapshot, now()->addSeconds($ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    public static function isFresh(?array $snapshot = null): bool
    {
        $snapshot ??= self::get();
        if ($snapshot === null || ! isset($snapshot['collected_at'])) {
            return false;
        }

        try {
            $collected = \Illuminate\Support\Carbon::parse((string) $snapshot['collected_at']);
        } catch (\Throwable) {
            return false;
        }

        $maxHours = max(1, (int) config('module_monitor.snapshot.stale_hours', 36));

        return $collected->gte(now()->subHours($maxHours));
    }
}
