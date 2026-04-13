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
     * Contagem de matrículas activas (mesma lógica da visão geral: junta turma quando há ano ou recortes dimensionais).
     */
    public static function totalMatriculasAtivasFiltradas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mTurma = (string) config('ieducar.columns.matricula.turma');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $yearVal = $filters->yearFilterValue();
            $needsTurma = $yearVal !== null
                || $filters->escola_id
                || $filters->curso_id
                || $filters->turno_id;

            if (! $needsTurma) {
                $q = $db->table($mat);
                MatriculaAtivoFilter::apply($q, $db, $mAtivo);

                return (int) $q->count();
            }

            $turma = IeducarSchema::resolveTable('turma', $city);
            $tId = (string) config('ieducar.columns.turma.id');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $usePivot = MatriculaTurmaJoin::usePivotTable($db, $city);

            if ($usePivot) {
                $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
                $q = $db->table($mat.' as m')
                    ->join($mt.' as mt', 'm.'.$mId, '=', 'mt.'.$mtMat)
                    ->join($turma.' as t', 'mt.'.$mtTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                if ($mtAtivo !== '') {
                    MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo);
                }
            } else {
                $q = $db->table($mat.' as m')->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            }

            if ($yearVal !== null && $year !== '') {
                $q->where('t.'.$year, $yearVal);
            }
            if ($filters->escola_id && $escola !== '') {
                $q->where('t.'.$escola, $filters->escola_id);
            }
            if ($filters->curso_id && $curso !== '') {
                $q->where('t.'.$curso, $filters->curso_id);
            }
            if ($filters->turno_id && $turno !== '') {
                $q->where('t.'.$turno, $filters->turno_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Anos letivos distintos na turma (desc), com os mesmos filtros dimensionais; ano letivo só aplica se estiver definido em $filters.
     *
     * @return list<int>
     */
    public static function distinctAnosLetivosTurmaDesc(Connection $db, City $city, IeducarFilterState $filters, int $limit = 16): array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');
            if ($year === '') {
                return [];
            }

            $q = $db->table($turma);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null) {
                $q->where($year, $yearVal);
            }
            if ($filters->escola_id !== null && $escola !== '') {
                $q->where($escola, $filters->escola_id);
            }
            if ($filters->curso_id !== null && $curso !== '') {
                $q->where($curso, $filters->curso_id);
            }
            if ($filters->turno_id !== null && $turno !== '') {
                $q->where($turno, $filters->turno_id);
            }

            $rows = $q->select($year)
                ->whereNotNull($year)
                ->distinct()
                ->orderByDesc($year)
                ->limit(max(1, $limit))
                ->pluck($year);

            $out = [];
            foreach ($rows as $v) {
                $out[] = (int) $v;
            }

            return $out;
        } catch (QueryException|\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Linha temporal de matrículas por ano (janela em torno do ano seleccionado ou últimos anos quando «todos»).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function chartEvolucaoMatriculasPorAno(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        if (! $filters->hasYearSelected()) {
            return null;
        }

        $fDim = $filters->withAnoLetivoOverride(null);
        $anos = [];

        if ($filters->isAllSchoolYears()) {
            $desc = self::distinctAnosLetivosTurmaDesc($db, $city, $fDim, 12);
            $anos = array_slice($desc, 0, 8);
            $anos = array_reverse($anos);
        } else {
            $y0 = $filters->yearFilterValue();
            if ($y0 === null) {
                return null;
            }
            $candidate = range($y0 - 4, $y0);
            $existing = array_flip(self::distinctAnosLetivosTurmaDesc($db, $city, $fDim, 32));
            foreach ($candidate as $y) {
                if (isset($existing[$y])) {
                    $anos[] = $y;
                }
            }
        }

        if ($anos === []) {
            return null;
        }

        $labels = [];
        $values = [];
        foreach ($anos as $ano) {
            $fAno = $filters->withAnoLetivoOverride($ano);
            $n = self::totalMatriculasAtivasFiltradas($db, $city, $fAno);
            if ($n === null) {
                return null;
            }
            $labels[] = (string) $ano;
            $values[] = (float) $n;
        }

        return ChartPayload::line(
            __('Evolução de matrículas por ano letivo (comparativo)'),
            __('Matrículas activas'),
            $labels,
            $values
        );
    }

    /**
     * Resumo de oferta: capacidade declarada, ocupação e vagas ociosas na rede.
     *
     * @return array{
     *   capacidade_total: int,
     *   matriculas: int,
     *   vagas_ociosas: int,
     *   taxa_ociosidade_pct: ?float,
     *   turmas_com_capacidade: int
     * }
     */
    public static function redeVagasResumoKpis(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = [
            'capacidade_total' => 0,
            'matriculas' => 0,
            'vagas_ociosas' => 0,
            'taxa_ociosidade_pct' => null,
            'turmas_com_capacidade' => 0,
        ];

        try {
            $resumo = self::enrollmentResumoKpis($db, $city, $filters);
            $out['matriculas'] = $resumo['matriculas'];

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            $tId = (string) config('ieducar.columns.turma.id');
            if ($maxCol === '' || ! IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                return $out;
            }

            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $tq = $db->table($turma.' as t');
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $year !== '') {
                $tq->where('t.'.$year, $yearVal);
            }
            if ($filters->escola_id !== null && $escola !== '') {
                $tq->where('t.'.$escola, $filters->escola_id);
            }
            if ($filters->curso_id !== null && $curso !== '') {
                $tq->where('t.'.$curso, $filters->curso_id);
            }
            if ($filters->turno_id !== null && $turno !== '') {
                $tq->where('t.'.$turno, $filters->turno_id);
            }

            $caps = $tq->pluck('t.'.$maxCol, 't.'.$tId);
            $counts = self::matriculaCountByTurma($db, $city, $filters);
            $vacant = 0;
            $capSum = 0;
            $nTurmas = 0;
            foreach ($caps as $tid => $cap) {
                $c = (int) $cap;
                if ($c <= 0) {
                    continue;
                }
                $nTurmas++;
                $capSum += $c;
                $en = min($c, $counts[(string) $tid] ?? 0);
                $vacant += max(0, $c - $en);
            }
            $out['capacidade_total'] = $capSum;
            $out['vagas_ociosas'] = $vacant;
            $out['turmas_com_capacidade'] = $nTurmas;
            if ($capSum > 0) {
                $out['taxa_ociosidade_pct'] = round(100.0 * $vacant / $capSum, 1);
            }
        } catch (QueryException|\Throwable) {
        }

        return $out;
    }

    /**
     * Vagas ociosas (capacidade − matrículas) agregadas por turno.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function vagasOciosasPorTurno(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $turno = IeducarSchema::resolveTable('turno', $city);
            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            if ($maxCol === '' || ! IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turnoCol = (string) config('ieducar.columns.turma.turno');
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

            $turmaRows = $q->select([
                't.'.$tId.' as tid',
                't.'.$maxCol.' as cap',
                'tn.'.$tnId.' as turno_id',
            ])->get();
            if ($turmaRows->isEmpty()) {
                return null;
            }

            $counts = self::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            foreach ($turmaRows as $row) {
                $tid = (string) ($row->tid ?? '');
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $en = min($cap, $counts[$tid] ?? 0);
                $vac = max(0, $cap - $en);
                if ($vac === 0) {
                    continue;
                }
                $kid = (string) ($row->turno_id ?? '');
                if ($kid === '') {
                    continue;
                }
                $agg[$kid] = ($agg[$kid] ?? 0) + $vac;
            }

            if ($agg === []) {
                return null;
            }

            $items = [];
            foreach ($agg as $id => $v) {
                $items[] = ['id' => $id, 'v' => $v];
            }
            usort($items, fn ($a, $b) => $b['v'] <=> $a['v']);

            $labels = [];
            $values = [];
            foreach ($items as $it) {
                $name = $db->table($turno)->where($tnId, $it['id'])->value($tnName);
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$it['id']);
                $values[] = $it['v'];
            }

            return ChartPayload::barHorizontal(
                __('Vagas ociosas por turno (capacidade − matrículas)'),
                __('Vagas ociosas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

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
            $sexoCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                $sexoCol,
                'sexo',
                'tipo_sexo',
                'genero',
                'idsexo',
            ]), $city);
            if ($sexoCol === null) {
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

    /**
     * Contagem de matrículas activas por turma (para vagas).
     *
     * @return array<string, int> cod_turma => quantidade
     */
    public static function matriculaCountByTurma(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tId = (string) config('ieducar.columns.turma.id');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->selectRaw('t_filter.'.$tId.' as tid')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('t_filter.'.$tId);

            $out = [];
            foreach ($q->get() as $row) {
                $out[(string) ($row->tid ?? '')] = (int) ($row->c ?? 0);
            }

            return $out;
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * Soma de vagas em aberto (capacidade − matrículas) por segmento (curso).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function vagasAbertasPorCurso(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return self::vagasAbertasAgrupadas($db, $city, $filters, 'curso');
    }

    /**
     * Soma de vagas em aberto por escola.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function vagasAbertasPorEscola(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return self::vagasAbertasAgrupadas($db, $city, $filters, 'escola');
    }

    /**
     * @param  'curso'|'escola'  $por
     */
    private static function vagasAbertasAgrupadas(Connection $db, City $city, IeducarFilterState $filters, string $por): ?array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            if ($maxCol === '' || ! IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $q = $db->table($turma.' as t');
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
            if ($filters->turno_id !== null && $turno !== '') {
                $q->where('t.'.$turno, $filters->turno_id);
            }

            $turmaRows = $q->select(['t.'.$tId.' as tid', 't.'.$maxCol.' as cap', 't.'.$escola.' as eid', 't.'.$curso.' as cid'])->get();
            if ($turmaRows->isEmpty()) {
                return null;
            }

            $counts = self::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            foreach ($turmaRows as $row) {
                $tid = (string) ($row->tid ?? '');
                $cap = (int) ($row->cap ?? 0);
                $en = $counts[$tid] ?? 0;
                $vac = max(0, $cap - $en);
                if ($vac === 0) {
                    continue;
                }
                $key = $por === 'escola'
                    ? (string) ($row->eid ?? '')
                    : (string) ($row->cid ?? '');
                if ($key === '') {
                    continue;
                }
                $agg[$key] = ($agg[$key] ?? 0) + $vac;
            }

            if ($agg === []) {
                return null;
            }

            $items = [];
            foreach ($agg as $id => $v) {
                $items[] = ['id' => $id, 'v' => $v];
            }
            usort($items, fn ($a, $b) => $b['v'] <=> $a['v']);

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $cursoT = IeducarSchema::resolveTable('curso', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');
            $cId = (string) config('ieducar.columns.curso.id');
            $cName = (string) config('ieducar.columns.curso.name');

            $labels = [];
            $values = [];
            foreach ($items as $it) {
                if ($por === 'escola') {
                    $name = $db->table($escolaT)->where($eId, $it['id'])->value($eName);
                } else {
                    $name = $db->table($cursoT)->where($cId, $it['id'])->value($cName);
                }
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$it['id']);
                $values[] = $it['v'];
            }

            $title = $por === 'escola'
                ? __('Vagas em aberto por escola (capacidade − matrículas)')
                : __('Vagas em aberto por segmento (curso)');

            return ChartPayload::barHorizontal(
                $title,
                __('Vagas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Resumo para KPIs: total de matrículas activas, turmas distintas e, se existir max_aluno, taxa média de ocupação.
     *
     * @return array{matriculas: int, turmas_distintas: int, ocupacao_pct: ?float}
     */
    public static function enrollmentResumoKpis(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = ['matriculas' => 0, 'turmas_distintas' => 0, 'ocupacao_pct' => null];
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tId = (string) config('ieducar.columns.turma.id');

            $base = function () use ($db, $city, $filters, $mat, $mAtivo) {
                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');

                return $q;
            };

            $out['matriculas'] = (int) $base()->count();
            $rowT = $base()->selectRaw('COUNT(DISTINCT t_filter.'.$tId.') as c')->first();
            $out['turmas_distintas'] = (int) ($rowT->c ?? 0);

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            if ($maxCol !== '' && IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                $year = (string) config('ieducar.columns.turma.year');
                $escola = (string) config('ieducar.columns.turma.escola');
                $curso = (string) config('ieducar.columns.turma.curso');
                $turno = (string) config('ieducar.columns.turma.turno');
                $tq = $db->table($turma.' as t');
                $yearVal = $filters->yearFilterValue();
                if ($yearVal !== null && $year !== '') {
                    $tq->where('t.'.$year, $yearVal);
                }
                if ($filters->escola_id !== null && $escola !== '') {
                    $tq->where('t.'.$escola, $filters->escola_id);
                }
                if ($filters->curso_id !== null && $curso !== '') {
                    $tq->where('t.'.$curso, $filters->curso_id);
                }
                if ($filters->turno_id !== null && $turno !== '') {
                    $tq->where('t.'.$turno, $filters->turno_id);
                }
                $caps = $tq->pluck($maxCol, $tId);
                $counts = self::matriculaCountByTurma($db, $city, $filters);
                $sumCap = 0;
                $sumEn = 0;
                foreach ($caps as $tid => $cap) {
                    $c = (int) $cap;
                    if ($c <= 0) {
                        continue;
                    }
                    $sumCap += $c;
                    $sumEn += min($c, $counts[(string) $tid] ?? 0);
                }
                if ($sumCap > 0) {
                    $out['ocupacao_pct'] = round(100.0 * $sumEn / $sumCap, 1);
                }
            }
        } catch (QueryException|\Throwable) {
        }

        return $out;
    }

    /**
     * Matrículas agregadas por nível de ensino (cadastro curso → nível, alinhado à hierarquia Educacenso).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorNivelEnsinoEducacenso(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $refCurso = (string) config('ieducar.columns.turma.curso');
            if ($refCurso === '') {
                return null;
            }

            $curso = IeducarSchema::resolveTable('curso', $city);
            $cId = (string) config('ieducar.columns.curso.id');
            $nivelCol = IeducarColumnInspector::firstExistingColumn($db, $curso, array_filter([
                (string) config('ieducar.columns.curso.nivel_ensino'),
                'ref_cod_nivel_ensino',
                'cod_nivel_ensino',
            ]), $city);
            if ($nivelCol === null) {
                return null;
            }

            $nivelT = IeducarSchema::resolveTable('nivel_ensino', $city);
            $nId = (string) config('ieducar.columns.nivel_ensino.id');
            $nName = (string) config('ieducar.columns.nivel_ensino.name');
            if (! IeducarColumnInspector::columnExists($db, $nivelT, $nId, $city)) {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->join($curso.' as c', 't_filter.'.$refCurso, '=', 'c.'.$cId)
                ->join($nivelT.' as ne', 'c.'.$nivelCol, '=', 'ne.'.$nId)
                ->selectRaw('ne.'.$nId.' as nid')
                ->selectRaw('MAX(ne.'.$nName.') as nname')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy('ne.'.$nId)
                ->orderBy('ne.'.$nId);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->nname ?? '') !== '' ? $row->nname : ('#'.$row->nid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 22, __('Outros níveis'));

            return ChartPayload::barHorizontal(
                __('Matrículas por nível de ensino (Educacenso / INEP)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Todas as séries com matrícula activa, ordenadas por etapa (INEP) quando a coluna existir.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorSerieEducacensoCompleto(Connection $db, City $city, IeducarFilterState $filters): ?array
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

            $sortCol = IeducarColumnInspector::firstExistingColumn($db, $serie, array_filter([
                (string) config('ieducar.columns.serie.etapa_educacenso'),
                (string) config('ieducar.columns.serie.sort'),
                'etapa_educacenso',
                'serie',
                'cod_serie',
            ]), $city);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $city, $filters, 't_filter');
            $q->join($serie.' as s', 't_filter.'.$refSerie, '=', 's.'.$sId)
                ->selectRaw('s.'.$sId.' as sid')
                ->selectRaw('MAX(s.'.$sName.') as sname')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy('s.'.$sId);

            $driver = $db->getDriverName();
            if ($sortCol !== null && $sortCol !== '') {
                if ($driver === 'pgsql') {
                    $q->orderByRaw('MAX(s.'.$sortCol.') ASC NULLS LAST');
                } else {
                    $q->orderByRaw('MAX(s.'.$sortCol.') ASC');
                }
            }
            $q->orderBy('s.'.$sId);

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

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 28, __('Outras séries'));

            return ChartPayload::barHorizontal(
                __('Matrículas por série / etapa (total por série)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Todos os cursos (segmentos) com total de matrículas activas.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorCursoEducacensoCompleto(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $refCurso = (string) config('ieducar.columns.turma.curso');
            if ($refCurso === '') {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $curso = IeducarSchema::resolveTable('curso', $city);
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
                ->orderByRaw('MAX(c.'.$cName.')');

            $rows = $q->limit(400)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->cname ?? '') !== '' ? $row->cname : ('#'.$row->cid));
                $values[] = (int) ($row->cnt ?? 0);
            }

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 26, __('Outros cursos'));

            return ChartPayload::barHorizontal(
                __('Matrículas por curso / segmento (totais)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Escolas com mais matrículas; demais agregadas em «Outras escolas» (leitura mais segura).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorEscolaComOutros(Connection $db, City $city, IeducarFilterState $filters, int $maxVisible = 12): ?array
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
                ->limit(250);

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

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, max(4, $maxVisible), __('Outras escolas'));

            return ChartPayload::barHorizontal(
                __('Matrículas por escola (principais + outras agregadas)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException) {
            return null;
        }
    }
}
