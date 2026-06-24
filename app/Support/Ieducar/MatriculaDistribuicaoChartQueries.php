<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Distribuição de matrículas por escola, turno, curso e série (extraídas de MatriculaChartQueries).
 */
final class MatriculaDistribuicaoChartQueries
{
    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorCursoTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $spec = MatriculaChartQueries::cursoJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            ['qualified' => $curso, 'idCol' => $cId, 'nameCol' => $cName] = $spec;

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refCurso = $tc['curso'];
            if ($refCurso === '') {
                return null;
            }

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $distinct = MatriculaChartQueries::distinctMatriculaCountExpression($db);
            $q->join($curso.' as c', 't_filter.'.$refCurso, '=', 'c.'.$cId)
                ->selectRaw('c.'.$cId.' as cid')
                ->selectRaw('MAX(c.'.$cName.') as cname')
                ->selectRaw($distinct.' as cnt')
                ->groupBy('c.'.$cId)
                ->orderByDesc('cnt')
                ->limit(10);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->cname ?? '') !== '' ? $row->cname : ('#'.$row->cid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            return ChartPayload::barHorizontal(
                __('Matrículas por tipo/segmento (curso) — top 10'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Agregação por escola (turma → unidade). Reutilizado por gráficos e pelo cartão de unidades.
     *
     * @return Collection<int, object>|null
     */
    private static function matriculasPorEscolaGroupedRows(Connection $db, City $city, IeducarFilterState $filters, ?int $sqlLimit = null)
    {
        try {
            $spec = MatriculaChartQueries::escolaJoinSpec($db, $city);
            if ($spec === null) {
                return self::matriculasPorEscolaGroupedRowsEscolaIdOnly($db, $city, $filters, $sqlLimit);
            }
            ['qualified' => $escola, 'idCol' => $eId, 'nameCol' => $eName] = $spec;

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refEscola = $tc['escola'];
            if ($refEscola === '') {
                return null;
            }

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $joinSpec = EscolaTurmaJoin::joinTurmaEscolaFk($q, $db, $city, 't_filter', 'e');
            if ($joinSpec === null) {
                return self::matriculasPorEscolaGroupedRowsEscolaIdOnly($db, $city, $filters, $sqlLimit);
            }
            $eId = $joinSpec['idCol'];
            $distinct = MatriculaChartQueries::distinctMatriculaCountExpression($db);
            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw($distinct.' as cnt')
                ->groupBy('e.'.$eId)
                ->orderByDesc('cnt');
            if ($sqlLimit !== null && $sqlLimit > 0) {
                $q->limit($sqlLimit);
            }

            $rows = $q->get();

            if ($rows->isEmpty()) {
                $fallback = self::matriculasPorEscolaGroupedRowsEscolaIdOnly($db, $city, $filters, $sqlLimit);

                return ($fallback !== null && $fallback->isNotEmpty()) ? $fallback : null;
            }

            return $rows;
        } catch (QueryException|\Throwable) {
            return self::matriculasPorEscolaGroupedRowsEscolaIdOnly($db, $city, $filters, $sqlLimit);
        }
    }

    /**
     * Agregação só pelo FK de escola na turma (sem JOIN), quando o JOIN falha ou tipos divergem.
     *
     * @return Collection<int, object>|null
     */
    private static function matriculasPorEscolaGroupedRowsEscolaIdOnly(Connection $db, City $city, IeducarFilterState $filters, ?int $sqlLimit = null): ?Collection
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refEscola = $tc['escola'];
            if ($refEscola === '') {
                return null;
            }

            $grammar = $db->getQueryGrammar();
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->whereNotNull('t_filter.'.$refEscola);
            $distinct = MatriculaChartQueries::distinctMatriculaCountExpression($db);
            $q->selectRaw($tEsc.' as eid')
                ->selectRaw("'' as ename")
                ->selectRaw($distinct.' as cnt')
                ->groupBy($tEsc)
                ->orderByDesc('cnt');
            if ($sqlLimit !== null && $sqlLimit > 0) {
                $q->limit($sqlLimit);
            }

            $rows = $q->get();

            return $rows->isEmpty() ? null : $rows;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorEscolaTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $rows = self::matriculasPorEscolaGroupedRows($db, $city, $filters, 10);
        if ($rows === null) {
            return null;
        }

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) (($row->ename ?? '') !== '' ? $row->ename : ('#'.$row->eid));
            $values[] = (int) ($row->cnt ?? 0);
        }

        return ChartPayload::barHorizontal(
            __('Matrículas por escola — top 10'),
            __('Matrículas'),
            $labels,
            $values
        );
    }

    /**
     * Lista para o cartão «por unidade escolar» (sem agregar «outras»).
     *
     * @return list<array{nome: string, total: int}>|null
     */
    public static function matriculasPorUnidadesEscolaresCard(Connection $db, City $city, IeducarFilterState $filters, int $limit = 20): ?array
    {
        $rows = self::matriculasPorEscolaGroupedRows($db, $city, $filters, max(1, $limit));
        if ($rows === null) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'nome' => (string) (($row->ename ?? '') !== '' ? $row->ename : ('#'.$row->eid)),
                'total' => (int) ($row->cnt ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Matrículas por escola com nome via relatorio.get_nome_escola (PostgreSQL / Portabilis), join directo
     * matricula → escola quando existir coluna de FK na matrícula (ex.: ref_ref_cod_escola), com os mesmos filtros
     * que o resto do painel (turma + matrícula ativa).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorEscolaRelatorioDireto(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $limit = 15,
    ): ?array {
        try {
            $spec = MatriculaChartQueries::escolaJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            ['qualified' => $escola, 'idCol' => $eId, 'nameCol' => $eName] = $spec;

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_ref_cod_escola',
                'ref_cod_escola',
                'cod_escola',
            ]), $city);
            if ($mEsc === null) {
                return null;
            }

            $grammar = $db->getQueryGrammar();
            $mEscW = $grammar->wrap('m').'.'.$grammar->wrap($mEsc);
            $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->join($escola.' as e', function ($join) use ($db, $mEscW, $ePk) {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$mEscW.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$mEscW.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            });

            $q->selectRaw('e.'.$eId.' as eid');
            if ($db->getDriverName() === 'pgsql'
                && filter_var(config('ieducar.pgsql_use_relatorio_escola_nome', true), FILTER_VALIDATE_BOOLEAN)) {
                $q->addSelect($db->raw('relatorio.get_nome_escola(e.'.$eId.') as ename'));
            } else {
                $q->addSelect($db->raw('MAX(e.'.$eName.') as ename'));
            }
            $q->selectRaw('COUNT(*) as cnt')
                ->groupBy('e.'.$eId)
                ->orderByDesc('cnt')
                ->limit(max(1, $limit));

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->ename ?? '') !== '' ? $row->ename : ('#'.$row->eid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            $ano = $filters->yearFilterValue();
            $suf = $ano !== null ? ' ('.$ano.')' : ' ('.__('todos os anos').')';

            return ChartPayload::barHorizontal(
                __('Matrículas por escola').$suf,
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function turmasPorTurnoDistribuicao(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $turnoSpec = MatriculaChartQueries::turnoJoinSpec($db, $city);
            if ($turnoSpec === null) {
                return null;
            }
            ['qualified' => $turno, 'idCol' => $tnId, 'nameCol' => $tnName] = $turnoSpec;

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $turnoCol = $tc['turno'];
            if ($turnoCol === '') {
                return null;
            }
            $tId = (string) config('ieducar.columns.turma.id');

            $q = $db->table($turma.' as t');
            MatriculaChartQueries::joinTurmaAliasToTurnoCatalog($db, $q, 't', $turno, 'tn', $turnoCol, $tnId);

            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $turnoCol, $filters->turno_id);

            $q->selectRaw('tn.'.$tnId.' as tid')
                ->selectRaw('MAX(tn.'.$tnName.') as tname')
                ->selectRaw('COUNT(DISTINCT t.'.$tId.') as cnt')
                ->groupBy('tn.'.$tnId)
                ->orderByDesc('cnt')
                ->limit(12);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->tname ?? '') !== '' ? $row->tname : ('#'.$row->tid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            return ChartPayload::doughnut(__('Turmas por turno (oferta)'), $labels, $values);
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Matrículas ativas por turno (cadastro.turno).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function matriculasPorTurno(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $turnoSpec = MatriculaChartQueries::turnoJoinSpec($db, $city);
            if ($turnoSpec === null) {
                return null;
            }
            ['qualified' => $turno, 'idCol' => $tnId, 'nameCol' => $tnName] = $turnoSpec;

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $turnoCol = $tc['turno'];
            if ($turnoCol === '') {
                return null;
            }

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            MatriculaChartQueries::joinTurmaAliasToTurnoCatalog($db, $q, 't_filter', $turno, 'tn', $turnoCol, $tnId);
            $q->selectRaw('tn.'.$tnId.' as tid')
                ->selectRaw('MAX(tn.'.$tnName.') as tname')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy('tn.'.$tnId)
                ->orderByDesc('cnt')
                ->limit(12);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->tname ?? '') !== '' ? $row->tname : ('#'.$row->tid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            return ChartPayload::bar(
                __('Matrículas por turno'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Matrículas por série (conexão turma.ref_cod_serie), top 10.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorSerieTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refSerie = $tc['serie'];
            if ($refSerie === '') {
                return null;
            }

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            ['qualified' => $serie, 'idCol' => $sId, 'nameCol' => $sName] = $spec;

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->join($serie.' as s', 't_filter.'.$refSerie, '=', 's.'.$sId)
                ->selectRaw('s.'.$sId.' as sid')
                ->selectRaw('MAX(s.'.$sName.') as sname')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy('s.'.$sId)
                ->orderByDesc('cnt')
                ->limit(10);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->sname ?? '') !== '' ? $row->sname : ('#'.$row->sid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            return ChartPayload::barHorizontal(
                __('Matrículas por série (ano) — top 10'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Distribuição por sexo em cadastro.pessoa (se a coluna existir).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function matriculasPorSexo(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $kpiDenominator = null
    ): ?array {
        try {
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);
            if (! IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
                return null;
            }

            $sexoCol = (string) config('ieducar.columns.pessoa.sexo');
            $sexoCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                $sexoCol,
                'sexo',
                'tipo_sexo',
                'genero',
                'idsexo',
                'sex',
                'sg_sexo',
                'cod_sexo',
                'cd_sexo',
                'ind_sexo',
                'ref_cod_sexo',
                'tp_sexo',
                'sexo_id',
            ]), $city);

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            if (! IeducarColumnInspector::tableExists($db, $aluno, $city)) {
                return null;
            }

            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                (string) config('ieducar.columns.pessoa.id'),
                'idpes',
                'id',
                'cod_pessoa',
            ]), $city);

            if ($aPessoa === null || $pId === null) {
                return null;
            }

            $fisicaTable = null;
            $fisicaSexoCol = null;
            $fisicaLinkCol = null;
            if ($sexoCol === null) {
                foreach (self::fisicaTableCandidates($city) as $cand) {
                    if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                        continue;
                    }
                    $fisicaSexoCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'sexo',
                        'tipo_sexo',
                        'genero',
                        'idsexo',
                    ], $city);
                    $fisicaLinkCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'idpes',
                        'ref_idpes',
                    ], $city);
                    if ($fisicaSexoCol !== null && $fisicaLinkCol !== null) {
                        $fisicaTable = $cand;
                        break;
                    }
                }
            }

            if ($sexoCol === null && ($fisicaTable === null || $fisicaSexoCol === null || $fisicaLinkCol === null)) {
                return null;
            }

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId);

            if ($fisicaTable !== null) {
                $q->leftJoin($fisicaTable.' as pf', 'p.'.$pId, '=', 'pf.'.$fisicaLinkCol)
                    ->selectRaw('pf.'.$fisicaSexoCol.' as sx')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('pf.'.$fisicaSexoCol);
            } else {
                $q->selectRaw('p.'.$sexoCol.' as sx')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('p.'.$sexoCol);
            }

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

            $rows = $q->orderByDesc('c')->limit(16)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = self::labelSexo($row->sx ?? null);
                $values[] = (int) ($row->c ?? 0);
            }

            $chart = ChartPayload::doughnut(__('Matrículas por sexo (registro administrativo — Educacenso)'), $labels, $values);

            $den = $kpiDenominator ?? self::totalMatriculasAtivasFiltradas($db, $city, $filters);

            return ChartPayload::withKpiStudentTotal(
                $chart,
                $den,
                __('Total de matrículas no filtro (denominador)')
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Tabelas candidatas onde o sexo pode estar (iEducar: cadastro.fisica ligada a pessoa por idpes).
     *
     * @return list<string>
     */
    private static function fisicaTableCandidates(City $city): array
    {
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $sch = IeducarSchema::effectiveSchema($city);
        $main = $sch !== '' ? $sch.'.fisica' : 'fisica';

        return array_values(array_unique(array_filter([$cad, $main, 'cadastro.fisica'])));
    }

    private static function labelSexo(mixed $v): string
    {
        if ($v === null || $v === '') {
            return __('Não informado');
        }

        $k = strtoupper(trim((string) $v));

        return match ($k) {
            'M', '1' => __('Masculino'),
            'F', '2' => __('Feminino'),
            default => (string) $v,
        };
    }
}
