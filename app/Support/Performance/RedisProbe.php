<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\Redis;
use Throwable;

final class RedisProbe
{
    /**
     * Verifica se o Redis configurado em config/database.php responde a PING.
     */
    public static function isReachable(?string $connection = null): bool
    {
        try {
            $connection ??= (string) config('database.redis.default', 'default');
            $pong = Redis::connection($connection)->ping();

            if (is_bool($pong)) {
                return $pong;
            }

            return is_string($pong) && strtoupper($pong) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public static function recommendedEnvWhenAvailable(): array
    {
        return [
            'CACHE_STORE=redis',
            'SESSION_DRIVER=redis',
            'QUEUE_CONNECTION=redis',
            'PULSE_CACHE_DRIVER=redis',
            'PULSE_INGEST_DRIVER=redis',
        ];
    }
}
