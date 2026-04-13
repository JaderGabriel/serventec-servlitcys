<?php

namespace App\Support\Ieducar;

/**
 * Resolve nomes de tabelas iEducar na base do município (MySQL ou PostgreSQL).
 *
 * Se IEDUCAR_SCHEMA estiver definido (ex.: pmieducar no PostgreSQL Portabilis),
 * prefixa as tabelas. Se o nome em config já contiver ponto (schema.tabela), não duplica.
 */
final class IeducarSchema
{
    public static function resolveTable(string $logicalKey): string
    {
        $table = config("ieducar.tables.{$logicalKey}");
        if ($table === null || $table === '') {
            throw new \InvalidArgumentException("ieducar.tables.{$logicalKey} não está definido.");
        }

        $table = (string) $table;

        if (str_contains($table, '.')) {
            return $table;
        }

        $schema = trim((string) config('ieducar.schema', ''));

        if ($schema !== '') {
            return $schema.'.'.$table;
        }

        return $table;
    }
}
