<?php

namespace App\Support\Ieducar;

use App\Models\City;

/**
 * Resolve nomes de tabelas iEducar na base do município (MySQL ou PostgreSQL).
 *
 * Se IEDUCAR_SCHEMA estiver definido (ex.: pmieducar no PostgreSQL Portabilis),
 * prefixa as tabelas. Se o nome em config já contiver ponto (schema.tabela), não duplica.
 *
 * Ordem do schema efetivo: tabela já qualificada (schema.tabela) > campo da cidade
 * (ieducar_schema) > IEDUCAR_SCHEMA > para pgsql sem nada acima, pgsql_default_schema
 * (pmieducar por defeito).
 */
final class IeducarSchema
{
    public static function resolveTable(string $logicalKey, ?City $city = null): string
    {
        $table = config("ieducar.tables.{$logicalKey}");
        if ($table === null || $table === '') {
            throw new \InvalidArgumentException("ieducar.tables.{$logicalKey} não está definido.");
        }

        $table = (string) $table;

        if (str_contains($table, '.')) {
            return $table;
        }

        $schema = self::effectiveSchema($city);

        if ($schema !== '') {
            return $schema.'.'.$table;
        }

        return $table;
    }

    /**
     * Schema PostgreSQL a aplicar antes do nome da tabela (vazio = MySQL ou public sem prefixo).
     */
    public static function effectiveSchema(?City $city): string
    {
        $global = trim((string) config('ieducar.schema', ''));
        $fromCity = trim((string) ($city?->ieducar_schema ?? ''));

        if ($fromCity !== '') {
            return $fromCity;
        }

        if ($global !== '') {
            return $global;
        }

        if ($city !== null && $city->dataDriver() === City::DRIVER_PGSQL) {
            $fallback = trim((string) config('ieducar.pgsql_default_schema', ''));

            return $fallback;
        }

        return '';
    }
}
