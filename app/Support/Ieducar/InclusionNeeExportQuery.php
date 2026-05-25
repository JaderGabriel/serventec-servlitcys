<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Base detalhada NEE para exportação (matrícula × aluno × designações).
 */
final class InclusionNeeExportQuery
{
    /** @return list<string> */
    public static function columnHeaders(): array
    {
        return [
            'municipio',
            'ano_letivo',
            'aluno_id',
            'matricula_id',
            'nome_aluno',
            'escola',
            'turma',
            'curso',
            'segmento',
            'designacoes_nee',
            'grupos_nee',
            'cadastro_deficiencia',
            'turma_aee',
            'criterio_nee',
            'recursos_prova_inep',
            'inconsistencia_cadastro',
        ];
    }

    /**
     * @return list<array<string, string|int>>
     */
    public static function rows(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $matriculas = self::fetchMatriculasNee($db, $city, $filters);
        if ($matriculas === []) {
            return [];
        }

        $alunoIds = array_values(array_unique(array_map(
            static fn (array $r): int => (int) ($r['aluno_id'] ?? 0),
            $matriculas,
        )));
        $alunoIds = array_values(array_filter($alunoIds, static fn (int $id): bool => $id > 0));

        $defMap = self::deficienciasPorAluno($db, $city, $alunoIds);
        $cadastroIds = self::alunosComCadastroIds($db, $city, $alunoIds);
        $recursoMap = InclusionRecursoProvaQueries::canQuery($db, $city)
            ? InclusionRecursoProvaQueries::recursoLabelsPorAlunoPublic($db, $city)
            : [];
        $inconsMap = self::inconsistenciasPorAluno($db, $city, $filters, $alunoIds);

        $ano = (string) ($filters->ano_letivo ?? '');
        $municipio = (string) $city->name;
        $out = [];

        foreach ($matriculas as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            $def = $defMap[$aid] ?? ['labels' => [], 'grupos' => []];
            $temCadastro = isset($cadastroIds[$aid]);
            $temAee = (bool) ($row['turma_aee'] ?? false);
            $labels = $def['labels'];
            $grupos = $def['grupos'];

            $out[] = [
                'municipio' => $municipio,
                'ano_letivo' => $ano,
                'aluno_id' => $aid,
                'matricula_id' => (int) ($row['matricula_id'] ?? 0),
                'nome_aluno' => (string) ($row['nome_aluno'] ?? ''),
                'escola' => (string) ($row['escola'] ?? ''),
                'turma' => (string) ($row['turma'] ?? ''),
                'curso' => (string) ($row['curso'] ?? ''),
                'segmento' => (string) ($row['segmento'] ?? ''),
                'designacoes_nee' => implode('; ', $labels),
                'grupos_nee' => implode('; ', array_unique($grupos)),
                'cadastro_deficiencia' => $temCadastro ? __('Sim') : __('Não'),
                'turma_aee' => $temAee ? __('Sim') : __('Não'),
                'criterio_nee' => self::criterioNeeLabel($temCadastro, $temAee),
                'recursos_prova_inep' => (string) ($recursoMap[$aid] ?? ''),
                'inconsistencia_cadastro' => (string) ($inconsMap[$aid] ?? ''),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $c = strcasecmp((string) $a['nome_aluno'], (string) $b['nome_aluno']);

            return $c !== 0 ? $c : ((int) $a['matricula_id'] <=> (int) $b['matricula_id']);
        });

        return $out;
    }

    /**
     * Alinhado a {@see InclusionDashboardQueries::fetchNeeMatriculasComTurmaCurso()} (total NEE no painel).
     * Escola e nome do aluno são opcionais — não abortam a exportação se o join falhar.
     *
     * @return list<array{aluno_id: int, matricula_id: int, nome_aluno: string, escola: string, turma: string, curso: string, segmento: string, turma_aee: bool}>
     */
    private static function fetchMatriculasNee(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $turma = IeducarSchema::resolveTable('turma', $city);
        $cursoT = IeducarSchema::resolveTable('curso', $city);
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $mId = (string) config('ieducar.columns.matricula.id');
        $aId = (string) config('ieducar.columns.aluno.id');
        $grammar = $db->getQueryGrammar();
        $wrapAid = $grammar->wrap('a').'.'.$grammar->wrap($aId);
        $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, ['nm_turma', (string) config('ieducar.columns.turma.name')], $city) ?? 'nm_turma';
        $cName = IeducarColumnInspector::firstExistingColumn($db, $cursoT, ['nm_curso', (string) config('ieducar.columns.curso.name')], $city) ?? 'nm_curso';
        $cId = (string) config('ieducar.columns.curso.id');
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $tCurso = $tc['curso'];

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

