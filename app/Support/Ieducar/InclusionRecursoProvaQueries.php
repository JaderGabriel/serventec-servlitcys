<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Recursos de prova INEP (Censo) × cadastro NEE — cruzamento para Inclusão e Discrepâncias.
 */
final class InclusionRecursoProvaQueries
{
    public static function canQuery(Connection $db, City $city): bool
    {
        return (bool) (self::schema($db, $city)['available'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(Connection $db, City $city): array
    {
        return RecursoProvaSchemaResolver::resolve($db, $city);
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasComRecursoProvaPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return self::aggregatePorEscolaComSubqueryRecurso($db, $city, $filters, true);
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasRecursoProvaSemNeePorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return self::aggregatePorEscolaComSubqueryRecurso($db, $city, $filters, false);
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasNeeSemRecursoProvaPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        if (! (bool) config('ieducar.inclusion.recurso_prova_exigir_com_nee', false)) {
            return [];
        }

        try {
            $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
            $recursoSub = self::alunosComRecursoProvaSubquery($db, $city);
            if ($neeSub === null || $recursoSub === null) {
                return [];
            }

            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.(string) config('ieducar.columns.matricula.aluno'), '=', 'a.'.$aId);
            $q->whereIn('a.'.$aId, $neeSub);
            $q->whereNotIn('a.'.$aId, $recursoSub);

            return DiscrepanciesQueries::aggregatePorEscolaPublic($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasRecursoIncompativelPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $rules = config('ieducar.inclusion.recurso_deficiencia_incompatibilidades', []);
        if (! is_array($rules) || $rules === []) {
            return [];
        }

        try {
            $schema = self::schema($db, $city);
            if (! ($schema['available'] ?? false)) {
                return [];
            }

            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);
            if ($aIdpes === null) {
                return [];
            }

            $recursoLabels = self::recursoLabelsPorAluno($db, $city, $schema);
            $deficienciaLabels = self::deficienciaLabelsPorAluno($db, $city);
            if ($recursoLabels === [] || $deficienciaLabels === []) {
                return [];
            }

            $badAlunos = [];
            foreach ($recursoLabels as $aid => $recursos) {
                $defs = $deficienciaLabels[$aid] ?? [];
                if (! self::passesCompatibilityRules($recursos, $defs, $rules)) {
                    $badAlunos[$aid] = true;
                }
            }
            if ($badAlunos === []) {
                return [];
            }

            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';
            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.(string) config('ieducar.columns.matricula.aluno'), '=', 'a.'.$aId)
                ->whereIn('a.'.$aId, array_keys($badAlunos));

            return DiscrepanciesQueries::aggregatePorEscolaPublic($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return array{
     *   com_recurso: int,
     *   sem_nee: int,
     *   nee_sem_recurso: int,
     *   catalogo: list<array{nome: string, total: int}>,
     *   schema_note: ?string
     * }
     */
    public static function resumoCruzamento(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $schema = self::schema($db, $city);
        if (! ($schema['available'] ?? false)) {
            return [
                'com_recurso' => 0,
                'sem_nee' => 0,
                'nee_sem_recurso' => 0,
                'catalogo' => [],
                'schema_note' => (string) ($schema['discovery_note'] ?? null),
            ];
        }

        $semNeeRows = self::matriculasRecursoProvaSemNeePorEscola($db, $city, $filters);
        $comRecurso = array_sum(array_column(self::matriculasComRecursoProvaPorEscola($db, $city, $filters), 'total'));
        $semNee = array_sum(array_column($semNeeRows, 'total'));
        $neeSemRecurso = array_sum(array_column(
            self::matriculasNeeSemRecursoProvaPorEscola($db, $city, $filters),
            'total',
        ));

        return [
            'com_recurso' => $comRecurso,
            'sem_nee' => $semNee,
            'nee_sem_recurso' => $neeSemRecurso,
            'catalogo' => self::catalogoRecursosProvaResumo($db, $city, $filters),
            'schema_note' => (string) ($schema['discovery_note'] ?? null),
        ];
    }

    /**
     * @return list<array{nome: string, total: int}>
     */
    public static function catalogoRecursosProvaResumo(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $customSql = trim((string) config('ieducar.sql.inclusion_recurso_prova_catalogo', ''));
        if ($customSql !== '') {
            return self::catalogoFromCustomSql($db, $city, $customSql);
        }

        $schema = self::schema($db, $city);
        if (! ($schema['available'] ?? false)) {
            return [];
        }

        $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
        if ($boolCols !== []) {
            return self::catalogoFromBooleanColumns($db, $city, $schema);
        }

        $pivot = (string) ($schema['pivot_table'] ?? '');
        $catalog = (string) ($schema['catalog_table'] ?? '');
        $pivotRecurso = (string) ($schema['pivot_recurso_col'] ?? '');
        $catalogId = (string) ($schema['catalog_id_col'] ?? '');
        $catalogName = (string) ($schema['catalog_name_col'] ?? '');
        if ($pivot === '' || $pivotRecurso === '') {
            return [];
        }

        try {
            $q = $db->table($pivot.' as rp');
            if ($catalog !== '' && $catalogId !== '' && $catalogName !== '') {
                $q->leftJoin($catalog.' as rc', 'rp.'.$pivotRecurso, '=', 'rc.'.$catalogId)
                    ->selectRaw('COALESCE(rc.'.$catalogName.', CAST(rp.'.$pivotRecurso.' AS TEXT)) as nome')
                    ->selectRaw('COUNT(*) as total')
                    ->groupByRaw('COALESCE(rc.'.$catalogName.', CAST(rp.'.$pivotRecurso.' AS TEXT))');
            } else {
                $q->selectRaw('CAST(rp.'.$pivotRecurso.' AS TEXT) as nome')
                    ->selectRaw('COUNT(*) as total')
                    ->groupByRaw('CAST(rp.'.$pivotRecurso.' AS TEXT)');
            }

            $out = [];
            foreach ($q->get() as $row) {
                $arr = (array) $row;
                $nome = trim((string) ($arr['nome'] ?? ''));
                $total = (int) ($arr['total'] ?? 0);
                if ($nome !== '' && $total > 0) {
                    $out[] = ['nome' => $nome, 'total' => $total];
                }
            }
            usort($out, static fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return \Closure(Builder): void|null
     */
    public static function alunosComRecursoProvaSubquery(Connection $db, City $city): ?\Closure
    {
        $customSql = trim((string) config('ieducar.sql.inclusion_recurso_prova_alunos', ''));
        if ($customSql !== '') {
            $sql = IeducarSqlPlaceholders::interpolate($customSql, $city);

            return static function ($sub) use ($sql): void {
                $sub->fromRaw('('.$sql.') as rec_alunos');
            };
        }

        $schema = self::schema($db, $city);
        if (! ($schema['available'] ?? false)) {
            return null;
        }

        $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
        if ($boolCols !== []) {
            return self::subqueryFromBooleanColumns($db, $city, $schema);
        }

        return self::subqueryFromPivot($db, $city, $schema);
    }

    /**
     * @param  bool  $comRecurso  true = com recurso; false = com recurso e sem NEE
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    private static function aggregatePorEscolaComSubqueryRecurso(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        bool $comRecurso,
    ): array {
        try {
            $recursoSub = self::alunosComRecursoProvaSubquery($db, $city);
            if ($recursoSub === null) {
                return [];
            }

            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.(string) config('ieducar.columns.matricula.aluno'), '=', 'a.'.$aId)
                ->whereIn('a.'.$aId, $recursoSub);

            if (! $comRecurso) {
                $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
                if ($neeSub === null) {
                    return [];
                }
                $q->whereNotIn('a.'.$aId, $neeSub);
            }

            return DiscrepanciesQueries::aggregatePorEscolaPublic($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return \Closure(Builder): void
     */
    private static function subqueryFromPivot(Connection $db, City $city, array $schema): ?\Closure
    {
        $pivot = (string) ($schema['pivot_table'] ?? '');
        if ($pivot === '') {
            return null;
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $pivotPerson = $schema['pivot_person_col'] ?? null;
        $pivotAluno = $schema['pivot_aluno_col'] ?? null;

        if ($pivotPerson !== null) {
            $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);
            if ($aIdpes === null) {
                return null;
            }

            return static function ($sub) use ($pivot, $pivotPerson, $aluno, $aId, $aIdpes): void {
                $sub->select('a_r.'.$aId)
                    ->from($aluno.' as a_r')
                    ->whereExists(function ($ex) use ($pivot, $pivotPerson, $aIdpes): void {
                        $ex->from($pivot.' as rp')
                            ->whereColumn('rp.'.$pivotPerson, 'a_r.'.$aIdpes);
                    });
            };
        }

        if ($pivotAluno !== null) {
            return static function ($sub) use ($pivot, $pivotAluno): void {
                $sub->from($pivot.' as rp')->select('rp.'.$pivotAluno)->distinct();
            };
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return \Closure(Builder): void
     */
    private static function subqueryFromBooleanColumns(Connection $db, City $city, array $schema): ?\Closure
    {
        $table = (string) ($schema['pivot_table'] ?? $schema['table'] ?? '');
        $idpesCol = (string) ($schema['pivot_person_col'] ?? $schema['idpes_col'] ?? '');
        $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
        if ($table === '' || $idpesCol === '' || $boolCols === []) {
            return null;
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);
        if ($aIdpes === null) {
            return null;
        }

        $grammar = $db->getQueryGrammar();
        $checks = [];
        foreach ($boolCols as $col) {
            $checks[] = $grammar->wrap($col).' IN (1, true)';
        }
        $orExpr = '('.implode(' OR ', $checks).')';

        return static function ($sub) use ($table, $idpesCol, $orExpr, $aluno, $aId, $aIdpes): void {
            $sub->select('a_r.'.$aId)
                ->from($aluno.' as a_r')
                ->whereExists(function ($ex) use ($table, $idpesCol, $orExpr, $aIdpes): void {
                    $ex->from($table.' as fr')
                        ->whereColumn('fr.'.$idpesCol, 'a_r.'.$aIdpes)
                        ->whereRaw($orExpr);
                });
        };
    }

    /**
     * @return list<array{nome: string, total: int}>
     */
    private static function catalogoFromCustomSql(Connection $db, City $city, string $sql): array
    {
        try {
            $sql = IeducarSqlPlaceholders::interpolate($sql, $city);
            $rows = $db->select($sql);
            $out = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $nome = trim((string) ($arr['nome'] ?? $arr['name'] ?? $arr['label'] ?? ''));
                $total = (int) ($arr['total'] ?? $arr['value'] ?? 0);
                if ($nome !== '' && $total > 0) {
                    $out[] = ['nome' => $nome, 'total' => $total];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, list<string>>
     */
    private static function recursoLabelsPorAluno(Connection $db, City $city, array $schema): array
    {
        try {
            $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
            if ($boolCols !== []) {
                return self::recursoLabelsFromBooleanColumns($db, $city, $schema);
            }

            $pivot = (string) ($schema['pivot_table'] ?? '');
            $pivotRecurso = (string) ($schema['pivot_recurso_col'] ?? '');
            $catalog = (string) ($schema['catalog_table'] ?? '');
            $catalogId = (string) ($schema['catalog_id_col'] ?? '');
            $catalogName = (string) ($schema['catalog_name_col'] ?? '');
            $pivotPerson = $schema['pivot_person_col'] ?? null;
            $pivotAluno = $schema['pivot_aluno_col'] ?? null;
            if ($pivot === '' || $pivotRecurso === '') {
                return [];
            }

            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $labelExpr = $catalog !== '' && $catalogId !== '' && $catalogName !== ''
                ? 'COALESCE(rc.'.$catalogName.', CAST(rp.'.$pivotRecurso.' AS TEXT))'
                : 'CAST(rp.'.$pivotRecurso.' AS TEXT)';

            if ($pivotPerson !== null) {
                $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);
                if ($aIdpes === null) {
                    return [];
                }
                $q = $db->table($aluno.' as a')
                    ->join($pivot.' as rp', 'rp.'.$pivotPerson, '=', 'a.'.$aIdpes);
                if ($catalog !== '' && $catalogId !== '') {
                    $q->leftJoin($catalog.' as rc', 'rp.'.$pivotRecurso, '=', 'rc.'.$catalogId);
                }
                $rows = $q->selectRaw('a.'.$aId.' as aid')->selectRaw($labelExpr.' as lbl')->distinct()->get();
            } elseif ($pivotAluno !== null) {
                $q = $db->table($pivot.' as rp');
                if ($catalog !== '' && $catalogId !== '') {
                    $q->leftJoin($catalog.' as rc', 'rp.'.$pivotRecurso, '=', 'rc.'.$catalogId);
                }
                $rows = $q->selectRaw('rp.'.$pivotAluno.' as aid')->selectRaw($labelExpr.' as lbl')->distinct()->get();
            } else {
                return [];
            }

            return self::mapAlunoLabelRows($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, list<string>>
     */
    private static function recursoLabelsFromBooleanColumns(Connection $db, City $city, array $schema): array
    {
        $table = (string) ($schema['pivot_table'] ?? $schema['table'] ?? '');
        $idpesCol = (string) ($schema['pivot_person_col'] ?? $schema['idpes_col'] ?? '');
        $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
        if ($table === '' || $idpesCol === '' || $boolCols === []) {
            return [];
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);
        if ($aIdpes === null) {
            return [];
        }

        $out = [];
        foreach ($boolCols as $col) {
            try {
                $rows = $db->table($aluno.' as a')
                    ->join($table.' as fr', 'fr.'.$idpesCol, '=', 'a.'.$aIdpes)
                    ->whereRaw($db->getQueryGrammar()->wrap($col).' IN (1, true)')
                    ->selectRaw('a.'.$aId.' as aid')
                    ->selectRaw('? as lbl', [str_replace('_', ' ', $col)])
                    ->distinct()
                    ->get();
                foreach (self::mapAlunoLabelRows($rows) as $aid => $labels) {
                    $out[$aid] = array_values(array_unique(array_merge($out[$aid] ?? [], $labels)));
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @return array<int, list<string>>
     */
    private static function deficienciaLabelsPorAluno(Connection $db, City $city): array
    {
        try {
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);

            $fisica = self::resolveFisicaDeficienciaTable($db, $city);
            if ($fisica !== null && $aIdpes !== null) {
                $defTable = self::resolveDeficienciaCatalogTable($db, $city);
                if ($defTable === null) {
                    return [];
                }
                $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, [
                    (string) config('ieducar.columns.deficiencia.id'),
                    'cod_deficiencia',
                ], $city);
                $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, [
                    (string) config('ieducar.columns.deficiencia.name'),
                    'nm_deficiencia',
                ], $city);
                if ($defPk === null || $nmCol === null) {
                    return [];
                }

                $rows = $db->table($aluno.' as a')
                    ->join($fisica['table'].' as fd', 'fd.'.$fisica['idpes_col'], '=', 'a.'.$aIdpes)
                    ->join($defTable.' as d', 'fd.'.$fisica['def_col'], '=', 'd.'.$defPk)
                    ->selectRaw('a.'.$aId.' as aid')
                    ->selectRaw('d.'.$nmCol.' as lbl')
                    ->distinct()
                    ->get();

                return self::mapAlunoLabelRows($rows);
            }

            $adTable = IeducarColumnInspector::findQualifiedTableByNames($db, ['aluno_deficiencia', 'aluno_deficiencias'], $city);
            if ($adTable === null) {
                return [];
            }
            $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, [
                (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                'ref_cod_aluno',
                'cod_aluno',
            ], $city);
            $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, [
                (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
                'ref_cod_deficiencia',
            ], $city);
            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($adAluno === null || $adDef === null || $defTable === null) {
                return [];
            }
            $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, [
                (string) config('ieducar.columns.deficiencia.id'),
                'cod_deficiencia',
            ], $city);
            $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, [
                (string) config('ieducar.columns.deficiencia.name'),
                'nm_deficiencia',
            ], $city);
            if ($defPk === null || $nmCol === null) {
                return [];
            }

            $rows = $db->table($aluno.' as a')
                ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                ->selectRaw('a.'.$aId.' as aid')
                ->selectRaw('d.'.$nmCol.' as lbl')
                ->distinct()
                ->get();

            return self::mapAlunoLabelRows($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return ?array{table: string, idpes_col: string, def_col: string}
     */
    private static function resolveFisicaDeficienciaTable(Connection $db, City $city): ?array
    {
        $candidates = [];
        try {
            $candidates[] = IeducarSchema::resolveTable('fisica_deficiencia', $city);
        } catch (\InvalidArgumentException) {
        }
        $candidates[] = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica_deficiencia';
        foreach ($candidates as $t) {
            if (! IeducarColumnInspector::tableExists($db, $t, $city)) {
                continue;
            }
            $idpes = IeducarColumnInspector::firstExistingColumn($db, $t, ['ref_idpes', 'idpes'], $city);
            $defCol = IeducarColumnInspector::firstExistingColumn($db, $t, [
                'ref_cod_deficiencia',
                'cod_deficiencia',
                'deficiencia_id',
            ], $city);
            if ($idpes !== null && $defCol !== null) {
                return ['table' => $t, 'idpes_col' => $idpes, 'def_col' => $defCol];
            }
        }

        return null;
    }

    private static function resolveDeficienciaCatalogTable(Connection $db, City $city): ?string
    {
        try {
            $t = IeducarSchema::resolveTable('deficiencia', $city);
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        } catch (\InvalidArgumentException) {
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, ['deficiencia', 'deficiencias'], $city);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array{nome: string, total: int}>
     */
    private static function catalogoFromBooleanColumns(Connection $db, City $city, array $schema): array
    {
        $table = (string) ($schema['pivot_table'] ?? $schema['table'] ?? '');
        $boolCols = is_array($schema['boolean_columns'] ?? null) ? $schema['boolean_columns'] : [];
        if ($table === '' || $boolCols === []) {
            return [];
        }

        $grammar = $db->getQueryGrammar();
        $out = [];
        foreach ($boolCols as $col) {
            try {
                $total = (int) $db->table($table)->whereRaw($grammar->wrap($col).' IN (1, true)')->count();
                if ($total > 0) {
                    $out[] = ['nome' => str_replace('_', ' ', $col), 'total' => $total];
                }
            } catch (\Throwable) {
                continue;
            }
        }
        usort($out, static fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

        return $out;
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<int, list<string>>
     */
    private static function mapAlunoLabelRows(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $aid = (int) ($arr['aid'] ?? 0);
            $lbl = trim((string) ($arr['lbl'] ?? ''));
            if ($aid <= 0 || $lbl === '') {
                continue;
            }
            $out[$aid] ??= [];
            if (! in_array($lbl, $out[$aid], true)) {
                $out[$aid][] = $lbl;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $recursos
     * @param  list<string>  $deficiencias
     * @param  list<array{recurso?: list<string>, deficiencia?: list<string>}>  $rules
     */
    private static function passesCompatibilityRules(array $recursos, array $deficiencias, array $rules): bool
    {
        $recLower = array_map(static fn (string $s): string => mb_strtolower($s), $recursos);
        $defLower = array_map(static fn (string $s): string => mb_strtolower($s), $deficiencias);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $rk = is_array($rule['recurso'] ?? null) ? $rule['recurso'] : [];
            $dk = is_array($rule['deficiencia'] ?? null) ? $rule['deficiencia'] : [];
            $recHit = false;
            foreach ($rk as $kw) {
                foreach ($recLower as $r) {
                    if (str_contains($r, mb_strtolower((string) $kw))) {
                        $recHit = true;
                        break 2;
                    }
                }
            }
            if (! $recHit) {
                continue;
            }
            $defHit = false;
            foreach ($dk as $kw) {
                foreach ($defLower as $d) {
                    if (str_contains($d, mb_strtolower((string) $kw))) {
                        $defHit = true;
                        break 2;
                    }
                }
            }
            if (! $defHit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{escola_id: string, escola: string, total: int}>  $schoolRows
     * @return list<array{escola_id: string, escola: string, total: int, tipos_recurso: string}>
     */
    public static function enriquecerLinhasEscolaComTiposRecurso(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $schoolRows,
    ): array {
        if ($schoolRows === []) {
            return [];
        }

        $schema = self::schema($db, $city);
        if (! ($schema['available'] ?? false)) {
            return array_map(
                static fn (array $r): array => array_merge($r, ['tipos_recurso' => '']),
                $schoolRows,
            );
        }

        $recursoLabels = self::recursoLabelsPorAluno($db, $city, $schema);
        $neeIds = self::alunoIdsComNee($db, $city);
        $alunosSemNeeComRecurso = [];
        foreach ($recursoLabels as $aid => $labels) {
            if (isset($neeIds[$aid]) || $labels === []) {
                continue;
            }
            $alunosSemNeeComRecurso[$aid] = $labels;
        }

        if ($alunosSemNeeComRecurso === []) {
            return array_map(
                static fn (array $r): array => array_merge($r, ['tipos_recurso' => '']),
                $schoolRows,
            );
        }

        $mapEscola = self::matriculaAlunoEscolaNoFiltro($db, $city, $filters, array_keys($alunosSemNeeComRecurso));
        $tiposPorEscola = [];
        foreach ($alunosSemNeeComRecurso as $aid => $labels) {
            $eid = $mapEscola[$aid] ?? null;
            if ($eid === null) {
                continue;
            }
            foreach ($labels as $lbl) {
                $tiposPorEscola[$eid][$lbl] = true;
            }
        }

        $out = [];
        foreach ($schoolRows as $row) {
            $eid = (string) ($row['escola_id'] ?? '');
            $tipos = isset($tiposPorEscola[$eid]) ? array_keys($tiposPorEscola[$eid]) : [];
            sort($tipos, SORT_NATURAL | SORT_FLAG_CASE);
            $txt = implode('; ', array_slice($tipos, 0, 10));
            if (count($tipos) > 10) {
                $txt .= ' …';
            }
            $out[] = array_merge($row, ['tipos_recurso' => $txt]);
        }

        return $out;
    }

    /**
     * @param  list<array{nome: string, total: int}>  $catalogo
     * @return array<string, mixed>|null
     */
    public static function catalogoChart(array $catalogo): ?array
    {
        if ($catalogo === []) {
            return null;
        }

        $slice = array_slice($catalogo, 0, 20);
        $labels = array_map(static fn (array $r): string => (string) ($r['nome'] ?? '—'), $slice);
        $values = array_map(static fn (array $r): float => (float) ($r['total'] ?? 0), $slice);

        return ChartPayload::barHorizontal(
            __('Recursos de prova por tipo (catálogo)'),
            __('Registos no filtro'),
            $labels,
            $values,
        );
    }

    /**
     * @return array<int, true>
     */
    private static function alunoIdsComNee(Connection $db, City $city): array
    {
        $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
        if ($neeSub === null) {
            return [];
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');

        try {
            $ids = $db->table($aluno.' as a')
                ->whereIn('a.'.$aId, $neeSub)
                ->pluck('a.'.$aId)
                ->all();

            $out = [];
            foreach ($ids as $id) {
                $out[(int) $id] = true;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $alunoIds
     * @return array<int, string> aluno_id => escola_id
     */
    private static function matriculaAlunoEscolaNoFiltro(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $alunoIds,
    ): array {
        if ($alunoIds === []) {
            return [];
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id');
        $mEsc = (string) config('ieducar.columns.matricula.escola');

        try {
            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->whereIn('a.'.$aId, $alunoIds);
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            $q->join($escolaT.' as e', 'm.'.$mEsc, '=', 'e.'.$eId)
                ->selectRaw('a.'.$aId.' as aid')
                ->selectRaw('e.'.$eId.' as eid')
                ->distinct();

            $out = [];
            foreach ($q->get() as $row) {
                $out[(int) $row->aid] = (string) $row->eid;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
