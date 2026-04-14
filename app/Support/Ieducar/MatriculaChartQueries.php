<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Gráficos reutilizáveis (matrícula × turma × curso/escola/turno) para o painel analítico.
 */
final class MatriculaChartQueries
{
    /**
     * Contagem de matrículas ativas (mesma lógica da visão geral: junta turma quando há ano ou recortes dimensionais).
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
                || $filters->escola_id !== null
                || $filters->curso_id !== null
                || $filters->turno_id !== null;

            if (! $needsTurma) {
                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);

                return (int) $q->count();
            }

            $turma = IeducarSchema::resolveTable('turma', $city);
            $tId = (string) config('ieducar.columns.turma.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $usePivot = MatriculaTurmaJoin::usePivotTable($db, $city);

            if ($usePivot) {
                $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
                $q = $db->table($mat.' as m')
                    ->join($mt.' as mt', 'm.'.$mId, '=', 'mt.'.$mtMat)
                    ->join($turma.' as t', 'mt.'.$mtTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                if ($mtAtivo !== '') {
                    MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo, $city);
                }
            } else {
                $q = $db->table($mat.' as m')->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            }

            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

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
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['year'] === '') {
                return [];
            }

            $q = $db->table($turma);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null) {
                $q->where($tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['turno'], $filters->turno_id);

            $rows = $q->select($tc['year'])
                ->whereNotNull($tc['year'])
                ->distinct()
                ->orderByDesc($tc['year'])
                ->limit(max(1, $limit))
                ->pluck($tc['year']);

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
            __('Matrículas ativas'),
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

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $tq = $db->table($turma.' as t');
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $tq->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['turno'], $filters->turno_id);

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
            $turnoSpec = self::turnoJoinSpec($db, $city);
            if ($turnoSpec === null) {
                return null;
            }
            ['qualified' => $turno, 'idCol' => $tnId, 'nameCol' => $tnName] = $turnoSpec;

            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            if ($maxCol === '' || ! IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $turnoCol = $tc['turno'];
            if ($turnoCol === '') {
                return null;
            }

            $q = $db->table($turma.' as t');
            self::joinTurmaAliasToTurnoCatalog($db, $q, 't', $turno, 'tn', $turnoCol, $tnId);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $turnoCol, $filters->turno_id);

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
            $spec = self::cursoJoinSpec($db, $city);
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
            $spec = self::escolaJoinSpec($db, $city);
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

            $grammar = $db->getQueryGrammar();
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);
            $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->join($escola.' as e', function ($join) use ($db, $tEsc, $ePk) {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$tEsc.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            })
                ->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw('COUNT(*) as cnt')
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
            $q->selectRaw($tEsc.' as eid')
                ->selectRaw("'' as ename")
                ->selectRaw('COUNT(*) as cnt')
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
            $spec = self::escolaJoinSpec($db, $city);
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
            $turnoSpec = self::turnoJoinSpec($db, $city);
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
            self::joinTurmaAliasToTurnoCatalog($db, $q, 't', $turno, 'tn', $turnoCol, $tnId);

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
            $turnoSpec = self::turnoJoinSpec($db, $city);
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
            self::joinTurmaAliasToTurnoCatalog($db, $q, 't_filter', $turno, 'tn', $turnoCol, $tnId);
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
     * Matrículas por série (ligação turma.ref_cod_serie), top 10.
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

            $spec = self::serieJoinSpec($db, $city);
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
    public static function matriculasPorSexo(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
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

            return ChartPayload::doughnut(__('Matrículas por sexo (registo administrativo — Educacenso)'), $labels, $values);
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

    /**
     * Contagem de matrículas ativas por turma (para vagas).
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
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
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
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($por === 'escola' && $tc['escola'] === '') {
                return null;
            }
            if ($por === 'curso' && $tc['curso'] === '') {
                return null;
            }

            $q = $db->table($turma.' as t');
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

            $selectCols = ['t.'.$tId.' as tid', 't.'.$maxCol.' as cap'];
            if ($por === 'escola') {
                $selectCols[] = 't.'.$tc['escola'].' as eid';
            } else {
                $selectCols[] = 't.'.$tc['curso'].' as cid';
            }
            $turmaRows = $q->select($selectCols)->get();
            if ($turmaRows->isEmpty()) {
                return null;
            }

            $counts = self::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            $capAgg = [];
            foreach ($turmaRows as $row) {
                $tid = (string) ($row->tid ?? '');
                $cap = (int) ($row->cap ?? 0);
                $en = $counts[$tid] ?? 0;
                $vac = max(0, $cap - $en);
                $key = $por === 'escola'
                    ? (string) ($row->eid ?? '')
                    : (string) ($row->cid ?? '');
                if ($key === '') {
                    continue;
                }
                $agg[$key] = ($agg[$key] ?? 0) + $vac;
                $capAgg[$key] = ($capAgg[$key] ?? 0) + max(0, $cap);
            }

            if ($agg === []) {
                return null;
            }

            $items = [];
            foreach ($agg as $id => $v) {
                $items[] = [
                    'id' => $id,
                    'v' => $v,
                    'cap' => (int) ($capAgg[$id] ?? 0),
                ];
            }
            $anyPositive = false;
            foreach ($items as $it) {
                if (($it['v'] ?? 0) > 0) {
                    $anyPositive = true;
                    break;
                }
            }
            if ($anyPositive) {
                $items = array_values(array_filter($items, static fn (array $x): bool => ($x['v'] ?? 0) > 0));
                usort($items, fn ($a, $b) => $b['v'] <=> $a['v']);
            } else {
                usort($items, fn ($a, $b) => $b['cap'] <=> $a['cap']);
                $items = array_slice($items, 0, 40);
            }

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
                $values[] = (float) $it['v'];
            }

            $title = $por === 'escola'
                ? __('Vagas ociosas por escola')
                : __('Vagas em aberto por segmento (curso)');

            $payload = ChartPayload::barHorizontal(
                $title,
                __('Vagas'),
                $labels,
                $values
            );
            if ($por === 'escola') {
                $payload['subtitle'] = $anyPositive
                    ? __(
                        'Por turma: capacidade declarada (máx. de alunos) menos matrículas ativas, respeitando os filtros; valores somados por escola. Só aparecem escolas com vagas ociosas > 0.'
                    )
                    : __(
                        'Não há vagas ociosas no filtro (turmas cheias ou capacidade não declarada). O gráfico mostra as escolas com maior capacidade declarada nas turmas, com valor 0 de vagas — confira capacidade e matrículas na base.'
                    );
            }

            return $payload;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Resumo para KPIs: total de matrículas ativas, turmas distintas e, se existir max_aluno, taxa média de ocupação.
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
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

                return $q;
            };

            $out['matriculas'] = (int) $base()->count();
            $rowT = $base()->selectRaw('COUNT(DISTINCT t_filter.'.$tId.') as c')->first();
            $out['turmas_distintas'] = (int) ($rowT->c ?? 0);

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = (string) config('ieducar.columns.turma.max_alunos');
            if ($maxCol !== '' && IeducarColumnInspector::columnExists($db, $turma, $maxCol, $city)) {
                $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
                $tq = $db->table($turma.' as t');
                $yearVal = $filters->yearFilterValue();
                if ($yearVal !== null && $tc['year'] !== '') {
                    $tq->where('t.'.$tc['year'], $yearVal);
                }
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['escola'], $filters->escola_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['curso'], $filters->curso_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($tq, $db, 't', $tc['turno'], $filters->turno_id);
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
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refCurso = $tc['curso'];
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
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
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
     * Todas as séries com matrícula ativa, ordenadas por etapa (INEP) quando a coluna existir.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorSerieEducacensoCompleto(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refSerie = $tc['serie'];
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
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
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
     * Todos os cursos (segmentos) com total de matrículas ativas.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function matriculasPorCursoEducacensoCompleto(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refCurso = $tc['curso'];
            if ($refCurso === '') {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $curso = IeducarSchema::resolveTable('curso', $city);
            $cId = (string) config('ieducar.columns.curso.id');
            $cName = (string) config('ieducar.columns.curso.name');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
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
        $rows = self::matriculasPorEscolaGroupedRows($db, $city, $filters, 250);
        if ($rows === null) {
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
    }

    /**
     * Distorção idade/série (PostgreSQL): contagem de matrículas em distorção por unidade escolar —
     * matricula → turma → escola; aluno → cadastro.fisica (nascimento); série com idade_final / idade_ideal.
     * Idade na data de referência 1 de março do ano da matrícula; distorção se idade > limite etário da série.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function distorcaoIdadeSeriePorEscolaFisica(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
        if ($db->getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $serieT = IeducarSchema::resolveTable('serie', $city);

            $fisicaTable = self::resolveFisicaTableForDistorcao($db, $city);
            if ($fisicaTable === null
                || ! IeducarColumnInspector::tableExists($db, $mat, $city)
                || ! IeducarColumnInspector::tableExists($db, $aluno, $city)
                || ! IeducarColumnInspector::tableExists($db, $serieT, $city)) {
                return null;
            }

            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            $fLink = IeducarColumnInspector::firstExistingColumn($db, $fisicaTable, ['idpes', 'ref_idpes'], $city);
            $fNasc = IeducarColumnInspector::firstExistingColumn($db, $fisicaTable, [
                'data_nasc', 'data_nascimento', 'dt_nascimento',
            ], $city);

            if ($aPessoa === null || $fLink === null || $fNasc === null) {
                return null;
            }

            $spec = self::serieJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            $sPk = $spec['idCol'];
            $idadeFinal = IeducarColumnInspector::firstExistingColumn($db, $serieT, ['idade_final', 'idade_fim'], $city);
            $idadeIdeal = IeducarColumnInspector::firstExistingColumn($db, $serieT, ['idade_ideal', 'idade_ideal_max'], $city);
            if ($idadeFinal === null && $idadeIdeal === null) {
                return null;
            }

            $g = $db->getQueryGrammar();
            if ($idadeFinal !== null && $idadeIdeal !== null) {
                $limiteSql = 'COALESCE(NULLIF('.$g->wrap('s').'.'.$g->wrap($idadeFinal).', 0), '.$g->wrap('s').'.'.$g->wrap($idadeIdeal).', 99)';
            } elseif ($idadeFinal !== null) {
                $limiteSql = 'COALESCE(NULLIF('.$g->wrap('s').'.'.$g->wrap($idadeFinal).', 0), 99)';
            } else {
                $limiteSql = 'COALESCE('.$g->wrap('s').'.'.$g->wrap((string) $idadeIdeal).', 99)';
            }

            $mAno = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.ano'),
                'ano',
                'ref_ano_letivo',
                'ano_letivo',
            ]), $city);
            if ($mAno === null) {
                return null;
            }

            $mSer = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.serie'),
                'ref_ref_cod_serie',
                'ref_cod_serie',
            ]), $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '') {
                return null;
            }

            $escolaSpec = self::escolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return null;
            }
            ['qualified' => $escola, 'idCol' => $eId, 'nameCol' => $eName] = $escolaSpec;

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $q->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($fisicaTable.' as f', 'a.'.$aPessoa, '=', 'f.'.$fLink)
                ->whereNotNull('f.'.$fNasc);

            if ($mSer !== null) {
                $lhs = $g->wrap('m').'.'.$g->wrap($mSer);
                $rhs = $g->wrap('s').'.'.$g->wrap($sPk);
                $q->leftJoin($serieT.' as s', function ($join) use ($lhs, $rhs) {
                    $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
                });
            } elseif ($tc['serie'] !== '') {
                $lhs = $g->wrap('t_filter').'.'.$g->wrap($tc['serie']);
                $rhs = $g->wrap('s').'.'.$g->wrap($sPk);
                $q->leftJoin($serieT.' as s', function ($join) use ($lhs, $rhs) {
                    $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
                });
            } else {
                return null;
            }

            $anoVal = $filters->yearFilterValue();
            if ($anoVal !== null) {
                $q->where('m.'.$mAno, $anoVal);
            } else {
                $y0 = (int) date('Y');
                $q->whereBetween('m.'.$mAno, [$y0 - 4, $y0]);
            }

            $mAnoW = $g->wrap('m').'.'.$g->wrap($mAno);
            $fNascW = $g->wrap('f').'.'.$g->wrap($fNasc);

            $ageCond = 'extract(year from age(make_date(cast('.$mAnoW.' as integer), 3, 1), '.$fNascW.'))::int > ('.$limiteSql.')';

            $tEsc = $g->wrap('t_filter').'.'.$g->wrap($tc['escola']);
            $ePk = $g->wrap('e').'.'.$g->wrap($eId);
            $q->join($escola.' as e', function ($join) use ($tEsc, $ePk) {
                $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
            });

            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw('count(*) filter (where '.$ageCond.') as cnt')
                ->groupBy('e.'.$eId)
                ->havingRaw('count(*) filter (where '.$ageCond.') > 0')
                ->orderByRaw('count(*) filter (where '.$ageCond.') desc')
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

            $sub = $anoVal !== null
                ? ' ('.$anoVal.')'
                : ' ('.__('últimos 5 anos').')';

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 12, __('Outras unidades'));

            $total = array_sum(array_map(static fn ($v) => (int) $v, $values));

            $chart = ChartPayload::barHorizontal(
                __('Distorção idade/série — por unidade escolar').$sub,
                __('Matrículas com distorção'),
                $labels,
                $values
            );
            $chart['subtitle'] = __(
                'Contagem de matrículas em que a idade (referência 1 de março do ano letivo da matrícula) excede o limite etário esperado para a série (idade_final ou idade_ideal na tabela série), por escola da rede.'
            );
            $chart['footnote'] = __(
                'Barras horizontais: apenas matrículas classificadas com distorção; somatório das barras (incl. «Outras unidades»): :n.',
                ['n' => number_format($total, 0, ',', '.')]
            );
            $chart['options'] = array_merge($chart['options'] ?? [], [
                'scales' => [
                    'x' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => __('Matrículas'),
                        ],
                    ],
                ],
            ]);

            return $chart;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return string|null tabela qualificada cadastro.fisica ou schema.fisica
     */
    private static function resolveFisicaTableForDistorcao(Connection $db, City $city): ?string
    {
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $sch = IeducarSchema::effectiveSchema($city);
        foreach (array_values(array_unique(array_filter([
            $cad,
            $sch !== '' ? $sch.'.fisica' : '',
            'public.fisica',
            'cadastro.fisica',
        ]))) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Contagens para cartão / KPI (SQL custom ou critério INEP automático).
     *
     * @return array{com: int, sem: int, total: int, fonte: 'custom'|'automatico'}|null
     */
    public static function distorcaoIdadeSerieContagens(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $fromCustom = self::distorcaoIdadeSerieContagensFromCustomSql($db, $city);
        if ($fromCustom !== null) {
            return $fromCustom;
        }

        return self::distorcaoIdadeSerieContagensAutomatico($db, $city, $filters);
    }

    /**
     * @return array{com: int, sem: int, total: int, fonte: 'custom'}|null
     */
    private static function distorcaoIdadeSerieContagensFromCustomSql(Connection $db, City $city): ?array
    {
        $custom = trim((string) config('ieducar.sql.distorcao_rede_chart', ''));
        if ($custom === '') {
            return null;
        }
        try {
            $sql = IeducarSqlPlaceholders::interpolate($custom, $city);
            $rows = $db->select($sql);
            $vals = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $val = $arr['valor'] ?? $arr['value'] ?? $arr['quantidade'] ?? $arr['pct'] ?? $arr['total'] ?? null;
                if ($val === null || ! is_numeric($val)) {
                    continue;
                }
                $vals[] = (int) $val;
            }
            if (count($vals) >= 2) {
                $com = $vals[0];
                $sem = $vals[1];

                return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem, 'fonte' => 'custom'];
            }
        } catch (QueryException|\Throwable) {
        }

        return null;
    }

