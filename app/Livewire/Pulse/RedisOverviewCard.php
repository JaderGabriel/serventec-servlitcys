<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Throwable;

/**
 * Resumo do servidor Redis (memória, chaves no DB lógico, versão) por ligação configurada.
 */
#[Lazy]
class RedisOverviewCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            return $this->snapshot();
        }, 'overview');

        return View::make('livewire.pulse.redis-overview-card', [
            'payload' => $payload,
            'time' => $time,
            'runAt' => $runAt,
            'cacheStore' => config('cache.default'),
            'redisPrefix' => (string) config('database.redis.options.prefix', ''),
            'cachePrefix' => (string) config('cache.prefix', ''),
        ]);
    }

    /**
     * @return array{ok: bool, error: ?string, connections: list<array<string, mixed>>}
     */
    protected function snapshot(): array
    {
        $out = [
            'ok' => true,
            'error' => null,
            'connections' => [],
        ];

        try {
            foreach (['default', 'cache'] as $name) {
                if (! is_array(config("database.redis.{$name}"))) {
                    continue;
                }

                try {
                    $conn = Redis::connection($name);
                    $info = $conn->info();
                    if (! is_array($info)) {
                        $info = [];
                    }

                    $dbIndex = (int) (config("database.redis.{$name}.database") ?? 0);
                    $dbKey = 'db'.$dbIndex;
                    $keyspace = $info[$dbKey] ?? null;
                    if (! is_string($keyspace)) {
                        $keyspace = null;
                    }

                    $dbsize = null;
                    try {
                        $dbsize = (int) $conn->command('dbSize', []);
                    } catch (Throwable) {
                        // extensão / cliente alternativo
                    }

                    $out['connections'][] = [
                        'name' => $name,
                        'db_index' => $dbIndex,
                        'redis_version' => $info['redis_version'] ?? null,
                        'used_memory_human' => $info['used_memory_human'] ?? null,
                        'connected_clients' => isset($info['connected_clients']) ? (int) $info['connected_clients'] : null,
                        'total_commands_processed' => isset($info['total_commands_processed']) ? (int) $info['total_commands_processed'] : null,
                        'keyspace' => $keyspace,
                        'dbsize' => $dbsize,
                    ];
                } catch (Throwable $e) {
                    $out['connections'][] = [
                        'name' => $name,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if ($out['connections'] === []) {
                $out['ok'] = false;
                $out['error'] = __('Nenhuma ligação Redis configurada.');
            }
        } catch (Throwable $e) {
            $out['ok'] = false;
            $out['error'] = $e->getMessage();
        }

        return $out;
    }
}
