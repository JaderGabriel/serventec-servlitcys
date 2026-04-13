<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Gráficos reutilizáveis (matrícula × turma × curso/escola/turno) para o painel analítico.
 */
final class MatriculaChartQueries
{
    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorCursoTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $curso = IeducarSchema::resolveTable('curso', $city);
            $refCurso = (string) config('ieducar.columns.turma.curso');
            $cId = (string) config('ieducar.columns.curso.id');
            $cName = (string) config('ieducar.columns.curso.name');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->join($curso.' as c', 't_filter.'.$refCurso, '=', 'c.'.$cId)
                ->selectRaw('c.'.$cId.' as cid')
                ->selectRaw('MAX(c.'.$cName.') as cname')
                ->selectRaw('COUNT(*) as cnt')
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
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorEscolaTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $escola = IeducarSchema::resolveTable('escola', $city);
            $refEscola = (string) config('ieducar.columns.turma.escola');
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->join($escola.' as e', 't_filter.'.$refEscola, '=', 'e.'.$eId)
                ->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy('e.'.$eId)
                ->orderByDesc('cnt')
                ->limit(10);

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

            return ChartPayload::barHorizontal(
                __('Matrículas por escola — top 10'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
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
            $turno = IeducarSchema::resolveTable('turno', $city);
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turnoCol = (string) config('ieducar.columns.turma.turno');
            $tId = (string) config('ieducar.columns.turma.id');
            $tnId = (string) config('ieducar.columns.turno.id');
            $tnName = (string) config('ieducar.columns.turno.name');

            $q = $db->table($turma.' as t')
                ->join($turno.' as tn', 't.'.$turnoCol, '=', 'tn.'.$tnId);

            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $year !== '') {
                $q->where('t.'.$year, $yearVal);
            }
            if ($filters->escola_id !== null && $escola !== '') {
                $q->where('t.'.$escola, $filters->escola_id);
            }
            if ($filters->curso_id !== null && $curso !== '') {
                $q->where('t.'.$curso, $filters->curso_id);
            }
            if ($filters->turno_id !== null && $turnoCol !== '') {
                $q->where('t.'.$turnoCol, $filters->turno_id);
            }

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
     * Matrículas activas por turno (cadastro.turno).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function matriculasPorTurno(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $turno = IeducarSchema::resolveTable('turno', $city);
            $turnoCol = (string) config('ieducar.columns.turma.turno');
            $tnId = (string) config('ieducar.columns.turno.id');
            $tnName = (string) config('ieducar.columns.turno.name');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->join($turno.' as tn', 't_filter.'.$turnoCol, '=', 'tn.'.$tnId)
                ->selectRaw('tn.'.$tnId.' as tid')
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
     * Matrículas por série (ligação turma.ref_cod_serie), top 10.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorSerieTop(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $refSerie = (string) config('ieducar.columns.turma.serie');
            if ($refSerie === '') {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $serie = IeducarSchema::resolveTable('serie', $city);
            $sId = (string) config('ieducar.columns.serie.id');
            $sName = (string) config('ieducar.columns.serie.name');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
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
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Distribuição por sexo em cadastro.pessoa (se a coluna existir).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function matriculasPorSexo(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);
            $sexoCol = (string) config('ieducar.columns.pessoa.sexo');
            if ($sexoCol === '' || ! IeducarColumnInspector::columnExists($db, $pessoa, $sexoCol)) {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = (string) config('ieducar.columns.aluno.pessoa');
            $pId = (string) config('ieducar.columns.pessoa.id');

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                ->selectRaw('p.'.$sexoCol.' as sx')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('p.'.$sexoCol);

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
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

            return ChartPayload::doughnut(__('Matrículas por sexo (cadastro)'), $labels, $values);
        } catch (QueryException) {
            return null;
        }
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