    /**
     * @return array{com: int, sem: int, total: int, fonte: 'automatico'}|null
     */
    private static function distorcaoIdadeSerieContagensAutomatico(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);
            $serieT = IeducarSchema::resolveTable('serie', $city);

            if (! IeducarColumnInspector::tableExists($db, $aluno, $city)
                || ! IeducarColumnInspector::tableExists($db, $pessoa, $city)
                || ! IeducarColumnInspector::tableExists($db, $serieT, $city)) {
                return null;
            }

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['year'] === '' || $tc['serie'] === '') {
                return null;
            }

            $birthCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                'data_nasc',
                'data_nascimento',
                'dt_nascimento',
                'dt_nasc',
            ]), $city);
            if ($birthCol === null) {
                return null;
            }

            $spec = self::serieJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            $sId = $spec['idCol'];
            $cfgMax = trim((string) config('ieducar.columns.serie.idade_limite_max', ''));
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $serieT, array_filter([
                $cfgMax !== '' ? $cfgMax : null,
                'idade_maxima',
                'idade_max',
                'idade_maxima_escolar',
                'idade_final',
                'idade_fim',
                'idade_ideal_max',
                'idade_maxima_ideal',
            ]), $city);
            if ($maxCol === null) {
                return null;
            }

            $grammar = $db->getQueryGrammar();
            $limiteExpr = $grammar->wrap('s').'.'.$grammar->wrap($maxCol);

            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            if ($aPessoa === null) {
                return null;
            }

            $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                (string) config('ieducar.columns.pessoa.id'),
                'idpes',
                'id',
                'cod_pessoa',
            ]), $city);
            if ($pId === null) {
                return null;
            }

            $refDateExpr = self::refDateCorteEscolarSql($db, 't_filter', $tc['year']);
            $idadeExpr = self::idadeAnosCompletosSql($db, $refDateExpr, 'p', $birthCol);
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + 2)';

            $serieJoinCol = $tc['serie'];
            $base = function () use ($db, $city, $filters, $mat, $mAtivo, $mAluno, $aluno, $aId, $aPessoa, $pessoa, $pId, $serieT, $sId, $birthCol, $serieJoinCol, $limiteExpr, $grammar) {
                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                    ->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId);
                $tSerieFk = $grammar->wrap('t_filter').'.'.$grammar->wrap($serieJoinCol);
                $sPk = $grammar->wrap('s').'.'.$grammar->wrap($sId);
                $q->join($serieT.' as s', function ($join) use ($db, $tSerieFk, $sPk) {
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$tSerieFk.')::text = ('.$sPk.')::text');
                    } else {
                        $join->whereRaw('CAST('.$tSerieFk.' AS UNSIGNED) = CAST('.$sPk.' AS UNSIGNED)');
                    }
                })
                    ->whereNotNull('p.'.$birthCol)
                    ->whereRaw('('.$limiteExpr.') IS NOT NULL');

                return $q;
            };

            $qCom = $base();
            $com = (int) $qCom->whereRaw($distorcaoCond)->count();
            $qSem = $base();
            $sem = (int) $qSem->whereRaw('NOT ('.$distorcaoCond.')')->count();

            if ($com === 0 && $sem === 0) {
                return null;
            }

            return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem, 'fonte' => 'automatico'];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Matrículas com distorção idade/série, agrupadas por série (rótulo + quantidade absoluta).
     *
     * @return array{labels: list<string>, values: list<float>}|null
     */
    private static function distorcaoComDistorsaoPorSerieAutomatico(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);
            $serieT = IeducarSchema::resolveTable('serie', $city);

            if (! IeducarColumnInspector::tableExists($db, $aluno, $city)
                || ! IeducarColumnInspector::tableExists($db, $pessoa, $city)
                || ! IeducarColumnInspector::tableExists($db, $serieT, $city)) {
                return null;
            }

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['year'] === '' || $tc['serie'] === '') {
                return null;
            }

            $birthCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                'data_nasc',
                'data_nascimento',
                'dt_nascimento',
                'dt_nasc',
            ]), $city);
            if ($birthCol === null) {
                return null;
            }

            $spec = self::serieJoinSpec($db, $city);
            if ($spec === null) {
                return null;
            }
            $sId = $spec['idCol'];
            $nameCol = $spec['nameCol'];
            $cfgMax = trim((string) config('ieducar.columns.serie.idade_limite_max', ''));
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $serieT, array_filter([
                $cfgMax !== '' ? $cfgMax : null,
                'idade_maxima',
                'idade_max',
                'idade_maxima_escolar',
                'idade_final',
                'idade_fim',
                'idade_ideal_max',
                'idade_maxima_ideal',
            ]), $city);
            if ($maxCol === null) {
                return null;
            }

            $grammar = $db->getQueryGrammar();
            $limiteExpr = $grammar->wrap('s').'.'.$grammar->wrap($maxCol);

            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            if ($aPessoa === null) {
                return null;
            }

            $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                (string) config('ieducar.columns.pessoa.id'),
                'idpes',
                'id',
                'cod_pessoa',
            ]), $city);
            if ($pId === null) {
                return null;
            }

            $refDateExpr = self::refDateCorteEscolarSql($db, 't_filter', $tc['year']);
            $idadeExpr = self::idadeAnosCompletosSql($db, $refDateExpr, 'p', $birthCol);
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + 2)';

            $serieJoinCol = $tc['serie'];
            $base = function () use ($db, $city, $filters, $mat, $mAtivo, $mAluno, $aluno, $aId, $aPessoa, $pessoa, $pId, $serieT, $sId, $birthCol, $serieJoinCol, $limiteExpr, $grammar) {
                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                    ->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId);
                $tSerieFk = $grammar->wrap('t_filter').'.'.$grammar->wrap($serieJoinCol);
                $sPk = $grammar->wrap('s').'.'.$grammar->wrap($sId);
                $q->join($serieT.' as s', function ($join) use ($db, $tSerieFk, $sPk) {
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$tSerieFk.')::text = ('.$sPk.')::text');
                    } else {
                        $join->whereRaw('CAST('.$tSerieFk.' AS UNSIGNED) = CAST('.$sPk.' AS UNSIGNED)');
                    }
                })
                    ->whereNotNull('p.'.$birthCol)
                    ->whereRaw('('.$limiteExpr.') IS NOT NULL');

                return $q;
            };

            $sidWrapped = $grammar->wrap('s').'.'.$grammar->wrap($sId);
            $snameWrapped = $grammar->wrap('s').'.'.$grammar->wrap($nameCol);

            $rows = $base()
                ->whereRaw($distorcaoCond)
                ->selectRaw($snameWrapped.' as serie_lbl')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy([$sidWrapped, $snameWrapped])
                ->orderByDesc('cnt')
                ->limit(40)
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $lbl = isset($arr['serie_lbl']) ? trim((string) $arr['serie_lbl']) : '';
                if ($lbl === '') {
                    $lbl = __('Série sem nome');
                }
                $labels[] = $lbl;
                $values[] = (float) ($arr['cnt'] ?? 0);
            }

            return ['labels' => $labels, 'values' => $values];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Distorção idade/série (rede): barras horizontais (eixo Y = ano/série) com quantidade absoluta de alunos com distorção por série.
     *
     * 1) Se existir ieducar.sql.distorcao_rede_chart, usa-se esse SQL (uma linha por categoria: label + quantidade).
     * 2) Caso contrário, agrupa-se por série com o critério INEP (idade à data de corte 31/03 > idade máxima da série + 2 anos).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function distorcaoIdadeSerieRedeChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $custom = trim((string) config('ieducar.sql.distorcao_rede_chart', ''));
        if ($custom !== '') {
            try {
                $sql = IeducarSqlPlaceholders::interpolate($custom, $city);
                $rows = $db->select($sql);
                if ($rows === []) {
                    return null;
                }
                $labels = [];
                $values = [];
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $label = $arr['label'] ?? $arr['name'] ?? $arr['categoria'] ?? $arr['rotulo'] ?? null;
                    $val = $arr['valor'] ?? $arr['value'] ?? $arr['quantidade'] ?? $arr['pct'] ?? $arr['total'] ?? null;
                    if ($label === null || $val === null) {
                        continue;
                    }
                    $labels[] = (string) $label;
                    $values[] = is_numeric($val) ? (float) $val : 0.0;
                }
                if ($labels === []) {
                    return null;
                }

                [$labels, $values, $fracNote] = self::normalizeDistorcaoDoughnutValues($labels, $values);
                [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 32, __('Outras séries'));

                $chart = ChartPayload::barHorizontal(
                    __('Distorção idade/série (rede)'),
                    __('Alunos com distorção'),
                    $labels,
                    $values
                );
                $chart['subtitle'] = __(
                    'Barras horizontais: cada linha corresponde a um ano/série (ou categoria do SQL) com a quantidade absoluta de matrículas com distorção idade/série.'
                );
                $chart['footnote'] = __(
                    'Critério INEP (rede): idade na data de corte 31/03 do ano letivo da turma > idade máxima da série + 2 anos. O SQL deve devolver contagens por linha (colunas label e valor/quantidade); proporções entre 0 e 1 são ajustadas para escala de desenho.'
                ).($fracNote !== '' ? ' '.$fracNote : '');

                return $chart;
            } catch (QueryException|\Throwable) {
                // tenta caminho automático
            }
        }

        $bySerie = self::distorcaoComDistorsaoPorSerieAutomatico($db, $city, $filters);
        if ($bySerie === null || $bySerie['labels'] === []) {
            return null;
        }

        $labels = $bySerie['labels'];
        $values = $bySerie['values'];
        [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 32, __('Outras séries'));

        $total = array_sum(array_map(static fn ($v) => (float) $v, $values));

        $chart = ChartPayload::barHorizontal(
            __('Distorção idade/série (rede)'),
            __('Alunos com distorção'),
            $labels,
            $values
        );
        $chart['subtitle'] = __(
            'Quantidade absoluta de matrículas ativas com distorção (idade à 31/03 > limite etário da série + 2 anos), por ano/série, nos filtros atuais. Total com distorção (somatório das barras): :n.',
            ['n' => number_format((int) round($total), 0, ',', '.')]
        );
        $chart['footnote'] = __(
            'Barras horizontais (categorias no eixo vertical): apenas matrículas classificadas com distorção; séries sem casos não aparecem.'
        );

        return $chart;
    }

    /**
     * SQL personalizado por vezes devolve proporções 0–1 em vez de contagens; normaliza para escala de «pesos» coerente com a rosca.
     *
     * @param  list<string>  $labels
     * @param  list<float>  $values
     * @return array{0: list<string>, 1: list<float>, 2: string}
     */
    private static function normalizeDistorcaoDoughnutValues(array $labels, array $values): array
    {
        $values = array_values($values);
        $labels = array_values($labels);
        $n = min(count($labels), count($values));
        if ($n < 2) {
            return [$labels, $values, ''];
        }
        $slice = array_slice($values, 0, $n);
        $max = max($slice);
        $sum = array_sum($slice);
        $note = '';
        if ($max <= 1.0 && $sum <= 1.0001 && $sum > 0) {
            $scaled = array_map(
                static fn (float $v) => round($v * 100.0, 6),
                array_slice($values, 0, $n)
            );
            $note = __('Valores entre 0 e 1 foram interpretados como proporções e multiplicados por 100 apenas para o desenho da rosca (as percentagens nos rótulos continuam correctas).');

            return [array_slice($labels, 0, $n), $scaled, $note];
        }

        return [array_slice($labels, 0, $n), array_slice($values, 0, $n), ''];
    }

    /**
     * Taxas de abandono (INEP 11) e evasão escolar combinada (11 + 16 remanejamento), mesmo denominador da aba Desempenho.
     *
     * @return ?array{
     *   total: int,
     *   abandono_q: int,
     *   remanejamento_q: int,
     *   evasao_q: int,
     *   abandono_pct: ?float,
     *   evasao_pct: ?float
     * }
     */
    public static function taxasAbandonoEvasaoFluxoEscolar(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
        $spec = MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city);
        if ($spec === null) {
            return null;
        }

        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            $spec['applyJoins']($q);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
            $q->selectRaw($spec['chaveExpr'].' as chave, COUNT(*) as c')
                ->groupByRaw($spec['groupByExpr']);

            $rows = $q->get();
        } catch (QueryException) {
            return null;
        }

        $counts = [];
        foreach ($rows as $row) {
            $k = self::normalizeSituacaoInepKey($row->chave);
            $counts[$k] = ($counts[$k] ?? 0) + (int) ($row->c ?? 0);
        }

        $total = array_sum($counts);
        if ($total <= 0) {
            return [
                'total' => 0,
                'abandono_q' => 0,
                'remanejamento_q' => 0,
                'evasao_q' => 0,
                'abandono_pct' => null,
                'evasao_pct' => null,
            ];
        }

        $aband = $counts['11'] ?? 0;
        $rem = $counts['16'] ?? 0;
        $ev = $aband + $rem;

        return [
            'total' => $total,
            'abandono_q' => $aband,
            'remanejamento_q' => $rem,
            'evasao_q' => $ev,
            'abandono_pct' => round(100.0 * $aband / $total, 1),
            'evasao_pct' => round(100.0 * $ev / $total, 1),
        ];
    }

    private static function normalizeSituacaoInepKey(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_float($v)) {
            return (string) (int) round($v);
        }
        if (is_int($v)) {
            return (string) $v;
        }
        $s = trim((string) $v);
        if ($s === 't' || strcasecmp($s, 'true') === 0) {
            return '1';
        }
        if ($s === 'f' || strcasecmp($s, 'false') === 0) {
            return '0';
        }
        if (is_numeric($s)) {
            return (string) (int) $s;
        }

        return $s;
    }

    /**
     * Data de corte escolar 31/03 do ano letivo da turma (expressão SQL).
     */
    private static function refDateCorteEscolarSql(Connection $db, string $turmaAlias, string $yearCol): string
    {
        $y = $turmaAlias.'.'.$yearCol;
        if ($db->getDriverName() === 'pgsql') {
            return 'make_date(CAST('.$y.' AS integer), 3, 31)';
        }

        return 'STR_TO_DATE(CONCAT('.$y.", '-03-31'), '%Y-%m-%d')";
    }

    /**
     * Idade em anos completos na data de referência (expressão SQL).
     */
    private static function idadeAnosCompletosSql(Connection $db, string $refDateExpr, string $pessoaAlias, string $birthCol): string
    {
        $b = $pessoaAlias.'.'.$birthCol;
        if ($db->getDriverName() === 'pgsql') {
            return 'CAST(EXTRACT(YEAR FROM AGE(('.$refDateExpr.')::date, ('.$b.')::date)) AS integer)';
        }

        return 'TIMESTAMPDIFF(YEAR, '.$b.', '.$refDateExpr.')';
    }

    /**
     * Colunas reais na tabela escola para JOIN (nome vs nm_escola, etc.).
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function escolaJoinSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('escola', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.escola.id'),
            'cod_escola',
            'id',
        ]), $city);
        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.escola.name'),
            'nome',
            'nm_escola',
            'fantasia',
            'razao_social',
            'sigla',
        ]), $city);

        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol, 'nameCol' => $nameCol];
    }

    /**
     * Colunas reais na tabela curso para JOIN.
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function cursoJoinSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('curso', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.curso.id'),
            'cod_curso',
            'id',
        ]), $city);
        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.curso.name'),
            'nm_curso',
            'nome',
            'descricao',
            'ds_curso',
        ]), $city);

        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol, 'nameCol' => $nameCol];
    }

    /**
     * Colunas reais na tabela série para JOIN.
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function serieJoinSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('serie', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.serie.id'),
            'cod_serie',
            'id',
        ]), $city);
        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.serie.name'),
            'nm_serie',
            'nome',
            'descricao',
            'ds_serie',
        ]), $city);

        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol, 'nameCol' => $nameCol];
    }

    /**
     * Tabela de turno e colunas reais (id + rótulo) para JOIN com turma — alinhado ao carregamento dos filtros (nm_turno, etc.).
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function turnoJoinSpec(Connection $db, City $city): ?array
    {
        // 1) Se existir pmieducar.turma_turno (ou equivalente) e a turma tiver FK física para esse
        // catálogo, usar sempre este JOIN — evita cair em cadastro.turno com outro espaço de IDs.
        $turma = IeducarSchema::resolveTable('turma', $city);
        $tt = IeducarSchema::resolveTable('turma_turno', $city);
        if ($tt !== '' && IeducarColumnInspector::tableExists($db, $tt, $city)) {
            $hasTurmaTurnoFk = false;
            foreach (['ref_cod_turma_turno', 'turma_turno_id'] as $col) {
                if (IeducarColumnInspector::columnExists($db, $turma, $col, $city)) {
                    $hasTurmaTurnoFk = true;
                    break;
                }
            }
            if ($hasTurmaTurnoFk) {
                $idCol = IeducarColumnInspector::firstExistingColumn($db, $tt, array_filter([
                    'id',
                    'cod_turno',
                    (string) config('ieducar.columns.turno.id'),
                ]), $city);
                $nameCol = IeducarColumnInspector::firstExistingColumn($db, $tt, array_filter([
                    'nome',
                    'nm_turno',
                    'name',
                    (string) config('ieducar.columns.turno.name'),
                ]), $city);
                if ($idCol !== null && $nameCol !== null) {
                    return [
                        'qualified' => $tt,
                        'idCol' => $idCol,
                        'nameCol' => $nameCol,
                    ];
                }
            }
        }

        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $fk = $tc['turno'];
        $preferTurmaTurnoTable = in_array($fk, ['ref_cod_turma_turno', 'turma_turno_id'], true);

        if ($preferTurmaTurnoTable) {
            if ($tt !== '' && IeducarColumnInspector::tableExists($db, $tt, $city)) {
                $idCol = IeducarColumnInspector::firstExistingColumn($db, $tt, array_filter([
                    'id',
                    'cod_turno',
                    (string) config('ieducar.columns.turno.id'),
                ]), $city);
                $nameCol = IeducarColumnInspector::firstExistingColumn($db, $tt, array_filter([
                    'nome',
                    'nm_turno',
                    'name',
                    (string) config('ieducar.columns.turno.name'),
                ]), $city);
                if ($idCol !== null && $nameCol !== null) {
                    return [
                        'qualified' => $tt,
                        'idCol' => $idCol,
                        'nameCol' => $nameCol,
                    ];
                }
            }
        }

        foreach (IeducarSchema::turnoTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }

            $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.turno.id'),
                'cod_turno',
                'id',
            ]), $city);
            $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.turno.name'),
                'nome',
                'nm_turno',
                'descricao',
            ]), $city);

            if ($idCol !== null && $nameCol !== null) {
                return [
                    'qualified' => $qualified,
                    'idCol' => $idCol,
                    'nameCol' => $nameCol,
                ];
            }
        }

        return null;
    }

    /**
     * JOIN turma.FK ↔ catálogo de turno (pmieducar.turma_turno, cadastro.turno, …) com cast para alinhar tipos int/text entre motores.
     */
    private static function joinTurmaAliasToTurnoCatalog(
        Connection $db,
        Builder $q,
        string $turmaAlias,
        string $turnoQualifiedTable,
        string $turnoAlias,
        string $turmaTurnoFkCol,
        string $turnoIdCol,
    ): void {
        $g = $db->getQueryGrammar();
        $lhs = $g->wrap($turmaAlias).'.'.$g->wrap($turmaTurnoFkCol);
        $rhs = $g->wrap($turnoAlias).'.'.$g->wrap($turnoIdCol);
        $q->join($turnoQualifiedTable.' as '.$turnoAlias, function ($join) use ($db, $lhs, $rhs) {
            if ($db->getDriverName() === 'pgsql') {
                $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
            } else {
                $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
            }
        });
    }
}
