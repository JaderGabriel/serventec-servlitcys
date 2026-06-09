<?php

namespace App\Support\Dashboard;

use App\Support\Performance\RedisProbe;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache do mapa «Municípios implementados» (Início) — Redis quando disponível, TTL ≥ 1 h.
 */
final class AdminHomeMapCache
{
    public static function ttlSeconds(): int
    {
        return max(3600, (int) config('performance.home_map_cache_ttl', 3600));
    }

    public static function repository(): Repository
    {
        $preferred = strtolower(trim((string) config('performance.home_map_cache_store', 'redis')));

        if ($preferred === 'redis' && RedisProbe::isReachable('cache')) {
            return Cache::store('redis');
        }

        return Cache::store();
    }

    public static function get(string $key): mixed
    {
        return self::repository()->get($key);
    }

    public static function put(string $key, mixed $value): void
    {
        self::repository()->put($key, $value, self::ttlSeconds());
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function remember(string $key, callable $callback): mixed
    {
        return self::repository()->remember($key, self::ttlSeconds(), $callback);
    }
}
