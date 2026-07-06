<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\Redis;
use Predis\Client;
use Predis\Response\Status;
use Throwable;

final class RedisProbe
{
    private const PROBE_KEY = '__servlitcys_redis_probe';

    /**
     * Cliente Redis efetivo após fallback (phpredis sem extensão → predis).
     */
    public static function effectiveClient(): string
    {
        $client = strtolower(trim((string) config('database.redis.client', 'predis')));

        if ($client === 'phpredis' && ! self::phpredisExtensionAvailable()) {
            return 'predis';
        }

        return $client !== '' ? $client : 'predis';
    }

    /**
     * Valor configurado em REDIS_CLIENT (config cache ou .env), antes do fallback de runtime.
     */
    public static function configuredClientFromEnv(): string
    {
        $fromConfig = strtolower(trim((string) config('database.redis.client', '')));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $raw = env('REDIS_CLIENT');
        if (is_string($raw) && trim($raw) !== '') {
            return strtolower(trim($raw));
        }

        return self::phpredisExtensionAvailable() ? 'phpredis' : 'predis';
    }

    /**
     * Alinha config/database.redis.client com o cliente realmente utilizável (igual ao AppServiceProvider).
     */
    public static function applyClientConfig(): void
    {
        $effective = self::effectiveClient();
        if ((string) config('database.redis.client') !== $effective) {
            config(['database.redis.client' => $effective]);
            self::forgetRedisConnections();
        }
    }

    public static function phpredisExtensionAvailable(): bool
    {
        return extension_loaded('redis') || class_exists(\Redis::class, false);
    }

    public static function predisPackageAvailable(): bool
    {
        return class_exists(Client::class);
    }

    /**
     * Interpreta a resposta de PING (phpredis, predis 2/3, Laravel).
     */
    public static function isPingResponse(mixed $response): bool
    {
        if ($response === true || $response === 1 || $response === '1') {
            return true;
        }

        if (is_bool($response)) {
            return $response;
        }

        if ($response instanceof Status) {
            return self::isPingResponse($response->getPayload());
        }

        if (is_string($response)) {
            $normalized = strtoupper(trim($response));

            return $normalized === 'PONG' || $normalized === '+PONG';
        }

        if (is_object($response)) {
            if (method_exists($response, 'getPayload')) {
                return self::isPingResponse($response->getPayload());
            }

            $asString = trim((string) $response);

            return $asString !== '' && self::isPingResponse($asString);
        }

        return false;
    }

    /**
     * Verifica se o Redis configurado em config/database.php responde a PING.
     */
    public static function isReachable(?string $connection = null): bool
    {
        return self::diagnose($connection)['ok'];
    }

    /**
     * @return array{
     *   ok: bool,
     *   connection: string,
     *   client_env: string,
     *   client_effective: string,
     *   host: string,
     *   port: string,
     *   uses_redis_drivers: bool,
     *   error: ?string,
     *   hints: list<string>
     * }
     */
    public static function diagnose(?string $connection = null): array
    {
        $clientEnv = self::configuredClientFromEnv();
        self::applyClientConfig();

        $connection = self::normalizeConnectionName($connection);
        $clientEffective = self::effectiveClient();

        $host = (string) (config("database.redis.{$connection}.host") ?? env('REDIS_HOST', '127.0.0.1'));
        $port = (string) (config("database.redis.{$connection}.port") ?? env('REDIS_PORT', '6379'));

        $hints = [];
        $usesRedisDrivers = self::applicationUsesRedisDrivers();

        if ($clientEnv === 'phpredis' && $clientEffective === 'predis') {
            $hints[] = __('REDIS_CLIENT=phpredis no .env, mas a extensão phpredis não está disponível — a aplicação usa predis (Composer). Recomendado: REDIS_CLIENT=predis.');
        }

        if ($clientEffective === 'predis' && ! self::predisPackageAvailable()) {
            return [
                'ok' => false,
                'connection' => $connection,
                'client_env' => $clientEnv,
                'client_effective' => $clientEffective,
                'host' => $host,
                'port' => $port,
                'uses_redis_drivers' => $usesRedisDrivers,
                'error' => 'Pacote predis/predis não encontrado (composer require predis/predis).',
                'hints' => $hints,
            ];
        }

        if ($clientEffective === 'phpredis' && ! self::phpredisExtensionAvailable()) {
            $hints[] = __('Instale a extensão PHP redis ou defina REDIS_CLIENT=predis.');

            return [
                'ok' => false,
                'connection' => $connection,
                'client_env' => $clientEnv,
                'client_effective' => $clientEffective,
                'host' => $host,
                'port' => $port,
                'uses_redis_drivers' => $usesRedisDrivers,
                'error' => 'Extensão phpredis não carregada.',
                'hints' => $hints,
            ];
        }

        try {
            $reach = self::attemptReachability($connection);
            $ok = $reach['ok'];

            if (! $ok && filled($reach['ping_raw'] ?? null)) {
                $raw = $reach['ping_raw'];
                $hints[] = __('Resposta PING inesperada: :raw', [
                    'raw' => is_scalar($raw) ? (string) $raw : get_debug_type($raw),
                ]);
            }

            return [
                'ok' => $ok,
                'connection' => $connection,
                'client_env' => $clientEnv,
                'client_effective' => $clientEffective,
                'host' => $host,
                'port' => $port,
                'uses_redis_drivers' => $usesRedisDrivers,
                'error' => $ok ? null : ($reach['error'] ?? __('PING sem confirmação PONG.')),
                'hints' => $hints,
            ];
        } catch (Throwable $e) {
            if ($usesRedisDrivers) {
                $hints[] = __('Cache/sessão/filas estão em redis no .env, mas o servidor não respondeu — verifique se o serviço Redis está activo, REDIS_HOST/REDIS_PORT e REDIS_CLIENT=predis quando não houver extensão phpredis.');
            } else {
                $hints[] = __('Instale e inicie o Redis (ex.: sudo systemctl start redis-server), defina REDIS_CLIENT=predis e configure REDIS_* no .env.');
            }

            if (str_contains(strtolower($e->getMessage()), 'class "redis" not found')) {
                $hints[] = __('Defina REDIS_CLIENT=predis no .env e execute php artisan config:clear.');
            }

            return [
                'ok' => false,
                'connection' => $connection,
                'client_env' => $clientEnv,
                'client_effective' => $clientEffective,
                'host' => $host,
                'port' => $port,
                'uses_redis_drivers' => $usesRedisDrivers,
                'error' => $e->getMessage(),
                'hints' => $hints,
            ];
        }
    }

