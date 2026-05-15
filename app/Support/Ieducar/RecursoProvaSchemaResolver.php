<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;

/**
 * Descobre tabelas/colunas de recursos de prova INEP (Educacenso) na base i-Educar municipal.
 */
final class RecursoProvaSchemaResolver
{
    /** @var list<string> */
    private const PIVOT_TABLE_NAMES = [
        'aluno_recurso',
        'aluno_recursos',
        'aluno_recurso_inep',
        'aluno_recursos_inep',
        'pessoa_recurso',
        'fisica_recurso',
        'recurso_utilizado_aluno',
        'aluno_recurso_utilizado',
        'recursos_utilizados_aluno',
        'modules.recurso_utilizado_aluno',
        'cadastro.fisica_recurso',
        'pmieducar.aluno_recurso',
    ];

    /** @var list<string> */
    private const CATALOG_TABLE_NAMES = [
        'recurso',
        'recursos',
        'recurso_utilizado',
        'recursos_utilizados',
        'recurso_prova',
        'recursos_prova',
        'recurso_inep',
        'recursos_inep',
        'tipo_recurso',
        'cadastro.recurso',
        'modules.recurso_utilizado',
    ];

    /**
     * @return array{
     *   available: bool,
     *   pivot_table: ?string,
     *   catalog_table: ?string,
     *   pivot_person_col: ?string,
     *   pivot_aluno_col: ?string,
     *   pivot_recurso_col: ?string,
     *   catalog_id_col: ?string,
     *   catalog_name_col: ?string,
     *   discovery_note: ?string,
     *   discovered_tables: list<string>
     * }
     */
    public static function resolve(Connection $db, City $city): array
    {
        $customSql = trim((string) config('ieducar.sql.inclusion_recurso_prova', ''));
        if ($customSql !== '') {
            return [
                'available' => true,
                'pivot_table' => null,
                'catalog_table' => null,
                'pivot_person_col' => null,
                'pivot_aluno_col' => null,
                'pivot_recurso_col' => null,
                'catalog_id_col' => null,
                'catalog_name_col' => null,
                'discovery_note' => __('SQL personalizado (IEDUCAR_SQL_INCLUSION_RECURSO_PROVA).'),
                'discovered_tables' => [],
            ];
        }

        $pivotEnv = trim((string) config('ieducar.tables.aluno_recurso_prova', ''));
        $catalogEnv = trim((string) config('ieducar.tables.recurso_prova_catalogo', ''));

        $discovered = self::discoverTablesMatchingRecurso($db, $city);
        $pivot = $pivotEnv !== '' && IeducarColumnInspector::tableExists($db, $pivotEnv, $city)
            ? $pivotEnv
            : self::findPivotTable($db, $city);
        $catalog = $catalogEnv !== '' && IeducarColumnInspector::tableExists($db, $catalogEnv, $city)
            ? $catalogEnv
            : self::findCatalogTable($db, $city, $pivot);

        if ($pivot === null) {
            $boolPath = self::resolveBooleanColumnsOnFisica($db, $city);
            if ($boolPath === null) {
                return [
                    'available' => false,
                    'pivot_table' => null,
                    'catalog_table' => null,
                    'pivot_person_col' => null,
                    'pivot_aluno_col' => null,
                    'pivot_recurso_col' => null,
                    'catalog_id_col' => null,
                    'catalog_name_col' => null,
                    'boolean_columns' => [],
                    'discovery_note' => __('Nenhuma tabela de recursos de prova detectada.'),
                    'discovered_tables' => $discovered,
                ];
            }

            return [
                'available' => true,
                'pivot_table' => $boolPath['table'],
                'catalog_table' => null,
                'pivot_person_col' => $boolPath['idpes_col'],
                'pivot_aluno_col' => null,
                'pivot_recurso_col' => null,
                'catalog_id_col' => null,
                'catalog_name_col' => null,
                'boolean_columns' => $boolPath['boolean_columns'],
                'discovery_note' => $boolPath['note'],
                'discovered_tables' => $discovered,
            ];
        }

        $pivotPerson = IeducarColumnInspector::firstExistingColumn($db, $pivot, [
            'ref_idpes', 'idpes', 'cod_pessoa',
        ], $city);
        $pivotAluno = IeducarColumnInspector::firstExistingColumn($db, $pivot, [
            'ref_cod_aluno', 'cod_aluno', 'aluno_id', 'id_aluno',
        ], $city);
        $pivotRecurso = IeducarColumnInspector::firstExistingColumn($db, $pivot, [
            'ref_cod_recurso', 'cod_recurso', 'recurso_id', 'id_recurso', 'ref_recurso',
        ], $city);

        $catalogId = null;
        $catalogName = null;
        if ($catalog !== null) {
            $catalogId = IeducarColumnInspector::firstExistingColumn($db, $catalog, [
                (string) config('ieducar.columns.recurso_prova.id', 'cod_recurso'),
                'cod_recurso', 'id', 'id_recurso',
            ], $city);
            $catalogName = IeducarColumnInspector::firstExistingColumn($db, $catalog, [
                (string) config('ieducar.columns.recurso_prova.name', 'nm_recurso'),
                'nm_recurso', 'nome', 'descricao', 'name',
            ], $city);
        }

        $available = ($pivotPerson !== null || $pivotAluno !== null)
            && ($pivotRecurso !== null || $catalog === null);

        return [
            'available' => $available,
            'pivot_table' => $pivot,
            'catalog_table' => $catalog,
            'pivot_person_col' => $pivotPerson,
            'pivot_aluno_col' => $pivotAluno,
            'pivot_recurso_col' => $pivotRecurso,
            'catalog_id_col' => $catalogId,
            'catalog_name_col' => $catalogName,
            'boolean_columns' => [],
            'discovery_note' => $available
                ? __('Tabela pivô: :t', ['t' => $pivot])
                : __('Tabela encontrada mas sem colunas de vínculo reconhecidas.'),
            'discovered_tables' => $discovered,
        ];
    }