        InclusionDashboardQueries::applyRecorteMatriculasNeeWhere($q, $db, $city, $filters);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        InclusionDashboardQueries::applyInclusionScopeForExport($q, $db, $city, $filters);

        if ($tCurso !== '') {
            $q->leftJoin($cursoT.' as c_exp', 't_filter.'.$tCurso, '=', 'c_exp.'.$cId);
        }

        $nomeJoin = self::resolveNomeAlunoJoin($db, $city);
        if ($nomeJoin !== null) {
            $q->leftJoin(
                $nomeJoin['pessoa'].' as p_nome',
                'a.'.$nomeJoin['aPessoa'],
                '=',
                'p_nome.'.$nomeJoin['pId']
            );
            $nomeExpr = $nomeJoin['nomeExpr'];
        } else {
            $nomeExpr = 'CONCAT(\''.__('Aluno #').'\', CAST('.$wrapAid.' AS TEXT))';
        }

        $escolaExpr = "''";
        $escolaSpec = self::optionalLeftJoinEscola($q, $db, $city);
        if ($escolaSpec !== null) {
            $wrapEscolaNome = $grammar->wrap('e').'.'.$grammar->wrap($escolaSpec['nameCol']);
            $escolaExpr = 'COALESCE(NULLIF(TRIM('.$wrapEscolaNome.'), \'\'), \'\')';
        }

        $rows = $q
            ->selectRaw('a.'.$aId.' as aluno_id')
            ->selectRaw('m.'.$mId.' as matricula_id')
            ->selectRaw($nomeExpr.' as nome_aluno')
            ->selectRaw($escolaExpr.' as escola')
            ->selectRaw('t_filter.'.$tName.' as turma')
            ->selectRaw($tCurso !== '' ? 'c_exp.'.$cName.' as curso' : $db->raw("'' as curso"))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $turma = (string) ($row->turma ?? '');
            $curso = (string) ($row->curso ?? '');
            $out[] = [
                'aluno_id' => (int) ($row->aluno_id ?? 0),
                'matricula_id' => (int) ($row->matricula_id ?? 0),
                'nome_aluno' => (string) ($row->nome_aluno ?? ''),
                'escola' => (string) ($row->escola ?? ''),
                'turma' => $turma,
                'curso' => $curso,
                'segmento' => InclusionDashboardQueries::segmentLabelForExport($turma, $curso),
                'turma_aee' => self::matchAeeKeywords($turma.' '.$curso),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $alunoIds
     * @return array<int, array{labels: list<string>, grupos: list<string>}>
     */
    private static function deficienciasPorAluno(Connection $db, City $city, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }

        return InclusionDashboardQueries::deficienciasPorAlunoIdsForExport($db, $city, $alunoIds);
    }

