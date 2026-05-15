<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Rotinas de detecção de inconsistências de cadastro com impacto em financiamento / Censo / VAAR.
 */
final class DiscrepanciesQueries
{
    /**
     * Matrículas ativas sem cor/raça declarada no cadastro (fisica_raca ou pessoa), por escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasSemRacaPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaRacaCadastroQueries::matriculasSemRacaDeclaradaPorEscola($db, $city, $filters);
    }

    public static function baseMatriculaComTurmaPublic(Connection $db, City $city, IeducarFilterState $filters): Builder
    {
        return self::baseMatriculaComTurma($db, $city, $filters);
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function aggregatePorEscolaPublic(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        Builder $q,
        string $distinctMat,
    ): array {
        return self::aggregatePorEscola($db, $city, $filters, $q, $distinctMat);
    }

    /**
     * Matrículas de alunos com NEE (cadastro) sem turma identificada como AEE, por escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function neeSemTurmaAeePorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $turma = IeducarSchema::resolveTable('turma', $city);
            $curso = IeducarSchema::resolveTable('curso', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, ['nm_turma', (string) config('ieducar.columns.turma.name')], $city) ?? 'nm_turma';
            $cName = IeducarColumnInspector::firstExistingColumn($db, $curso, ['nm_curso', (string) config('ieducar.columns.curso.name')], $city) ?? 'nm_curso';
            $cId = (string) config('ieducar.columns.curso.id');
            $grammar = $db->getQueryGrammar();

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '') {
                return [];
            }

            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

            if ($tc['curso'] !== '') {
                $q->leftJoin($curso.' as c_nee', 't_filter.'.$tc['curso'], '=', 'c_nee.'.$cId);
            }

            if (! self::applyNeeAlunoExists($q, $db, $city, 'a', $aId)) {
                return [];
            }

            $rows = $q->select([
                'm.'.$mId.' as mid',
                't_filter.'.$tName.' as nm_turma',
                $tc['curso'] !== '' ? 'c_nee.'.$cName.' as nm_curso' : $db->raw("'' as nm_curso"),
            ])->get();

            $byMidEscola = [];
            foreach ($rows as $row) {
                $t = strtolower((string) ($row->nm_turma ?? ''));
                $c = strtolower((string) ($row->nm_curso ?? ''));
                if (self::matchAeeKeywords($t.' '.$c)) {
                    continue;
                }
                $mid = (string) ($row->mid ?? '');
                if ($mid === '') {
                    continue;
                }
                $byMidEscola[$mid] = true;
            }

            if ($byMidEscola === []) {
                return [];
            }

            $mids = array_keys($byMidEscola);
            $q2 = self::baseMatriculaComTurma($db, $city, $filters)
                ->whereIn('m.'.$mId, $mids);
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            return self::aggregatePorEscola($db, $city, $filters, $q2, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Matrículas em turmas AEE (heurística) sem registo de deficiência/NEE no cadastro, por escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function turmaAeeSemCadastroNeePorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $turma = IeducarSchema::resolveTable('turma', $city);
            $curso = IeducarSchema::resolveTable('curso', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, ['nm_turma', (string) config('ieducar.columns.turma.name')], $city) ?? 'nm_turma';
            $cName = IeducarColumnInspector::firstExistingColumn($db, $curso, ['nm_curso', (string) config('ieducar.columns.curso.name')], $city) ?? 'nm_curso';
            $cId = (string) config('ieducar.columns.curso.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
            if ($tc['curso'] !== '') {
                $q->leftJoin($curso.' as c_aee', 't_filter.'.$tc['curso'], '=', 'c_aee.'.$cId);
            }

            $neeAlunos = self::alunosComNeeSubquery($db, $city);
            if ($neeAlunos === null) {
                return [];
            }
            $q->whereNotIn('a.'.$aId, $neeAlunos);

            $rows = $q->select([
                'm.'.$mId.' as mid',
                't_filter.'.$tName.' as nm_turma',
                $tc['curso'] !== '' ? 'c_aee.'.$cName.' as nm_curso' : $db->raw("'' as nm_curso"),
            ])->get();

            $midsAee = [];
            foreach ($rows as $row) {
                $t = strtolower((string) ($row->nm_turma ?? ''));
                $c = strtolower((string) ($row->nm_curso ?? ''));
                if (! self::matchAeeKeywords($t.' '.$c)) {
                    continue;
                }
                $mid = (string) ($row->mid ?? '');
                if ($mid !== '') {
                    $midsAee[$mid] = true;
                }
            }
            if ($midsAee === []) {
                return [];
            }

            $grammar = $db->getQueryGrammar();
            $q2 = self::baseMatriculaComTurma($db, $city, $filters)->whereIn('m.'.$mId, array_keys($midsAee));
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            return self::aggregatePorEscola($db, $city, $filters, $q2, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Escolas com matrículas ativas no filtro mas sem código INEP ligado.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function escolasSemInepComMatriculas(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $escolaSpec = self::escolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return [];
            }
            ['qualified' => $escolaT, 'idCol' => $eId, 'nameCol' => $eName] = $escolaSpec;

            $inepCol = trim((string) config('ieducar.columns.escola.inep', ''));
            $hasInep = $inepCol !== '' && IeducarColumnInspector::columnExists($db, $escolaT, $inepCol, $city);

            $educTable = null;
            $educCodEscola = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola', 'cod_escola');
            $educCodInep = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola_inep', 'cod_escola_inep');
            if (! $hasInep) {
                try {
                    $educTable = IeducarSchema::resolveTable('educacenso_cod_escola', $city);
                    if (! IeducarColumnInspector::tableExists($db, $educTable, $city)) {
                        $educTable = null;
                    }
                } catch (\InvalidArgumentException) {
                    $educTable = null;
                }
                if ($educTable === null) {
                    return [];
                }
            }

            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            $q = self::baseMatriculaComTurma($db, $city, $filters);
            if (DiscrepanciesAvailability::joinEscola($q, $db, $city) === null) {
                return [];
            }

            if ($hasInep) {
                $inepSql = $grammar->wrap('e').'.'.$grammar->wrap($inepCol);
                $q->where(function (Builder $w) use ($inepSql): void {
                    $w->whereNull($inepSql)->orWhere($inepSql, '')->orWhere($inepSql, 0);
                });
            } else {
                $q->leftJoin($educTable.' as edu', function ($join) use ($db, $educCodEscola, $eId, $grammar): void {
                    $lhs = $grammar->wrap('e').'.'.$grammar->wrap($eId);
                    $rhs = $grammar->wrap('edu').'.'.$grammar->wrap($educCodEscola);
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
                    } else {
                        $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
                    }
                });
                $inepEdu = $grammar->wrap('edu').'.'.$grammar->wrap($educCodInep);
                $q->where(function (Builder $w) use ($inepEdu): void {
                    $w->whereNull($inepEdu)->orWhere($inepEdu, '')->orWhere($inepEdu, 0);
                });
            }

            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw($distinctMat.' as c')
                ->groupBy('e.'.$eId)
                ->orderByDesc('c')
                ->limit(50);

            $out = [];
            foreach ($q->get() as $row) {
                $out[] = [
                    'escola_id' => (string) ($row->eid ?? ''),
                    'escola' => trim((string) ($row->ename ?? '')) ?: '—',
                    'total' => (int) ($row->c ?? 0),
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Escolas marcadas inativas na base mas com matrículas ativas no filtro.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function escolasInativasComMatriculas(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $escolaSpec = self::escolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return [];
            }
            ['qualified' => $escolaT, 'idCol' => $eId, 'nameCol' => $eName] = $escolaSpec;
            $activeCol = (string) config('ieducar.columns.escola.active', 'ativo');
            if ($activeCol === '' || ! IeducarColumnInspector::columnExists($db, $escolaT, $activeCol, $city)) {
                return [];
            }

            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';
            $q = self::baseMatriculaComTurma($db, $city, $filters);
            if (DiscrepanciesAvailability::joinEscola($q, $db, $city) === null) {
                return [];
            }
            if ($db->getDriverName() === 'pgsql') {
                $q->whereRaw('NOT ('.MatriculaAtivoFilter::pgsqlActiveExpression('e.'.$activeCol).')');
            } else {
                $q->where('e.'.$activeCol, 0);
            }

            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw($distinctMat.' as c')
                ->groupBy('e.'.$eId)
                ->orderByDesc('c')
                ->limit(50);

            $out = [];
            foreach ($q->get() as $row) {
                $out[] = [
                    'escola_id' => (string) ($row->eid ?? ''),
                    'escola' => trim((string) ($row->ename ?? '')) ?: '—',
                    'total' => (int) ($row->c ?? 0),
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Base de matrículas ativas com filtros — turma quando existir vínculo; senão ano/escola na matrícula.
     */
    private static function baseMatriculaComTurma(Connection $db, City $city, IeducarFilterState $filters): Builder
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $q = $db->table($mat.' as m');
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);

        if (DiscrepanciesAvailability::canJoinTurma($db, $city)) {
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        } else {
            $yearVal = $filters->yearFilterValue();
            $mAno = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.ano'),
                'ano',
            ]), $city);
            if ($yearVal !== null && $mAno !== null) {
                $q->where('m.'.$mAno, $yearVal);
            }
            $mEsc = DiscrepanciesAvailability::matriculaEscolaColumn($db, $city);
            if ($mEsc !== null) {
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 'm', $mEsc, $filters->escola_id);
            }
        }

        return $q;
    }

    public static function hasCorRacaCadastroPath(Connection $db, City $city): bool
    {
        return MatriculaRacaCadastroQueries::canQuery($db, $city);
    }

    public static function hasPessoaAlunoCadastroPath(Connection $db, City $city): bool
    {
        return self::resolvePessoaAlunoJoin($db, $city) !== null;
    }

    /**
     * @return ?array{qualified: string, idCol: string, nameCol: string}
     */
    public static function escolaJoinSpecPublic(Connection $db, City $city): ?array
    {
        return self::escolaJoinSpec($db, $city);
    }

    /**
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    private static function aggregatePorEscola(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        Builder $q,
        string $distinctMat
    ): array {
        $escolaSpec = DiscrepanciesAvailability::joinEscola($q, $db, $city);
        if ($escolaSpec === null) {
            return [];
        }
        $eId = $escolaSpec['idCol'];
        $eName = $escolaSpec['nameCol'];

        $rows = $q->selectRaw('e.'.$eId.' as eid')
            ->selectRaw('MAX(e.'.$eName.') as ename')
            ->selectRaw($distinctMat.' as c')
            ->groupBy('e.'.$eId)
            ->orderByDesc('c')
            ->limit(50)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'escola_id' => (string) ($row->eid ?? ''),
                'escola' => trim((string) ($row->ename ?? '')) ?: '—',
                'total' => (int) ($row->c ?? 0),
            ];
        }

        return $out;
    }

  /**
     * @return ?array{qualified: string, idpesCol: string, racaFkCol: string}
     */
    private static function fisicaRacaPivotSpec(Connection $db, City $city): ?array
    {
        $candidates = [];
        try {
            $candidates[] = IeducarSchema::resolveTable('fisica_raca', $city);
        } catch (\InvalidArgumentException) {
        }
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica_raca';
        $candidates[] = $cad;

        foreach ($candidates as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }
            $idpesCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, ['ref_idpes', 'idpes'], $city);
            $racaFkCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, ['ref_cod_raca', 'cod_raca'], $city);
            if ($idpesCol !== null && $racaFkCol !== null) {
                return ['qualified' => $qualified, 'idpesCol' => $idpesCol, 'racaFkCol' => $racaFkCol];
            }
        }

        return null;
    }

    /**
     * @return ?array{qualified: string, idCol: string, nameCol: string}
     */
    private static function escolaJoinSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('escola', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }
        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, [
            (string) config('ieducar.columns.escola.id'),
            'cod_escola',
        ], $city);
        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, [
            (string) config('ieducar.columns.escola.name'),
            'nome',
            'nm_escola',
        ], $city);
        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol, 'nameCol' => $nameCol];
    }

    private static function applyNeeAlunoExists(Builder $q, Connection $db, City $city, string $alunoAlias, string $aIdCol): bool
    {
        $sub = self::alunosComNeeSubquery($db, $city);
        if ($sub === null) {
            return false;
        }
        $q->whereIn($alunoAlias.'.'.$aIdCol, $sub);

        return true;
    }

    /**
     * @return \Closure(Builder): void|null
     */
    private static function alunosComNeeSubquery(Connection $db, City $city): ?\Closure
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, ['ref_idpes', 'idpes'], $city);

        $fisica = self::resolveFisicaDeficienciaTable($db, $city);
        if ($fisica !== null && $aIdpes !== null) {
            return static function ($sub) use ($fisica, $aIdpes, $aluno, $aId): void {
                $sub->select('a_nee.'.$aId)
                    ->from($aluno.' as a_nee')
                    ->whereExists(function ($ex) use ($fisica, $aIdpes, $aId): void {
                        $ex->from($fisica['table'].' as fd')
                            ->whereColumn('fd.'.$fisica['idpes_col'], 'a_nee.'.$aIdpes);
                    });
            };
        }

        $adTable = IeducarColumnInspector::findQualifiedTableByNames($db, ['aluno_deficiencia', 'aluno_deficiencias'], $city);
        if ($adTable === null) {
            return null;
        }
        $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, ['ref_cod_aluno', 'cod_aluno'], $city);
        if ($adAluno === null) {
            return null;
        }

        return static function ($sub) use ($adTable, $adAluno): void {
            $sub->from($adTable)->select($adAluno)->distinct();
        };
    }

    /**
     * @return ?array{table: string, idpes_col: string}
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
            if ($idpes !== null) {
                return ['table' => $t, 'idpes_col' => $idpes];
            }
        }

        return null;
    }

    /**
     * Matrículas ativas sem sexo em pessoa/fisica, por escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasSemSexoPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $join = self::resolvePessoaAlunoJoin($db, $city);
            if ($join === null) {
                return [];
            }
            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->join($join['aluno'].' as a', 'm.'.$join['mAluno'], '=', 'a.'.$join['aId'])
                ->join($join['pessoa'].' as p', 'a.'.$join['aPessoa'], '=', 'p.'.$join['pId']);

            $grammar = $db->getQueryGrammar();
            if ($join['sexoCol'] !== null) {
                $sx = $grammar->wrap('p').'.'.$grammar->wrap($join['sexoCol']);
                $q->where(function (Builder $w) use ($sx): void {
                    $w->whereNull($sx)->orWhere($sx, '')->orWhere($sx, 0);
                });
            } elseif ($join['fisicaTable'] !== null && $join['fisicaSexoCol'] !== null) {
                $q->leftJoin($join['fisicaTable'].' as pf', 'p.'.$join['pId'], '=', 'pf.'.$join['fisicaLinkCol']);
                $fsx = $grammar->wrap('pf').'.'.$grammar->wrap($join['fisicaSexoCol']);
                $q->where(function (Builder $w) use ($fsx): void {
                    $w->whereNull($fsx)->orWhere($fsx, '')->orWhere($fsx, 0);
                });
            } else {
                return [];
            }

            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($join['mId']).')';

            return self::aggregatePorEscola($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Matrículas sem data de nascimento em fisica/pessoa.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasSemDataNascimentoPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $join = self::resolvePessoaAlunoJoin($db, $city);
            if ($join === null) {
                return [];
            }
            $nascCol = self::resolveDataNascimentoColumn($db, $city, $join);
            if ($nascCol === null) {
                return [];
            }

            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->join($join['aluno'].' as a', 'm.'.$join['mAluno'], '=', 'a.'.$join['aId']);

            if ($nascCol['source'] === 'fisica') {
                $q->join($join['pessoa'].' as p', 'a.'.$join['aPessoa'], '=', 'p.'.$join['pId'])
                    ->leftJoin($nascCol['table'].' as pf_n', 'p.'.$join['pId'], '=', 'pf_n.'.$nascCol['linkCol']);
                $dn = $db->getQueryGrammar()->wrap('pf_n').'.'.$db->getQueryGrammar()->wrap($nascCol['col']);
            } else {
                $q->join($join['pessoa'].' as p', 'a.'.$join['aPessoa'], '=', 'p.'.$join['pId']);
                $dn = $db->getQueryGrammar()->wrap('p').'.'.$db->getQueryGrammar()->wrap($nascCol['col']);
            }

            $q->where(function (Builder $w) use ($dn): void {
                $w->whereNull($dn)->orWhere($dn, '');
            });

            $grammar = $db->getQueryGrammar();
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($join['mId']).')';

            return self::aggregatePorEscola($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Aluno com mais de uma matrícula ativa no filtro, por escola (contagem de matrículas em excesso).
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculaDuplicadaAtivoPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mId = (string) config('ieducar.columns.matricula.id');
            $grammar = $db->getQueryGrammar();

            $dupAlunos = self::baseMatriculaComTurma($db, $city, $filters)
                ->selectRaw('m.'.$mAluno.' as aid')
                ->selectRaw('COUNT(DISTINCT m.'.$mId.') as c')
                ->groupBy('m.'.$mAluno)
                ->havingRaw('COUNT(DISTINCT m.'.$mId.') > 1');

            $ids = [];
            foreach ($dupAlunos->get() as $row) {
                $ids[] = $row->aid;
            }
            if ($ids === []) {
                return [];
            }

            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->whereIn('m.'.$mAluno, $ids);
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            return self::aggregatePorEscola($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Matrículas ativas cuja situação INEP não é «em curso» (códigos em matricula_indicadores.situacao_inep_como_ativa).
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasSituacaoNaoEmCursoPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $spec = MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city);
            if ($spec === null) {
                return [];
            }
            $ativas = config('ieducar.matricula_indicadores.situacao_inep_como_ativa', ['1']);
            if (! is_array($ativas) || $ativas === []) {
                return [];
            }
            $ativas = array_map(static fn ($c) => (string) $c, $ativas);

            $q = self::baseMatriculaComTurma($db, $city, $filters);
            ($spec['applyJoins'])($q);
            $placeholders = implode(',', array_fill(0, count($ativas), '?'));
            $q->whereRaw('('.$spec['chaveExpr'].') NOT IN ('.$placeholders, $ativas);

            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            return self::aggregatePorEscola($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Escolas com matrículas e sem latitude/longitude na tabela escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function escolasSemGeolocalizacaoComMatriculas(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $escolaSpec = self::escolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return [];
            }
            ['qualified' => $escolaT, 'idCol' => $eId, 'nameCol' => $eName] = $escolaSpec;
            $latCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'latitude', 'lat', 'geo_lat', 'latitude_graus',
            ], $city);
            $lngCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'longitude', 'lng', 'lon', 'geo_lng', 'longitude_graus',
            ], $city);
            if ($latCol === null && $lngCol === null) {
                return [];
            }

            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';
            $q = self::baseMatriculaComTurma($db, $city, $filters);
            if (DiscrepanciesAvailability::joinEscola($q, $db, $city) === null) {
                return [];
            }

            $q->where(function (Builder $w) use ($grammar, $latCol, $lngCol): void {
                if ($latCol !== null) {
                    $la = $grammar->wrap('e').'.'.$grammar->wrap($latCol);
                    $w->where(function (Builder $x) use ($la): void {
                        $x->whereNull($la)->orWhere($la, '')->orWhere($la, 0);
                    });
                }
                if ($lngCol !== null) {
                    $ln = $grammar->wrap('e').'.'.$grammar->wrap($lngCol);
                    $method = $latCol !== null ? 'orWhere' : 'where';
                    $w->{$method}(function (Builder $x) use ($ln): void {
                        $x->whereNull($ln)->orWhere($ln, '')->orWhere($ln, 0);
                    });
                }
            });

            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw($distinctMat.' as c')
                ->groupBy('e.'.$eId)
                ->orderByDesc('c')
                ->limit(50);

            $out = [];
            foreach ($q->get() as $row) {
                $out[] = [
                    'escola_id' => (string) ($row->eid ?? ''),
                    'escola' => trim((string) ($row->ename ?? '')) ?: '—',
                    'total' => (int) ($row->c ?? 0),
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Contagem distinta de matrículas com NEE cadastrado.
     */
    public static function countMatriculasNeeDistintas(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $q = self::baseMatriculaComTurma($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
            if (! self::applyNeeAlunoExists($q, $db, $city, 'a', $aId)) {
                return 0;
            }
            $grammar = $db->getQueryGrammar();

            return (int) ($q->selectRaw('COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c')
                ->value('c') ?? 0);
        } catch (QueryException|\Throwable) {
            return 0;
        }
    }

    /**
     * Estimativa de matrículas NEE possivelmente não declaradas (benchmark configurável).
     *
     * @return ?array{escola_id: string, escola: string, total: int, meta: array<string, mixed>}
     */
    public static function neeSubnotificacaoEstimativaPorRede(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $totalMat
    ): ?array {
        $minMat = (int) config('ieducar.discrepancies.min_matriculas_nee_benchmark', 80);
        if ($totalMat < $minMat) {
            return null;
        }
        $benchmark = (float) config('ieducar.discrepancies.nee_benchmark_pct_min', 1.5);
        $neeCount = self::countMatriculasNeeDistintas($db, $city, $filters);
        $pct = $totalMat > 0 ? 100.0 * $neeCount / $totalMat : 0.0;
        if ($pct >= $benchmark) {
            return null;
        }
        $gap = (int) ceil($totalMat * ($benchmark - $pct) / 100.0);
        if ($gap <= 0) {
            return null;
        }

        return [
            'escola_id' => '',
            'escola' => __('Rede municipal (estimativa)'),
            'total' => $gap,
            'meta' => [
                'nee_matriculas' => $neeCount,
                'pct_atual' => round($pct, 2),
                'benchmark_pct' => $benchmark,
            ],
        ];
    }

    /**
     * @return ?array{
     *   aluno: string,
     *   pessoa: string,
     *   mAluno: string,
     *   mId: string,
     *   aId: string,
     *   aPessoa: string,
     *   pId: string,
     *   sexoCol: ?string,
     *   fisicaTable: ?string,
     *   fisicaSexoCol: ?string,
     *   fisicaLinkCol: ?string
     * }
     */
    private static function resolvePessoaAlunoJoin(Connection $db, City $city): ?array
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $pessoa = IeducarSchema::resolveTable('pessoa', $city);
        if (! IeducarColumnInspector::tableExists($db, $aluno, $city)
            || ! IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
            return null;
        }
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mId = (string) config('ieducar.columns.matricula.id');
        $aId = (string) config('ieducar.columns.aluno.id');
        $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, [
            (string) config('ieducar.columns.aluno.pessoa'),
            'ref_cod_pessoa', 'ref_idpes', 'idpes',
        ], $city);
        $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            (string) config('ieducar.columns.pessoa.id'),
            'idpes', 'id', 'cod_pessoa',
        ], $city);
        if ($aPessoa === null || $pId === null) {
            return null;
        }

        $sexoCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
            (string) config('ieducar.columns.pessoa.sexo'),
            'sexo', 'tipo_sexo', 'ref_cod_sexo', 'cod_sexo',
        ]), $city);

        $fisicaTable = null;
        $fisicaSexoCol = null;
        $fisicaLinkCol = null;
        if ($sexoCol === null) {
            $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
            $candidates = [$cad];
            try {
                $candidates[] = IeducarSchema::resolveTable('fisica', $city);
            } catch (\InvalidArgumentException) {
            }
            foreach ($candidates as $cand) {
                if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                    continue;
                }
                $fisicaSexoCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                    'sexo', 'tipo_sexo', 'genero',
                ], $city);
                $fisicaLinkCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                    'idpes', 'ref_idpes',
                ], $city);
                if ($fisicaSexoCol !== null && $fisicaLinkCol !== null) {
                    $fisicaTable = $cand;
                    break;
                }
            }
        }

        return [
            'aluno' => $aluno,
            'pessoa' => $pessoa,
            'mAluno' => $mAluno,
            'mId' => $mId,
            'aId' => $aId,
            'aPessoa' => $aPessoa,
            'pId' => $pId,
            'sexoCol' => $sexoCol,
            'fisicaTable' => $fisicaTable,
            'fisicaSexoCol' => $fisicaSexoCol,
            'fisicaLinkCol' => $fisicaLinkCol,
        ];
    }

    /**
     * @param  array<string, mixed>  $join
     * @return ?array{source: string, col: string, table?: string, linkCol?: string}
     */
    private static function resolveDataNascimentoColumn(Connection $db, City $city, array $join): ?array
    {
        $pessoa = (string) ($join['pessoa'] ?? '');
        $nascPessoa = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            'data_nasc', 'data_nascimento', 'dt_nascimento',
        ], $city);
        if ($nascPessoa !== null) {
            return ['source' => 'pessoa', 'col' => $nascPessoa];
        }

        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $candidates = [$cad];
        try {
            $candidates[] = IeducarSchema::resolveTable('fisica', $city);
        } catch (\InvalidArgumentException) {
        }
        foreach ($candidates as $cand) {
            if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                continue;
            }
            $nasc = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                'data_nasc', 'data_nascimento', 'dt_nascimento',
            ], $city);
            $link = IeducarColumnInspector::firstExistingColumn($db, $cand, ['idpes', 'ref_idpes'], $city);
            if ($nasc !== null && $link !== null) {
                return ['source' => 'fisica', 'col' => $nasc, 'table' => $cand, 'linkCol' => $link];
            }
        }

        return null;
    }

    private static function matchAeeKeywords(string $haystack): bool
    {
        $words = config('ieducar.inclusion.aee_keywords');
        if (! is_array($words) || $words === []) {
            return false;
        }
        $h = mb_strtolower($haystack);
        foreach ($words as $w) {
            $w = trim((string) $w);
            if ($w !== '' && str_contains($h, mb_strtolower($w))) {
                return true;
            }
        }

        return false;
    }
}
