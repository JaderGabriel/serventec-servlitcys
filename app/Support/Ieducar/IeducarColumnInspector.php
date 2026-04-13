<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;

/**
 * Verifica existência de colunas em tabelas qualificadas (schema.tabela ou nome curto).
 *
 * No PostgreSQL, nomes sem schema resolvem com o mesmo critério de {@see IeducarSchema::effectiveSchema}
 * quando é passada a cidade (recomendado em bases iEducar 2.x com vários schemas).
 */
final class IeducarColumnInspector
{
    public static function tableExists(Connection $db, string $qualifiedTable, ?City $city = null): bool
    {
        [$schema, $table] = self::parseQualifiedTable($db, $qualifiedTable, $city);
        $row = $db->selectOne(
            'select exists(
                select 1 from information_schema.tables
                where table_schema = ? and table_name = ?
            ) as e',
            [$schema, $table]
        );

        return (bool) ($row->e ?? false);
    }

    public static function columnExists(Connection $db, string $qualifiedTable, string $column, ?City $city = null): bool
    {
        if ($column === '') {
            return false;
        }

        [$schema, $table] = self::parseQualifiedTable($db, $qualifiedTable, $city);

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
    private static function parseQualifiedTable(Connection $db, string $qualifiedTable, ?City $city = null): array
    {
        if (str_contains($qualifiedTable, '.')) {
            [$s, $t] = explode('.', $qualifiedTable, 2);

            return [trim($s), trim($t)];
        }

        if ($db->getDriverName() === 'pgsql') {
            $schema = '';
            if ($city !== null) {
                $schema = IeducarSchema::effectiveSchema($city);
            }
            if ($schema === '') {
                $schema = trim((string) config('ieducar.schema', ''));
            }
            if ($schema === '') {
                $schema = trim((string) config('ieducar.pgsql_default_schema', '')) ?: 'pmieducar';
            }

            return [$schema, $qualifiedTable];
        }

        $dbName = $db->getDatabaseName() ?? '';

        return [$dbName, $qualifiedTable];
    }
}