    /**
     * @param  list<int>  $alunoIds
     * @return array<int, true>
     */
    private static function alunosComCadastroIds(Connection $db, City $city, array $alunoIds): array
    {
        $sub = InclusionDashboardQueries::alunosComCadastroNeeSubquery($db, $city);
        if ($sub === null || $alunoIds === []) {
            return [];
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $ids = $db->table($aluno.' as a')
            ->whereIn('a.'.$aId, $alunoIds)
            ->whereIn('a.'.$aId, $sub)
            ->pluck('a.'.$aId)
            ->all();

        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * @param  list<int>  $alunoIds
     * @return array<int, string>
     */
    private static function inconsistenciasPorAluno(Connection $db, City $city, IeducarFilterState $filters, array $alunoIds): array
    {
        $painel = InclusionCadastroInconsistenciasQueries::painelDetalhes($db, $city, $filters, 5000);
        $out = [];
        foreach ($painel['linhas'] ?? [] as $linha) {
            if (! is_array($linha)) {
                continue;
            }
            $aid = (int) ($linha['aluno_id'] ?? 0);
            if ($aid <= 0 || ($alunoIds !== [] && ! in_array($aid, $alunoIds, true))) {
                continue;
            }
            $tipo = (string) ($linha['tipo_label'] ?? '');
            $out[$aid] = isset($out[$aid]) ? $out[$aid].' | '.$tipo : $tipo;
        }

        return $out;
    }

    private static function criterioNeeLabel(bool $cadastro, bool $aee): string
    {
        if ($cadastro && $aee) {
            return __('Cadastro e turma AEE');
        }
        if ($cadastro) {
            return __('Cadastro de deficiência');
        }
        if ($aee) {
            return __('Turma AEE (heurística)');
        }

        return __('Outro critério NEE');
    }

    /**
     * LEFT JOIN escola — não elimina matrículas quando FK/nome não batem (diferente de joinEscola).
     *
     * @return ?array{qualified: string, idCol: string, nameCol: string}
     */
    private static function optionalLeftJoinEscola(Builder $q, Connection $db, City $city): ?array
    {
        $escolaSpec = DiscrepanciesQueries::escolaJoinSpecPublic($db, $city);
        if ($escolaSpec === null) {
            return null;
        }

        ['qualified' => $escolaT, 'idCol' => $eId] = $escolaSpec;
        $grammar = $db->getQueryGrammar();
        $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $sql = strtolower($q->toSql());
        $usesTurma = str_contains($sql, 't_filter');

        if ($usesTurma && $tc['escola'] !== '') {
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($tc['escola']);
            $q->leftJoin($escolaT.' as e', function ($join) use ($db, $tEsc, $ePk): void {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$tEsc.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            });

            return $escolaSpec;
        }

        $mEsc = DiscrepanciesAvailability::matriculaEscolaColumn($db, $city);
        if ($mEsc === null) {
            return null;
        }

        $mEscW = $grammar->wrap('m').'.'.$grammar->wrap($mEsc);
        $q->leftJoin($escolaT.' as e', function ($join) use ($db, $mEscW, $ePk): void {
            if ($db->getDriverName() === 'pgsql') {
                $join->whereRaw('('.$mEscW.')::text = ('.$ePk.')::text');
            } else {
                $join->whereRaw('CAST('.$mEscW.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
            }
        });

        return $escolaSpec;
    }

    /**
     * @return ?array{pessoa: string, aPessoa: string, pId: string, nomeExpr: string}
     */
    private static function resolveNomeAlunoJoin(Connection $db, City $city): ?array
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $pessoa = IeducarSchema::resolveTable('pessoa', $city);
        if (! IeducarColumnInspector::tableExists($db, $aluno, $city)
            || ! IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
            return null;
        }

        $aId = (string) config('ieducar.columns.aluno.id');
        $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, [
            (string) config('ieducar.columns.aluno.pessoa'),
            'ref_cod_pessoa', 'ref_idpes', 'idpes',
        ], $city);
        $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            (string) config('ieducar.columns.pessoa.id'),
            'idpes', 'id', 'cod_pessoa',
        ], $city);
        $nomeCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            (string) config('ieducar.columns.pessoa.name'),
            'nome', 'nm_pessoa',
        ], $city);
        if ($aPessoa === null || $pId === null || $nomeCol === null) {
            return null;
        }

        $grammar = $db->getQueryGrammar();
        $wrapP = $grammar->wrap('p_nome');
        $wrapNome = $wrapP.'.'.$grammar->wrap($nomeCol);
        $wrapAid = $grammar->wrap('a').'.'.$grammar->wrap($aId);
        $nomeExpr = 'COALESCE(NULLIF(TRIM('.$wrapNome.'), \'\'), CONCAT(\''.__('Aluno #').'\', CAST('.$wrapAid.' AS TEXT)))';

        return [
            'pessoa' => $pessoa,
            'aPessoa' => $aPessoa,
            'pId' => $pId,
            'nomeExpr' => $nomeExpr,
        ];
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
