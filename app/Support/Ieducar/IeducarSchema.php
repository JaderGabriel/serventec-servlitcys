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

        /*
         * MySQL/MariaDB: nomes «schema.tabela» do Portabilis (ex.: cadastro.turno) não são válidos
         * como database.tabela na mesma conexão — usa-se o nome curto (turno) na base da cidade.
         */
        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_MYSQL && str_contains($table, '.')) {
            $override = config("ieducar.tables_mysql.{$logicalKey}");
            if (is_string($override) && trim($override) !== '') {
                $table = $override;
            } else {
                $table = self::mysqlShortTableName($logicalKey, $table);
            }
        }

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

        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_PGSQL) {
            $fallback = trim((string) config('ieducar.pgsql_default_schema', ''));

            return $fallback !== '' ? $fallback : 'pmieducar';
        }

        return '';
    }

    /**
     * Nome de tabela curto para MySQL quando só existe config «cadastro.xxx» (Portabilis).
     */
    private static function mysqlShortTableName(string $logicalKey, string $qualified): string
    {
        $short = substr($qualified, (int) strrpos($qualified, '.') + 1);

        return match ($logicalKey) {
            'turno' => $short !== '' ? $short : 'turno',
            'pessoa' => $short !== '' ? $short : 'pessoa',
            'raca' => $short !== '' ? $short : 'raca',
            default => $short,
        };
    }
}