    /**
     * @return list<string>
     */
    public static function discoverTablesMatchingRecurso(Connection $db, City $city): array
    {
        $driver = $db->getDriverName();
        $out = [];
        try {
            if ($driver === 'pgsql') {
                $schema = IeducarSchema::effectiveSchema($city);
                $rows = $db->select(
                    "select table_schema || '.' || table_name as qn
                     from information_schema.tables
                     where table_schema not in ('pg_catalog','information_schema')
                       and table_type = 'BASE TABLE'
                       and (table_name ilike '%recurso%' or table_name ilike '%recursos%')
                     order by 1
                     limit 80"
                );
            } else {
                $rows = $db->select(
                    "select table_name as qn from information_schema.tables
                     where table_schema = database()
                       and table_type = 'BASE TABLE'
                       and (lower(table_name) like '%recurso%' or lower(table_name) like '%recursos%')
                     order by 1 limit 80"
                );
            }
            foreach ($rows as $row) {
                $qn = trim((string) ($row->qn ?? ''));
                if ($qn !== '') {
                    $out[] = $qn;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    private static function findPivotTable(Connection $db, City $city): ?string
    {
        foreach (self::PIVOT_TABLE_NAMES as $name) {
            if (IeducarColumnInspector::tableExists($db, $name, $city)) {
                return $name;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, array_map(
            static fn (string $t): string => strtolower(basename(str_replace('.', '/', $t))),
            self::PIVOT_TABLE_NAMES,
        ), $city);
    }

    private static function findCatalogTable(Connection $db, City $city, ?string $pivot): ?string
    {
        foreach (self::CATALOG_TABLE_NAMES as $name) {
            if ($pivot !== null && $name === $pivot) {
                continue;
            }
            if (IeducarColumnInspector::tableExists($db, $name, $city)) {
                return $name;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'recurso', 'recursos', 'recurso_utilizado', 'recursos_utilizados',
        ], $city);
    }

    /**
     * Colunas booleanas em fisica/pessoa (ex.: flags Educacenso legados).
     *
     * @return ?array{table: string, idpes_col: string, boolean_columns: list<string>, note: string}
     */
    private static function resolveBooleanColumnsOnFisica(Connection $db, City $city): ?array
    {
        $patterns = config('ieducar.inclusion.recurso_prova_column_patterns', []);
        if (! is_array($patterns) || $patterns === []) {
            $patterns = ['%recurso%', '%prova%', '%oculos%', '%lupa%', '%ledor%', '%interprete%'];
        }

        foreach (['cadastro.fisica', 'fisica', 'cadastro.pessoa', 'pessoa'] as $candidate) {
            try {
                $table = IeducarSchema::resolveTable(basename($candidate), $city);
            } catch (\InvalidArgumentException) {
                $table = $candidate;
            }
            if (! IeducarColumnInspector::tableExists($db, $table, $city)) {
                continue;
            }
            $idpes = IeducarColumnInspector::firstExistingColumn($db, $table, ['idpes', 'ref_idpes'], $city);
            if ($idpes === null) {
                continue;
            }
            $cols = self::columnsMatchingPatterns($db, $table, $patterns, $city);
            if ($cols !== []) {
                return [
                    'table' => $table,
                    'idpes_col' => $idpes,
                    'boolean_columns' => $cols,
                    'note' => __('Colunas em :t (:n campos).', ['t' => $table, 'n' => count($cols)]),
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    private static function columnsMatchingPatterns(Connection $db, string $table, array $patterns, City $city): array
    {
        [$schema, $short] = self::parseTable($db, $table, $city);
        $cols = [];
        try {
            $rows = $db->select(
                'select column_name, data_type from information_schema.columns
                 where table_schema = ? and table_name = ?',
                [$schema, $short]
            );
            foreach ($rows as $row) {
                $name = strtolower((string) ($row->column_name ?? ''));
                $type = strtolower((string) ($row->data_type ?? ''));
                if ($name === '' || ! in_array($type, ['boolean', 'smallint', 'integer', 'int2', 'int4', 'bit'], true)) {
                    continue;
                }
                foreach ($patterns as $pat) {
                    $pat = str_replace('%', '', strtolower(trim((string) $pat)));
                    if ($pat !== '' && str_contains($name, $pat)) {
                        $cols[] = (string) $row->column_name;
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique($cols));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseTable(Connection $db, string $qualified, City $city): array
    {
        if (str_contains($qualified, '.')) {
            $parts = explode('.', $qualified, 2);

            return [$parts[0], $parts[1]];
        }

        return [IeducarSchema::effectiveSchema($city), $qualified];
    }
}
