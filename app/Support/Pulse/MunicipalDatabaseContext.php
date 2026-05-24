<?php

namespace App\Support\Pulse;

/**
 * Contexto da base i-Educar municipal activa durante CityDataConnection::run().
 */
final class MunicipalDatabaseContext
{
    private static ?int $cityId = null;

    private static ?string $driver = null;

    public static function enter(int $cityId, string $driver): void
    {
        self::$cityId = $cityId;
        self::$driver = $driver;
    }

    public static function leave(): void
    {
        self::$cityId = null;
        self::$driver = null;
    }

    public static function cityId(): ?int
    {
        return self::$cityId;
    }

    public static function driver(): ?string
    {
        return self::$driver;
    }
}
