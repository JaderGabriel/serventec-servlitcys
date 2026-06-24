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
     * Expressão SQL para contar matrículas distintas (evita duplicar linhas em matricula_turma).
     */
    public static function distinctMatriculaCountExpression(Connection $db): string
    {
        $grammar = $db->getQueryGrammar();
        $mId = (string) config('ieducar.columns.matricula.id');

        return 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';
    }

    /**
     * Base de matrículas ativas com filtros (turma quando possível; senão ano/escola na matrícula).
     */
    private static function baseMatriculasAtivasFiltradas(Connection $db, City $city, IeducarFilterState $filters): Builder
    {
        return DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters);
    }

    /**
     * Contagem de matrículas ativas distintas no recorte (matrículas realizadas no filtro).
     */
    public static function totalMatriculasAtivasFiltradas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        $scope = IeducarAnalyticsMetricsScope::resolve();
        if ($scope !== null && $scope->matches($city, $filters)) {
            return $scope->matriculasAtivas();
        }

        return self::totalMatriculasAtivasFiltradasUncached($db, $city, $filters);
    }

    public static function totalMatriculasAtivasFiltradasUncached(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        $volume = MatriculaVolumeCounts::count($db, $city, $filters);

        return $volume['matriculas'];
    }

    /**
     * @return array{matriculas: int, alunos: ?int, alunos_available: bool}
     */
    public static function volumeCounts(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaVolumeCounts::count($db, $city, $filters);
    }

    public static function totalAlunosDistintosAtivosFiltrados(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        $volume = MatriculaVolumeCounts::count($db, $city, $filters);

        return $volume['alunos_available'] ? (int) ($volume['alunos'] ?? 0) : null;
    }

    /**
     * Contagem de matrículas ativas por escola (mesma lógica que os gráficos «por escola»: join turma + opcional join escola para alinhar tipos).
     *
     * @param  list<int>  $eids
     * @return array<int, int>
     */
    public static function matriculasCountByEscolaIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        $eids = array_values(array_unique(array_map(static fn ($x) => (int) $x, array_filter($eids, static fn ($x) => (int) $x > 0))));
        if ($eids === []) {
            return [];
        }

        $out = array_fill_keys($eids, 0);
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $refEscola = $tc['escola'];
            if ($refEscola === '') {
                return array_replace($out, self::matriculasCountByEscolaIdsDirectMatriculaEscola($db, $city, $filters, $eids));
            }

            $grammar = $db->getQueryGrammar();
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $joinSpec = EscolaTurmaJoin::joinTurmaEscolaFk($q, $db, $city, 't_filter', 'e');
            if ($joinSpec !== null) {
                $eId = $joinSpec['idCol'];
                $q->whereIn('e.'.$eId, $eids);
                $distinct = self::distinctMatriculaCountExpression($db);
                $q->selectRaw('e.'.$eId.' as eid')
                    ->selectRaw($distinct.' as c')
                    ->groupBy('e.'.$eId);
            } else {
                $distinct = self::distinctMatriculaCountExpression($db);
                $q->whereIn('t_filter.'.$refEscola, $eids);
                $q->selectRaw($tEsc.' as eid')
                    ->selectRaw($distinct.' as c')
                    ->groupBy($tEsc);
            }

            foreach ($q->get() as $row) {
                $a = (array) $row;
                $eid = (int) ($a['eid'] ?? 0);
                if ($eid > 0) {
                    $out[$eid] = (int) ($a['c'] ?? 0);
                }
            }
        } catch (QueryException|\Throwable) {
            $out = array_fill_keys($eids, 0);
        }

        $needAlt = [];
        foreach ($eids as $eid) {
            if (($out[$eid] ?? 0) === 0) {
                $needAlt[] = $eid;
            }
        }
        if ($needAlt !== []) {
            $alt = self::matriculasCountByEscolaIdsDirectMatriculaEscola($db, $city, $filters, $needAlt);
            foreach ($alt as $eid => $c) {
                if ($c > 0) {
                    $out[(int) $eid] = $c;
                }
            }
        }

        return $out;
    }

    /**
     * Quando a turma não liga bem à escola, tenta contar pela FK escola na própria matrícula (bases Portabilis / variantes).
     *
     * @param  list<int>  $eids
     * @return array<int, int>
     */
    private static function matriculasCountByEscolaIdsDirectMatriculaEscola(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        $eids = array_values(array_unique(array_map(static fn ($x) => (int) $x, array_filter($eids, static fn ($x) => (int) $x > 0))));
        if ($eids === []) {
            return [];
        }
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_ref_cod_escola',
                'ref_cod_escola',
                'cod_escola',
            ]), $city);
            if ($mEsc === null) {
                return [];
            }
            $mAno = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.ano'),
                'ano',
            ]), $city);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            if ($mAno !== null && $filters->yearFilterValue() !== null) {
                $q->where('m.'.$mAno, $filters->yearFilterValue());
            }
            $q->whereIn('m.'.$mEsc, $eids);
            $distinct = self::distinctMatriculaCountExpression($db);
            $q->selectRaw('m.'.$mEsc.' as eid')
                ->selectRaw($distinct.' as c')
                ->groupBy('m.'.$mEsc);

            $out = [];
            foreach ($q->get() as $row) {
                $a = (array) $row;
                $out[(int) ($a['eid'] ?? 0)] = (int) ($a['c'] ?? 0);
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
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
     * Linha temporal de matrículas por ano (janela em torno do ano selecionado ou últimos anos quando «todos»).
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
        return MatriculaVagasChartQueries::redeVagasResumoKpis($db, $city, $filters);
    }

    public static function chartRedeOfertaResumoVisaoGeral(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaVagasChartQueries::chartRedeOfertaResumoVisaoGeral($db, $city, $filters);
    }

    public static function vagasOciosasPorTurno(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaVagasChartQueries::vagasOciosasPorTurno($db, $city, $filters);
    }

    public static function matriculaCountByTurma(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaVagasChartQueries::matriculaCountByTurma($db, $city, $filters);
    }

    public static function capacidadeEVagasPorEscolaIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        return MatriculaVagasChartQueries::capacidadeEVagasPorEscolaIds($db, $city, $filters, $eids);
    }

    public static function metricasOfertaPorEscolaSegmentoIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        return MatriculaVagasChartQueries::metricasOfertaPorEscolaSegmentoIds($db, $city, $filters, $eids);
    }

    public static function vagasAbertasPorCurso(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaVagasChartQueries::vagasAbertasPorCurso($db, $city, $filters);
    }

    public static function vagasAbertasPorEscola(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaVagasChartQueries::vagasAbertasPorEscola($db, $city, $filters);
    }

    public static function matriculasPorCursoTop(Connection $db, City $city, IeducarFilterState $filters)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorCursoTop($db, $city, $filters);
    }

    public static function matriculasPorEscolaTop(Connection $db, City $city, IeducarFilterState $filters)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorEscolaTop($db, $city, $filters);
    }

    public static function matriculasPorUnidadesEscolaresCard(Connection $db, City $city, IeducarFilterState $filters, int $limit = 20)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorUnidadesEscolaresCard($db, $city, $filters, $limit);
    }

    public static function matriculasPorEscolaRelatorioDireto(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $limit = 15,
    ): ?array {
        return MatriculaDistribuicaoChartQueries::matriculasPorEscolaRelatorioDireto($db, $city, $filters, $limit);
    }

    public static function turmasPorTurnoDistribuicao(Connection $db, City $city, IeducarFilterState $filters)
    {
        return MatriculaDistribuicaoChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters);
    }

    public static function matriculasPorTurno(Connection $db, City $city, IeducarFilterState $filters)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorTurno($db, $city, $filters);
    }

    public static function matriculasPorSerieTop(Connection $db, City $city, IeducarFilterState $filters)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorSerieTop($db, $city, $filters);
    }

    public static function matriculasPorSexo(Connection $db, City $city, IeducarFilterState $filters, ?int $totalMatriculas = null)
    {
        return MatriculaDistribuicaoChartQueries::matriculasPorSexo($db, $city, $filters, $totalMatriculas);
    }

    public static function enrollmentResumoKpis(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = ['matriculas' => 0, 'alunos_distintos' => null, 'volume_hint' => null, 'turmas_distintas' => 0, 'ocupacao_pct' => null];
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

            $volume = self::volumeCounts($db, $city, $filters);
            $out['matriculas'] = $volume['matriculas'];
            $out['alunos_distintos'] = $volume['alunos_available'] ? (int) ($volume['alunos'] ?? 0) : null;
            $out['volume_hint'] = MatriculaVolumeCounts::presentation($volume)['hint'];
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
                $counts = MatriculaVagasChartQueries::matriculaCountByTurma($db, $city, $filters);
                $sumCap = 0;
                $sumEn = 0;
                foreach ($caps as $tid => $cap) {
                    $c = (int) $cap;
                    if ($c <= 0) {
                        continue;
                    }
                    $sumCap += $c;
                    $sumEn += min($c, MatriculaVagasChartQueries::matriculaCountForTurma($counts, $tid));
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



    public static function distorcaoIdadeSeriePorEscolaFisica(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSeriePorEscolaFisica($db, $city, $filters);
    }

    public static function distorcaoIdadeSerieContagens(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSerieContagens($db, $city, $filters);
    }

    public static function distorcaoIdadeSerieMecanismos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSerieMecanismos($db, $city, $filters);
    }

    public static function distorcaoIdadeSerieAnaliticos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSerieAnaliticos($db, $city, $filters);
    }

    public static function distorcaoIdadeSerieCartaoIndisponivelMotivo(Connection $db, City $city, IeducarFilterState $filters): ?string
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSerieCartaoIndisponivelMotivo($db, $city, $filters);
    }

    public static function distorcaoMatriculasPorEscolaRows(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return MatriculaDistorcaoChartQueries::distorcaoMatriculasPorEscolaRows($db, $city, $filters);
    }

    public static function distorcaoIdadeSeriePorTurnoCursoRedeChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSeriePorTurnoCursoRedeChart($db, $city, $filters);
    }

    public static function distorcaoIdadeSeriePorEscolaAutomaticoChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSeriePorEscolaAutomaticoChart($db, $city, $filters);
    }

    public static function distorcaoIdadeSerieRedeChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        return MatriculaDistorcaoChartQueries::distorcaoIdadeSerieRedeChart($db, $city, $filters);
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
     * Colunas reais na tabela escola para JOIN (nome vs nm_escola, etc.).
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    public static function escolaJoinSpec(Connection $db, City $city): ?array
    {
        $pk = EscolaTurmaJoin::pkSpec($db, $city);
        if ($pk === null) {
            return null;
        }

        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $pk['qualified'], array_filter([
            (string) config('ieducar.columns.escola.name'),
            'nome',
            'nm_escola',
            'fantasia',
            'razao_social',
            'sigla',
        ]), $city);

        if ($nameCol === null) {
            return null;
        }

        return [
            'qualified' => $pk['qualified'],
            'idCol' => $pk['idCol'],
            'nameCol' => $nameCol,
        ];
    }

    /**
     * Colunas reais na tabela curso para JOIN.
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    public static function cursoJoinSpec(Connection $db, City $city): ?array
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
    public static function serieJoinSpec(Connection $db, City $city): ?array
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
    public static function turnoJoinSpec(Connection $db, City $city): ?array
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
    public static function joinTurmaAliasToTurnoCatalog(
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
