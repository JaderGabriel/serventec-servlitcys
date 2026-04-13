<?php

namespace App\Support\Ieducar;

use Illuminate\Database\Connection;

/**
 * Verifica existência de colunas em tabelas qualificadas (schema.tabela ou nome curto).
 */
final class IeducarColumnInspector
{
    public static function tableExists(Connection $db, string $qualifiedTable): bool
    {
        [$schema, $table] = self::parseQualifiedTable($db, $qualifiedTable);
        $row = $db->selectOne(
            'select exists(
                select 1 from information_schema.tables
                where table_schema = ? and table_name = ?
            ) as e',
            [$schema, $table]
        );

        return (bool) ($row->e ?? false);
    }

    public static function columnExists(Connection $db, string $qualifiedTable, string $column): bool
    {
        if ($column === '') {
            return false;
        }

        [$schema, $table] = self::parseQualifiedTable($db, $qualifiedTable);

        if ($db->getDriverName() === 'pgsql') {
            $row = $db->selectOne(
                'select exists(
                    select 1 from information_schema.columns
                    where table_schema = ? and table_name = ? and column_name = ?
                ) as e',
                [$schema, $table, $column]
            );
        } else {
            $row = $db->selectOne(
                'select exists(
                    select 1 from information_schema.columns
                    where table_schema = ? and table_name = ? and column_name = ?
                ) as e',
                [$schema, $table, $column]
            );
        }

        return (bool) ($row->e ?? false);
    }

    /**
     * @return array{0: string, 1: string} [schema, table]
     */
    private static function parseQualifiedTable(Connection $db, string $qualifiedTable): array
    {
        if (str_contains($qualifiedTable, '.')) {
            [$s, $t] = explode('.', $qualifiedTable, 2);

            return [trim($s), trim($t)];
        }

        if ($db->getDriverName() === 'pgsql') {
            return ['public', $qualifiedTable];
        }

        $dbName = $db->getDatabaseName() ?? '';

        return [$dbName, $qualifiedTable];
    }
}
