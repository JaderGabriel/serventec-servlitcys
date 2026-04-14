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

    /**
     * @param  list<string>  $candidates
     */
    public static function firstExistingColumn(Connection $db, string $qualifiedTable, array $candidates, ?City $city = null): ?string
    {
        foreach ($candidates as $col) {
            $col = trim((string) $col);
            if ($col !== '' && self::columnExists($db, $qualifiedTable, $col, $city)) {
                return $col;
            }
        }

        return null;
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
     * Procura uma tabela pelo nome (minúsculas) em qualquer schema PostgreSQL ou na base MySQL actual.
     * Útil quando a config aponta para pmieducar.* mas o pivô está em cadastro.* (ou vice-versa).
     *
     * @param  list<string>  $lowerTableNames  ex.: ['aluno_deficiencia','aluno_deficiencias']
     */
    public static function findQualifiedTableByNames(Connection $db, array $lowerTableNames, ?City $city = null): ?string
    {
        $names = array_values(array_unique(array_filter(array_map(static fn ($n) => strtolower(trim((string) $n)), $lowerTableNames))));
        if ($names === []) {
            return null;
        }

        if ($db->getDriverName() === 'pgsql') {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $rows = $db->select(
                'select table_schema, table_name from information_schema.tables
                where table_type in (\'BASE TABLE\',\'VIEW\')
                and table_schema not in (\'pg_catalog\',\'information_schema\')
                and lower(table_name) in ('.$placeholders.')',
                $names
            );
            if ($rows === []) {
                return null;
            }
            $pref = self::schemaPreferenceList($city);
            usort($rows, static function ($a, $b) use ($pref) {
                $ia = array_search($a->table_schema, $pref, true);
                $ib = array_search($b->table_schema, $pref, true);
                $ia = $ia === false ? 99 : $ia;
                $ib = $ib === false ? 99 : $ib;
                if ($ia !== $ib) {
                    return $ia <=> $ib;
                }

                return strcmp((string) $a->table_name, (string) $b->table_name);
            });
            foreach ($rows as $r) {
                $q = $r->table_schema.'.'.$r->table_name;
                if (self::tableExists($db, $q, $city)) {
                    return $q;
                }
            }

            return null;
        }

        $dbName = (string) ($db->getDatabaseName() ?? '');
        if ($dbName === '') {
            return null;
        }
        foreach ($names as $nm) {
            $row = $db->selectOne(
                'select table_name from information_schema.tables
                where table_schema = ? and lower(table_name) = ? limit 1',
                [$dbName, $nm]
            );
            if ($row !== null && isset($row->table_name) && $row->table_name !== '') {
                $short = (string) $row->table_name;

                return self::tableExists($db, $short, $city) ? $short : null;
            }
        }

        return null;
    }

    /**
     * Ordem de preferência de schema (PostgreSQL) para escolher homónimos.
     *
     * @return list<string>
     */
    private static function schemaPreferenceList(?City $city): array
    {
        $sch = ($city !== null) ? IeducarSchema::effectiveSchema($city) : '';
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro'));

        return array_values(array_unique(array_filter([
            'pmieducar',
            $cad,
            $sch,
            'public',
            'modules',
            'educacenso',
            'relatorio',
        ])));
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
