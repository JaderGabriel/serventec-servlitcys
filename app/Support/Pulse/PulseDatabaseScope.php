<?php

namespace App\Support\Pulse;

use App\Services\CityDataConnection;

/**
 * Classifica ligações Laravel DB: base do sistema vs município i-Educar.
 *
 * @phpstan-type ScopeInfo array{
 *   kind: 'system'|'municipal'|'other',
 *   scope_key: string,
 *   city_id: ?int,
 *   driver: string,
 *   connection: string
 * }
 */
final class PulseDatabaseScope
{
    /**
     * @return ScopeInfo
     */
    public static function fromConnectionName(string $connectionName): array
    {
        $connectionName = trim($connectionName);
        $default = (string) config('database.default', 'mysql');
        $prefix = CityDataConnection::CONNECTION_PREFIX;

        if (str_starts_with($connectionName, $prefix)) {
            $cityId = (int) substr($connectionName, strlen($prefix));
            $ctxDriver = MunicipalDatabaseContext::driver();
            $driver = $ctxDriver !== null && $ctxDriver !== ''
                ? $ctxDriver
                : 'ieducar';

            return [
                'kind' => 'municipal',
                'scope_key' => self::municipalScopeKey($cityId > 0 ? $cityId : 0, $driver),
                'city_id' => $cityId > 0 ? $cityId : MunicipalDatabaseContext::cityId(),
                'driver' => $driver,
                'connection' => $connectionName,
            ];
        }

        if ($connectionName === $default || $connectionName === 'mysql' || $connectionName === 'mariadb') {
            $driver = (string) (config("database.connections.{$default}.driver") ?? 'mysql');

            return [
                'kind' => 'system',
                'scope_key' => self::systemScopeKey($driver),
                'city_id' => null,
                'driver' => $driver,
                'connection' => $connectionName,
            ];
        }

        $driver = (string) (config("database.connections.{$connectionName}.driver") ?? 'unknown');

        return [
            'kind' => 'other',
            'scope_key' => 'other:'.$connectionName,
            'city_id' => null,
            'driver' => $driver,
            'connection' => $connectionName,
        ];
    }

    public static function systemScopeKey(string $driver): string
    {
        return 'system:'.strtolower($driver !== '' ? $driver : 'mysql');
    }

    public static function municipalScopeKey(int $cityId, string $driver): string
    {
        return 'municipal:cid:'.$cityId.':'.strtolower($driver !== '' ? $driver : 'ieducar');
    }

    /**
     * @param  ScopeInfo  $scope
     */
    public static function label(array $scope): string
    {
        return match ($scope['kind']) {
            'system' => __('Base do sistema (:driver)', ['driver' => strtoupper((string) $scope['driver'])]),
            'municipal' => __('Município #:id (:driver)', [
                'id' => (string) ($scope['city_id'] ?? '?'),
                'driver' => strtoupper((string) $scope['driver']),
            ]),
            default => (string) ($scope['connection'] ?? 'other'),
        };
    }
}
