<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;

final class HorizonteFortnightlyFeedCache
{
    private const CACHE_KEY = 'horizonte:fortnightly_feed:last';

    /**
     * @param  array<string, mixed>  $result
     */
    public static function put(array $result): void
    {
        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.snapshot_cache_ttl', 604800));

        Cache::put(self::CACHE_KEY, array_merge($result, [
            'finished_at' => now()->toIso8601String(),
        ]), now()->addSeconds($ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }
}