    /**
     * @return array{ok: bool, error: ?string, ping_raw: mixed}
     */
    private static function attemptReachability(string $connection): array
    {
        $conn = Redis::connection($connection);

        try {
            $pong = $conn->ping();
            if (self::isPingResponse($pong)) {
                return ['ok' => true, 'error' => null, 'ping_raw' => $pong];
            }
        } catch (Throwable $e) {
            return self::attemptReachabilityViaSetGet($conn, $e);
        }

        try {
            $pong = $conn->command('PING');
            if (self::isPingResponse($pong)) {
                return ['ok' => true, 'error' => null, 'ping_raw' => $pong];
            }
        } catch (Throwable) {
            // tenta SET/GET abaixo
        }

        return self::attemptReachabilityViaSetGet($conn, null, $pong ?? null);
    }

    /**
     * @return array{ok: bool, error: ?string, ping_raw: mixed}
     */
    private static function attemptReachabilityViaSetGet(mixed $conn, ?Throwable $previous = null, mixed $pingRaw = null): array
    {
        try {
            $conn->set(self::PROBE_KEY, '1', 'EX', 10);

            $ok = (string) $conn->get(self::PROBE_KEY) === '1';
            if ($ok) {
                try {
                    $conn->del(self::PROBE_KEY);
                } catch (Throwable) {
                    // limpeza opcional
                }
            }

            return [
                'ok' => $ok,
                'error' => $ok ? null : ($previous?->getMessage() ?? __('PING sem confirmação PONG.')),
                'ping_raw' => $pingRaw,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $previous?->getMessage() ?? $e->getMessage(),
                'ping_raw' => $pingRaw,
            ];
        }
    }

    private static function normalizeConnectionName(?string $connection): string
    {
        if ($connection === null || $connection === '') {
            return 'default';
        }

        if (is_array(config("database.redis.{$connection}"))) {
            return $connection;
        }

        return 'default';
    }

    public static function applicationUsesRedisDrivers(): bool
    {
        $checks = [
            (string) config('cache.default'),
            (string) config('session.driver'),
            (string) config('queue.default'),
            (string) (config('pulse.cache') ?: config('cache.default')),
            (string) config('pulse.ingest.driver'),
        ];

        foreach ($checks as $value) {
            if (str_contains(strtolower($value), 'redis')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function recommendedEnvWhenAvailable(): array
    {
        $client = self::predisPackageAvailable() && ! self::phpredisExtensionAvailable()
            ? 'predis'
            : (self::phpredisExtensionAvailable() ? 'phpredis' : 'predis');

        return [
            'REDIS_CLIENT='.$client,
            'CACHE_STORE=redis',
            'SESSION_DRIVER=redis',
            'QUEUE_CONNECTION=redis',
            'PULSE_CACHE_DRIVER=redis',
            'PULSE_INGEST_DRIVER=redis',
        ];
    }

    private static function forgetRedisConnections(): void
    {
        if (! app()->bound('redis')) {
            return;
        }

        try {
            $redis = app('redis');
            if (method_exists($redis, 'purge')) {
                $redis->purge();
            }
        } catch (Throwable) {
            app()->forgetInstance('redis');
        }
    }
}
