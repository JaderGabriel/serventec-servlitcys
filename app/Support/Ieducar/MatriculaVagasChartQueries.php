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
 * Consultas de matriculavagaschartqueries (extraídas de MatriculaChartQueries).
 */
final class MatriculaVagasChartQueries
{
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
            $resumo = MatriculaChartQueries::enrollmentResumoKpis($db, $city, $filters);
            $out['matriculas'] = $resumo['matriculas'];

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
            ]), $city);
            $tId = (string) config('ieducar.columns.turma.id');
            if ($maxCol === null) {
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
            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
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
                $en = min($c, MatriculaChartQueries::matriculaCountForTurma($counts, $tid));
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
     * Barras: capacidade, matrículas e vagas ociosas — resumo para a aba Visão geral (alinhado a Rede e Oferta).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    public static function chartRedeOfertaResumoVisaoGeral(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $k = self::redeVagasResumoKpis($db, $city, $filters);
            $cap = (int) ($k['capacidade_total'] ?? 0);
            $mat = (int) ($k['matriculas'] ?? 0);
            $vac = (int) ($k['vagas_ociosas'] ?? 0);
            $taxa = $k['taxa_ociosidade_pct'] ?? null;
            $nTurmasCap = (int) ($k['turmas_com_capacidade'] ?? 0);

            $chart = ChartPayload::bar(
                __('Rede e oferta (resumo) — capacidade e vagas'),
                __('Quantidade'),
                [
                    __('Capacidade (turmas)'),
                    __('Matrículas realizadas (filtro)'),
                    __('Vagas ociosas'),
                ],
                [(float) $cap, (float) $mat, (float) $vac]
            );
            $sub = __('Turmas com capacidade declarada: :n.', ['n' => $nTurmasCap]);
            if ($taxa !== null) {
                $sub .= ' '.__('Taxa de ociosidade: :p%.', ['p' => number_format((float) $taxa, 1, ',', '.')]);
            }
            $chart['subtitle'] = $sub;
            $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'lg']);

            return $chart;
        } catch (QueryException|\Throwable) {
            return null;
        }
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
            $turnoSpec = MatriculaChartQueries::turnoJoinSpec($db, $city);
            if ($turnoSpec === null) {
                return null;
            }
            ['qualified' => $turno, 'idCol' => $tnId, 'nameCol' => $tnName] = $turnoSpec;

            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
            ]), $city);
            if ($maxCol === null) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $turnoCol = $tc['turno'];
            if ($turnoCol === '') {
                return null;
            }

            $q = $db->table($turma.' as t');
            MatriculaChartQueries::joinTurmaAliasToTurnoCatalog($db, $q, 't', $turno, 'tn', $turnoCol, $tnId);
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

            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            foreach ($turmaRows as $row) {
                $tid = (string) ($row->tid ?? '');
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $en = min($cap, MatriculaChartQueries::matriculaCountForTurma($counts, $tid));
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

            return ChartPayload::bar(
                __('Vagas ociosas por turno (capacidade − matrículas)'),
                __('Vagas ociosas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }
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
            $distinct = MatriculaChartQueries::distinctMatriculaCountExpression($db);
            $q->selectRaw('t_filter.'.$tId.' as tid')
                ->selectRaw($distinct.' as c')
                ->groupBy('t_filter.'.$tId);

            $out = [];
            foreach ($q->get() as $row) {
                $k = self::normalizeTurmaIdKey($row->tid ?? '');
                if ($k === '') {
                    continue;
                }
                $out[$k] = (int) ($row->c ?? 0);
            }

            return $out;
        } catch (QueryException) {
            return [];
        }
    }

    /** Chave estável para cruzar pluck/groupBy de turma com contagens por cod_turma. */
    private static function normalizeTurmaIdKey(mixed $tid): string
    {
        return trim((string) ($tid ?? ''));
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function matriculaCountForTurma(array $counts, mixed $tid): int
    {
        $k = self::normalizeTurmaIdKey($tid);
        if ($k === '') {
            return 0;
        }
        if (array_key_exists($k, $counts)) {
            return (int) $counts[$k];
        }
        if (ctype_digit($k)) {
            $alt = (string) (int) $k;
            if ($alt !== $k && array_key_exists($alt, $counts)) {
                return (int) $counts[$alt];
            }
        }

        return 0;
    }

    /**
     * Coluna de capacidade máxima na turma, quando existir na base.
     */
    private static function turmaMaxAlunosColumn(Connection $db, City $city): ?string
    {
        $turma = IeducarSchema::resolveTable('turma', $city);

        return IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.max_alunos'),
            'max_aluno',
            'max_alunos',
            'nr_maximo_alunos',
            'qtd_maxima_alunos',
            'qtde_max_alunos',
            'capacidade_maxima',
        ]), $city);
    }

    /**
     * Turmas com capacidade e escola (e opcionalmente curso/série) para agregações por unidade.
     *
     * @param  list<int>  $eids
     * @return list<object>
     */
    private static function turmaCapacidadeRowsForEscolaIds(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $eids,
        bool $withCursoSerie = false,
        bool $viaMatriculaEscola = false,
    ): array {
        $eids = array_values(array_unique(array_map(static fn ($x) => (int) $x, array_filter($eids, static fn ($x) => (int) $x > 0))));
        if ($eids === []) {
            return [];
        }

        $maxCol = self::turmaMaxAlunosColumn($db, $city);
        if ($maxCol === null) {
            return [];
        }

        $turma = IeducarSchema::resolveTable('turma', $city);
        $tId = (string) config('ieducar.columns.turma.id');
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

        if (! $viaMatriculaEscola && $tc['escola'] !== '') {
            $q = $db->table($turma.' as t');
            MatriculaTurmaJoin::applyYearFilterOnTurmaQuery($q, $db, $city, $filters, 't');
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

            $select = [
                't.'.$tId.' as tid',
                't.'.$maxCol.' as cap',
            ];
            if ($withCursoSerie && $tc['curso'] !== '') {
                $select[] = 't.'.$tc['curso'].' as cid';
                if ($tc['serie'] !== '') {
                    $select[] = 't.'.$tc['serie'].' as sid';
                }
            }

            $joinSpec = EscolaTurmaJoin::joinTurmaEscolaFk($q, $db, $city, 't', 'e_cap');
            if ($joinSpec !== null) {
                $ePkCol = $joinSpec['idCol'];
                $q->whereIn('e_cap.'.$ePkCol, $eids);
                $select[] = 'e_cap.'.$ePkCol.' as eid';
            } else {
                $q->whereIn('t.'.$tc['escola'], $eids);
                $select[] = 't.'.$tc['escola'].' as eid';
            }

            return $q->select($select)->get()->all();
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            (string) config('ieducar.columns.matricula.escola'),
            'ref_ref_cod_escola',
            'ref_cod_escola',
            'cod_escola',
        ]), $city);
        if ($mEsc === null) {
            return [];
        }

        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $q = $db->table($mat.' as m');
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        $g = $db->getQueryGrammar();
        $q->whereIn('m.'.$mEsc, $eids);
        $selectRaw = [
            't_filter.'.$g->wrap($tId).' as tid',
            'MAX(t_filter.'.$g->wrap($maxCol).') as cap',
            'MAX(m.'.$g->wrap($mEsc).') as eid',
        ];
        if ($withCursoSerie && $tc['curso'] !== '') {
            $selectRaw[] = 'MAX(t_filter.'.$g->wrap($tc['curso']).') as cid';
            if ($tc['serie'] !== '') {
                $selectRaw[] = 'MAX(t_filter.'.$g->wrap($tc['serie']).') as sid';
            }
        }
        $q->groupBy('t_filter.'.$g->wrap($tId))
            ->selectRaw(implode(', ', $selectRaw));

        return $q->get()->all();
    }

    /**
     * @param  list<object>  $rows
     * @param  array<string, int>  $counts
     * @param  list<int>  $eids
     * @return array<int, array{capacidade_declarada: int, vagas_disponiveis: int}>
     */
    private static function aggregateCapacidadeVagasFromTurmaRows(array $rows, array $counts, array $eids): array
    {
        $out = [];
        foreach ($eids as $eid) {
            $out[$eid] = ['capacidade_declarada' => 0, 'vagas_disponiveis' => 0];
        }

        foreach ($rows as $row) {
            $tid = self::normalizeTurmaIdKey($row->tid ?? '');
            if ($tid === '') {
                continue;
            }
            $cap = (int) ($row->cap ?? 0);
            $eid = (int) ($row->eid ?? 0);
            if ($eid <= 0 || ! isset($out[$eid])) {
                continue;
            }
            $enRaw = MatriculaChartQueries::matriculaCountForTurma($counts, $tid);
            if ($cap <= 0) {
                if ($enRaw <= 0) {
                    continue;
                }
                // Turma sem max_aluno preenchido: usa matrículas como piso de ocupação (vagas = 0).
                $cap = $enRaw;
            }
            $en = min($cap, $enRaw);
            $vac = max(0, $cap - $en);
            $out[$eid]['capacidade_declarada'] += $cap;
            $out[$eid]['vagas_disponiveis'] += $vac;
        }

        return $out;
    }

    /**
     * Capacidade declarada e vagas ociosas por escola, com a mesma regra do resumo/gráfico de vagas da rede:
     * por turma usa-se min(capacidade, matrículas ativas) e vagas ociosas = capacidade − essa ocupação (somado por escola).
     *
     * @param  list<int>  $eids
     * @return array<int, array{capacidade_declarada: ?int, vagas_disponiveis: ?int}>
     */
    public static function capacidadeEVagasPorEscolaIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        $eids = array_values(array_unique(array_map(static fn ($x) => (int) $x, array_filter($eids, static fn ($x) => (int) $x > 0))));
        if ($eids === []) {
            return [];
        }

        $nullBundle = static fn (): array => ['capacidade_declarada' => null, 'vagas_disponiveis' => null];
        $out = [];
        foreach ($eids as $eid) {
            $out[$eid] = $nullBundle();
        }

        if (self::turmaMaxAlunosColumn($db, $city) === null) {
            return $out;
        }

        try {
            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            $rows = self::turmaCapacidadeRowsForEscolaIds($db, $city, $filters, $eids);
            $agg = self::aggregateCapacidadeVagasFromTurmaRows($rows, $counts, $eids);

            $needMatriculaPath = $rows === [];
            if (! $needMatriculaPath) {
                foreach ($eids as $eid) {
                    if (($agg[$eid]['capacidade_declarada'] ?? 0) <= 0) {
                        $needMatriculaPath = true;
                        break;
                    }
                }
            }
            if ($needMatriculaPath) {
                $rowsMat = self::turmaCapacidadeRowsForEscolaIds($db, $city, $filters, $eids, false, true);
                if ($rowsMat !== []) {
                    $aggMat = self::aggregateCapacidadeVagasFromTurmaRows($rowsMat, $counts, $eids);
                    foreach ($eids as $eid) {
                        if (($agg[$eid]['capacidade_declarada'] ?? 0) <= 0 && ($aggMat[$eid]['capacidade_declarada'] ?? 0) > 0) {
                            $agg[$eid] = $aggMat[$eid];
                        }
                    }
                }
            }

            foreach ($eids as $eid) {
                $cap = (int) ($agg[$eid]['capacidade_declarada'] ?? 0);
                $vac = (int) ($agg[$eid]['vagas_disponiveis'] ?? 0);
                $out[$eid] = [
                    'capacidade_declarada' => $cap,
                    'vagas_disponiveis' => $vac,
                ];
            }

            $matByEscola = self::matriculasCountByEscolaIds($db, $city, $filters, $eids);
            foreach ($eids as $eid) {
                if ((int) ($out[$eid]['capacidade_declarada'] ?? 0) > 0) {
                    continue;
                }
                $matEsc = (int) ($matByEscola[$eid] ?? 0);
                if ($matEsc <= 0) {
                    continue;
                }
                $out[$eid] = [
                    'capacidade_declarada' => $matEsc,
                    'vagas_disponiveis' => 0,
                ];
            }
        } catch (QueryException|\Throwable) {
            foreach ($eids as $eid) {
                $out[$eid] = $nullBundle();
            }
        }

        return $out;
    }

    /**
     * Matrículas, capacidade e vagas por curso/série (segmento) em cada escola — para o mapa e modal de unidades.
     *
     * @param  list<int>  $eids
     * @return array<int, list<array{segmento: string, matriculas: int, capacidade: int, vagas: int}>>
     */
    public static function metricasOfertaPorEscolaSegmentoIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        $eids = array_values(array_unique(array_map(static fn ($x) => (int) $x, array_filter($eids, static fn ($x) => (int) $x > 0))));
        if ($eids === [] || self::turmaMaxAlunosColumn($db, $city) === null) {
            return [];
        }

        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        if ($tc['curso'] === '') {
            return [];
        }

        try {
            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            $rows = self::turmaCapacidadeRowsForEscolaIds($db, $city, $filters, $eids, true);
            if ($rows === []) {
                $rows = self::turmaCapacidadeRowsForEscolaIds($db, $city, $filters, $eids, true, true);
            }

            $cursoT = IeducarSchema::resolveTable('curso', $city);
            $cIdCol = (string) config('ieducar.columns.curso.id');
            $cNameCol = (string) config('ieducar.columns.curso.name');
            $serieT = IeducarSchema::resolveTable('serie', $city);
            $sIdCol = (string) config('ieducar.columns.serie.id');
            $sNameCol = IeducarColumnInspector::firstExistingColumn($db, $serieT, [
                (string) config('ieducar.columns.serie.name'),
                'nm_serie',
                'serie',
            ], $city);

            $cids = [];
            $sids = [];
            foreach ($rows as $row) {
                $cid = (int) ($row->cid ?? 0);
                if ($cid > 0) {
                    $cids[$cid] = true;
                }
                if (isset($row->sid)) {
                    $sid = (int) ($row->sid ?? 0);
                    if ($sid > 0) {
                        $sids[$sid] = true;
                    }
                }
            }

            $cmap = [];
            if ($cids !== []) {
                foreach ($db->table($cursoT)->whereIn($cIdCol, array_keys($cids))->get() as $cr) {
                    $cmap[(int) $cr->{$cIdCol}] = trim((string) ($cr->{$cNameCol} ?? ''));
                }
            }
            $smap = [];
            if ($sNameCol !== null && $sids !== []) {
                foreach ($db->table($serieT)->whereIn($sIdCol, array_keys($sids))->get() as $sr) {
                    $smap[(int) $sr->{$sIdCol}] = trim((string) ($sr->{$sNameCol} ?? ''));
                }
            }

            /** @var array<int, array<string, array{matriculas: int, capacidade: int, vagas: int}>> $acc */
            $acc = [];
            foreach ($rows as $row) {
                $tid = self::normalizeTurmaIdKey($row->tid ?? '');
                if ($tid === '') {
                    continue;
                }
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $eid = (int) ($row->eid ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                $enRaw = MatriculaChartQueries::matriculaCountForTurma($counts, $tid);
                $en = min($cap, $enRaw);
                $vac = max(0, $cap - $en);

                $cid = (int) ($row->cid ?? 0);
                $cn = $cid > 0 ? ($cmap[$cid] ?? ('#'.$cid)) : __('Sem curso');
                $sn = '';
                if ($tc['serie'] !== '' && isset($row->sid)) {
                    $sid = (int) ($row->sid ?? 0);
                    $sn = $sid > 0 && $sNameCol !== null ? ($smap[$sid] ?? ('#'.$sid)) : '';
                }
                $segmento = $cn;
                if ($sn !== '') {
                    $segmento = $segmento !== '' && $segmento !== __('Sem curso') ? $segmento.' — '.$sn : $sn;
                }

                if (! isset($acc[$eid])) {
                    $acc[$eid] = [];
                }
                if (! isset($acc[$eid][$segmento])) {
                    $acc[$eid][$segmento] = ['matriculas' => 0, 'capacidade' => 0, 'vagas' => 0];
                }
                $acc[$eid][$segmento]['matriculas'] += $enRaw;
                $acc[$eid][$segmento]['capacidade'] += $cap;
                $acc[$eid][$segmento]['vagas'] += $vac;
            }

            $out = [];
            foreach ($acc as $eid => $segments) {
                $list = [];
                foreach ($segments as $segmento => $vals) {
                    $list[] = [
                        'segmento' => $segmento,
                        'matriculas' => (int) $vals['matriculas'],
                        'capacidade' => (int) $vals['capacidade'],
                        'vagas' => (int) $vals['vagas'],
                    ];
                }
                usort($list, static fn (array $a, array $b): int => ($b['matriculas'] <=> $a['matriculas'])
                    ?: strcmp($a['segmento'], $b['segmento']));
                $out[$eid] = $list;
            }

            return $out;
        } catch (QueryException|\Throwable) {
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

    /** Título do gráfico principal de oferta por unidade (Rede & Oferta). */
    private static function chartTitleDistribuicaoVagasNaCidade(): string
    {
        return __('Distribuição de vagas na cidade');
    }

    /**
     * Soma de vagas em aberto por escola.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function vagasAbertasPorEscola(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        // Requisito do painel: sempre mostrar TODAS as escolas da rede no ano,
        // mesmo quando o filtro de escola estiver selecionado (não "apagar" o gráfico).
        // Mantém filtros de curso/turno, mas remove recorte por escola.
        $filtersAllSchools = new IeducarFilterState(
            ano_letivo: $filters->ano_letivo,
            escola_id: null,
            curso_id: $filters->curso_id,
            turno_id: $filters->turno_id,
        );

        $stacked = self::vagasOciosasPorEscolaCursoStacked($db, $city, $filtersAllSchools);
        if ($stacked !== null) {
            return $stacked;
        }

        $direct = self::vagasAbertasAgrupadas($db, $city, $filtersAllSchools, 'escola', [
            'include_zero' => true,
            'max_items' => 250,
        ]);
        if ($direct !== null) {
            return $direct;
        }

        $viaMatricula = self::vagasAbertasPorEscolaViaMatriculaEscola($db, $city, $filtersAllSchools);
        if ($viaMatricula !== null) {
            return $viaMatricula;
        }

        return self::vagasDistribuicaoFallbackCapacidadeEvagasPorEscola($db, $city, $filtersAllSchools);
    }

    /**
     * Escolas distintas no recorte (ano, curso, turno), sem filtrar por unidade — base para agregar vagas por escola.
     *
     * @return list<int>
     */
    private static function distinctEscolaIdsParaRecorteVagasRede(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $turma = IeducarSchema::resolveTable('turma', $city);

            if ($tc['escola'] !== '') {
                $q = $db->table($turma.' as t');
                $yearVal = $filters->yearFilterValue();
                if ($yearVal !== null && $tc['year'] !== '') {
                    $q->where('t.'.$tc['year'], $yearVal);
                }
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

                $raw = $q->distinct()->pluck('t.'.$tc['escola'])->all();
                $ids = [];
                foreach ($raw as $x) {
                    $n = (int) $x;
                    if ($n > 0) {
                        $ids[] = $n;
                    }
                }

                return array_values(array_unique($ids));
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_ref_cod_escola',
                'ref_cod_escola',
                'cod_escola',
            ]), $city);
            if ($mEsc === null) {
                return [];
            }
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $raw = $q->distinct()->pluck('m.'.$mEsc)->all();
            $ids = [];
            foreach ($raw as $x) {
                $n = (int) $x;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }

            return array_values(array_unique($ids));
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Quando agrupamentos diretos por turma falham, reutiliza capacidadeEVagasPorEscolaIds (alinhado aos KPIs).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    private static function vagasDistribuicaoFallbackCapacidadeEvagasPorEscola(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $eids = self::distinctEscolaIdsParaRecorteVagasRede($db, $city, $filters);
            if ($eids === []) {
                return null;
            }

            $bySchool = self::capacidadeEVagasPorEscolaIds($db, $city, $filters, $eids);
            if ($bySchool === []) {
                return null;
            }

            $items = [];
            foreach ($bySchool as $eid => $row) {
                $items[] = [
                    'id' => (int) $eid,
                    'v' => (int) ($row['vagas_disponiveis'] ?? 0),
                    'cap' => (int) ($row['capacidade_declarada'] ?? 0),
                ];
            }
            $anyPositive = false;
            foreach ($items as $it) {
                if (($it['v'] ?? 0) > 0) {
                    $anyPositive = true;
                    break;
                }
            }
            if (! $anyPositive) {
                // Rede cheia no recorte: ainda assim mostrar capacidade por escola (evita cartão vazio).
                $capBySchool = [];
                foreach ($items as $it) {
                    $cap = (int) ($it['cap'] ?? 0);
                    if ($cap <= 0) {
                        continue;
                    }
                    $capBySchool[(string) $it['id']] = $cap;
                }
                if ($capBySchool === []) {
                    return null;
                }
                $schoolIdsCap = array_keys($capBySchool);
                usort($schoolIdsCap, function (string $a, string $b) use ($capBySchool): int {
                    return ($capBySchool[$b] ?? 0) <=> ($capBySchool[$a] ?? 0);
                });
                $maxSchools = 250;
                if (count($schoolIdsCap) > $maxSchools) {
                    $schoolIdsCap = array_slice($schoolIdsCap, 0, $maxSchools);
                }
                $escolaT = IeducarSchema::resolveTable('escola', $city);
                $eId = (string) config('ieducar.columns.escola.id');
                $eName = (string) config('ieducar.columns.escola.name');
                $schoolNamesMap = $db->table($escolaT)->whereIn($eId, $schoolIdsCap)->pluck($eName, $eId)->all();
                $labels = [];
                $values = [];
                foreach ($schoolIdsCap as $sid) {
                    $name = $schoolNamesMap[$sid] ?? $schoolNamesMap[(string) $sid] ?? null;
                    $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$sid);
                    $values[] = (float) ($capBySchool[$sid] ?? 0);
                }
                $payload = ChartPayload::barHorizontal(
                    self::chartTitleDistribuicaoVagasNaCidade(),
                    __('Capacidade (máx. alunos)'),
                    $labels,
                    $values
                );
                $payload['subtitle'] = __(
                    'Não há vagas livres neste recorte (oferta ocupada). O gráfico mostra a capacidade máxima declarada por unidade, com a mesma regra dos indicadores — para comparar o porte da rede quando o agrupamento direto por turma não devolve vagas > 0.'
                );
                $payload['options'] = array_merge($payload['options'] ?? [], ['panelHeight' => 'xxl']);

                return $payload;
            }

            usort($items, fn ($a, $b) => ($b['v'] <=> $a['v']) ?: ($b['cap'] <=> $a['cap']));
            $items = array_values(array_filter($items, static fn (array $x): bool => ($x['v'] ?? 0) > 0));
            $maxItems = 250;
            if (count($items) > $maxItems) {
                $items = array_slice($items, 0, $maxItems);
            }

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');
            $idsForNames = array_column($items, 'id');
            $schoolNamesMap = $idsForNames === []
                ? []
                : $db->table($escolaT)->whereIn($eId, $idsForNames)->pluck($eName, $eId)->all();

            $labels = [];
            $values = [];
            foreach ($items as $it) {
                $id = $it['id'];
                $name = $schoolNamesMap[$id] ?? $schoolNamesMap[(string) $id] ?? null;
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$id);
                $values[] = (float) $it['v'];
            }

            $payload = ChartPayload::barHorizontal(
                self::chartTitleDistribuicaoVagasNaCidade(),
                __('Vagas'),
                $labels,
                $values
            );
            $payload['subtitle'] = __(
                'Distribuição por unidade com a mesma regra dos indicadores (capacidade na turma e matrículas ativas). Caminho alternativo usado quando o agrupamento direto pela turma não produz o gráfico — por exemplo chaves de turma inconsistentes entre consultas ou escola na turma vazia.'
            );
            $payload['options'] = array_merge($payload['options'] ?? [], ['panelHeight' => 'xxl']);

            return $payload;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Vagas ociosas por escola com várias séries por curso (agrupadas ou empilhadas conforme o número de cursos).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    private static function vagasOciosasPorEscolaCursoStacked(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
            ]), $city);
            if ($maxCol === null) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $semCursoKey = '__sem_curso__';
            if ($tc['curso'] === '') {
                return null;
            }

            if ($tc['escola'] !== '') {
                $q = $db->table($turma.' as t');
                $yearVal = $filters->yearFilterValue();
                if ($yearVal !== null && $tc['year'] !== '') {
                    $q->where('t.'.$tc['year'], $yearVal);
                }
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

                $turmaRows = $q->select([
                    't.'.$tId.' as tid',
                    't.'.$maxCol.' as cap',
                    't.'.$tc['escola'].' as eid',
                    't.'.$tc['curso'].' as cid',
                ])->get();
            } else {
                $mat = IeducarSchema::resolveTable('matricula', $city);
                $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                    (string) config('ieducar.columns.matricula.escola'),
                    'ref_ref_cod_escola',
                    'ref_cod_escola',
                    'cod_escola',
                ]), $city);
                if ($mEsc === null) {
                    return null;
                }

                $mAtivo = (string) config('ieducar.columns.matricula.ativo');
                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $g = $db->getQueryGrammar();
                $q->groupBy('t_filter.'.$g->wrap($tId))
                    ->selectRaw('t_filter.'.$g->wrap($tId).' as tid')
                    ->selectRaw('MAX(t_filter.'.$g->wrap($maxCol).') as cap')
                    ->selectRaw('MAX(m.'.$g->wrap($mEsc).') as eid')
                    ->selectRaw('MAX(t_filter.'.$g->wrap($tc['curso']).') as cid');

                $turmaRows = $q->get();
            }

            if ($turmaRows->isEmpty()) {
                return null;
            }

            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            /** @var array<string, array<string, int>> $matrix */
            /** @var array<string, int> $capBySchool */
            $matrix = [];
            $capBySchool = [];
            foreach ($turmaRows as $row) {
                $tid = trim((string) ($row->tid ?? ''));
                if ($tid === '') {
                    continue;
                }
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $enRaw = MatriculaChartQueries::matriculaCountForTurma($counts, $tid);
                $en = min($cap, $enRaw);
                $vac = max(0, $cap - $en);
                $eid = trim((string) ($row->eid ?? ''));
                if ($eid === '' || $eid === '0') {
                    continue;
                }
                $capBySchool[$eid] = ($capBySchool[$eid] ?? 0) + $cap;
                $cidRaw = trim((string) ($row->cid ?? ''));
                $cid = ($cidRaw === '' || $cidRaw === '0') ? $semCursoKey : $cidRaw;
                if (! isset($matrix[$eid])) {
                    $matrix[$eid] = [];
                }
                $matrix[$eid][$cid] = ($matrix[$eid][$cid] ?? 0) + $vac;
            }

            if ($matrix === []) {
                return null;
            }

            $schoolTotals = [];
            foreach ($matrix as $eid => $courses) {
                $schoolTotals[$eid] = array_sum($courses);
            }

            $anyPositive = false;
            foreach ($schoolTotals as $t) {
                if ($t > 0) {
                    $anyPositive = true;
                    break;
                }
            }
            if (! $anyPositive) {
                // Rede cheia no filtro: ainda assim mostrar distribuição por capacidade declarada (evita cartão vazio).
                if ($capBySchool === []) {
                    return null;
                }
                $schoolIdsCap = array_keys($capBySchool);
                usort($schoolIdsCap, function (string $a, string $b) use ($capBySchool): int {
                    return ($capBySchool[$b] ?? 0) <=> ($capBySchool[$a] ?? 0);
                });
                $maxSchools = 55;
                $schoolIdsCap = array_slice($schoolIdsCap, 0, $maxSchools);
                $escolaT = IeducarSchema::resolveTable('escola', $city);
                $eIdCol = (string) config('ieducar.columns.escola.id');
                $eNameCol = (string) config('ieducar.columns.escola.name');
                $schoolNamesMap = $db->table($escolaT)
                    ->whereIn($eIdCol, $schoolIdsCap)
                    ->pluck($eNameCol, $eIdCol)
                    ->all();
                $labels = [];
                $values = [];
                foreach ($schoolIdsCap as $sid) {
                    $name = $schoolNamesMap[$sid] ?? $schoolNamesMap[(string) $sid] ?? null;
                    $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$sid);
                    $values[] = (float) ($capBySchool[$sid] ?? 0);
                }
                $payload = ChartPayload::barHorizontal(
                    __('Capacidade declarada por escola (sem vagas livres no filtro)'),
                    __('Capacidade (máx. alunos)'),
                    $labels,
                    $values
                );
                $payload['subtitle'] = __(
                    'Não há vagas ociosas nas turmas deste recorte (oferta ocupada). O gráfico mostra a capacidade máxima declarada por unidade para comparar o porte da rede. Ajuste filtros ou confira max. alunos na turma e matrículas ativas.'
                );
                $payload['options'] = array_merge($payload['options'] ?? [], ['panelHeight' => 'xxl']);

                return $payload;
            }

            $schoolsWithVac = array_keys(array_filter($schoolTotals, static fn (int $v): bool => $v > 0));
            usort($schoolsWithVac, function (string $a, string $b) use ($schoolTotals): int {
                return ($schoolTotals[$b] ?? 0) <=> ($schoolTotals[$a] ?? 0);
            });
            $maxSchools = 55;
            $schoolIds = array_slice($schoolsWithVac, 0, $maxSchools);

            $courseTotals = [];
            foreach ($schoolIds as $sid) {
                foreach ($matrix[$sid] ?? [] as $cid => $v) {
                    $courseTotals[$cid] = ($courseTotals[$cid] ?? 0) + $v;
                }
            }
            arsort($courseTotals);
            $maxCourseSeries = 18;
            $topCourseIds = array_slice(array_keys($courseTotals), 0, $maxCourseSeries);
            $topSet = array_flip($topCourseIds);

            $hasOutros = false;
            foreach ($schoolIds as $sid) {
                foreach ($matrix[$sid] ?? [] as $cid => $v) {
                    if ($v > 0 && ! isset($topSet[$cid])) {
                        $hasOutros = true;
                        break 2;
                    }
                }
            }

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $cursoT = IeducarSchema::resolveTable('curso', $city);
            $eIdCol = (string) config('ieducar.columns.escola.id');
            $eNameCol = (string) config('ieducar.columns.escola.name');
            $cIdCol = (string) config('ieducar.columns.curso.id');
            $cNameCol = (string) config('ieducar.columns.curso.name');

            $schoolNamesMap = $db->table($escolaT)
                ->whereIn($eIdCol, $schoolIds)
                ->pluck($eNameCol, $eIdCol)
                ->all();
            $labels = [];
            foreach ($schoolIds as $sid) {
                $name = $schoolNamesMap[$sid] ?? $schoolNamesMap[(string) $sid] ?? null;
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$sid);
            }

            $courseIdsForNames = array_values(array_filter(
                $topCourseIds,
                static fn ($cid) => $cid !== $semCursoKey
            ));
            $courseNamesMap = $courseIdsForNames === []
                ? []
                : $db->table($cursoT)->whereIn($cIdCol, $courseIdsForNames)->pluck($cNameCol, $cIdCol)->all();

            $series = [];
            foreach ($topCourseIds as $cid) {
                $data = [];
                foreach ($schoolIds as $sid) {
                    $data[] = (float) ($matrix[$sid][$cid] ?? 0);
                }
                if ($cid === $semCursoKey) {
                    $cname = __('Sem curso');
                } else {
                    $cn = $courseNamesMap[$cid] ?? $courseNamesMap[(string) $cid] ?? null;
                    $cname = $cn !== null && (string) $cn !== '' ? (string) $cn : ('#'.$cid);
                }
                $series[] = [
                    'label' => $cname,
                    'data' => $data,
                ];
            }
            if ($hasOutros) {
                $data = [];
                foreach ($schoolIds as $sid) {
                    $sum = 0;
                    foreach ($matrix[$sid] ?? [] as $cid => $v) {
                        if (! isset($topSet[$cid])) {
                            $sum += $v;
                        }
                    }
                    $data[] = (float) $sum;
                }
                $series[] = [
                    'label' => __('Outros cursos'),
                    'data' => $data,
                ];
            }

            $seriesCount = count($series);
            $useStacked = $seriesCount > 10;

            $title = self::chartTitleDistribuicaoVagasNaCidade();
            $payload = $useStacked
                ? ChartPayload::barHorizontalStacked(
                    $title,
                    __('Vagas'),
                    $labels,
                    $series
                )
                : ChartPayload::barHorizontalGrouped(
                    $title,
                    __('Vagas'),
                    $labels,
                    $series
                );

            $payload['subtitle'] = $useStacked
                ? __(
                    'Distribuição por curso em cada unidade: por turma, vagas livres = capacidade declarada menos matrículas ativas. Com muitas séries (>10 cursos/«Outros»), barras empilhadas. Ano letivo e filtros de curso/turno; até :max escolas com vagas > 0 no total.',
                    ['max' => $maxSchools]
                )
                : __(
                    'Distribuição por curso (barras agrupadas): mesma regra por turma; ano letivo e filtros de curso/turno; até :max escolas com vagas > 0 no total.',
                    ['max' => $maxSchools]
                );
            $payload['options'] = array_merge($payload['options'] ?? [], ['panelHeight' => 'xxl']);

            return $payload;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Agrega vagas por escola usando a coluna de escola na matrícula (ex.: ref_cod_escola).
     * Usado como último recurso quando o agrupamento pela turma não produz unidades (FK vazia ou inconsistente).
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    private static function vagasAbertasPorEscolaViaMatriculaEscola(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_ref_cod_escola',
                'ref_cod_escola',
                'cod_escola',
            ]), $city);
            if ($mEsc === null) {
                return null;
            }

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
            ]), $city);
            if ($maxCol === null) {
                return null;
            }

            $tId = (string) config('ieducar.columns.turma.id');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $g = $db->getQueryGrammar();
            $q->groupBy('t_filter.'.$g->wrap($tId))
                ->selectRaw('t_filter.'.$g->wrap($tId).' as tid')
                ->selectRaw('MAX(t_filter.'.$g->wrap($maxCol).') as cap')
                ->selectRaw('MAX(m.'.$g->wrap($mEsc).') as eid');

            $turmaRows = $q->get();
            if ($turmaRows->isEmpty()) {
                return null;
            }

            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            $capAgg = [];
            foreach ($turmaRows as $row) {
                $tid = trim((string) ($row->tid ?? ''));
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $enRaw = MatriculaChartQueries::matriculaCountForTurma($counts, $tid);
                $en = min($cap, $enRaw);
                $vac = max(0, $cap - $en);
                $key = trim((string) ($row->eid ?? ''));
                if ($key === '' || $key === '0') {
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
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');

            $idsForNames = array_column($items, 'id');
            $schoolNamesMap = $idsForNames === []
                ? []
                : $db->table($escolaT)->whereIn($eId, $idsForNames)->pluck($eName, $eId)->all();

            $labels = [];
            $values = [];
            foreach ($items as $it) {
                $id = $it['id'];
                $name = $schoolNamesMap[$id] ?? $schoolNamesMap[(string) $id] ?? null;
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$id);
                $values[] = (float) $it['v'];
            }

            $payload = ChartPayload::barHorizontal(
                self::chartTitleDistribuicaoVagasNaCidade(),
                __('Vagas'),
                $labels,
                $values
            );
            $payload['subtitle'] = $anyPositive
                ? __(
                    'Por turma: capacidade declarada menos matrículas ativas, somadas por escola; escola obtida pela coluna de matrícula (útil quando a turma não tem escola ou a FK não está preenchida). Só aparecem escolas com vagas ociosas > 0.'
                )
                : __(
                    'Não há vagas ociosas no filtro. O gráfico mostra escolas com maior capacidade (via matrícula → escola). Valores 0 — confira capacidade e matrículas na base.'
                );

            return $payload;
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @param  'curso'|'escola'  $por
     */
    private static function vagasAbertasAgrupadas(Connection $db, City $city, IeducarFilterState $filters, string $por, array $opts = []): ?array
    {
        try {
            $includeZero = (bool) ($opts['include_zero'] ?? false);
            $maxItems = $opts['max_items'] ?? null;
            $maxItems = is_int($maxItems) && $maxItems > 0 ? $maxItems : null;

            $turma = IeducarSchema::resolveTable('turma', $city);
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
            ]), $city);
            if ($maxCol === null) {
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

            $counts = MatriculaChartQueries::matriculaCountByTurma($db, $city, $filters);
            $agg = [];
            $capAgg = [];
            foreach ($turmaRows as $row) {
                $tid = trim((string) ($row->tid ?? ''));
                $cap = (int) ($row->cap ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $enRaw = MatriculaChartQueries::matriculaCountForTurma($counts, $tid);
                $en = min($cap, $enRaw);
                $vac = max(0, $cap - $en);
                $key = $por === 'escola'
                    ? trim((string) ($row->eid ?? ''))
                    : trim((string) ($row->cid ?? ''));
                if ($key === '' || $key === '0') {
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

            // Ordenação: vagas desc, depois capacidade desc (para desempate e para casos com 0).
            usort($items, fn ($a, $b) => ($b['v'] <=> $a['v']) ?: ($b['cap'] <=> $a['cap']));

            if (! $includeZero) {
                if ($anyPositive) {
                    $items = array_values(array_filter($items, static fn (array $x): bool => ($x['v'] ?? 0) > 0));
                } else {
                    $items = array_slice($items, 0, 40);
                }
            } elseif ($maxItems !== null) {
                $items = array_slice($items, 0, $maxItems);
            }

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $cursoT = IeducarSchema::resolveTable('curso', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');
            $cId = (string) config('ieducar.columns.curso.id');
            $cName = (string) config('ieducar.columns.curso.name');

            $idsForNames = array_column($items, 'id');
            $namesMap = [];
            if ($por === 'escola' && $idsForNames !== []) {
                $namesMap = $db->table($escolaT)->whereIn($eId, $idsForNames)->pluck($eName, $eId)->all();
            } elseif ($por !== 'escola' && $idsForNames !== []) {
                $namesMap = $db->table($cursoT)->whereIn($cId, $idsForNames)->pluck($cName, $cId)->all();
            }

            $labels = [];
            $values = [];
            foreach ($items as $it) {
                $id = $it['id'];
                $name = $namesMap[$id] ?? $namesMap[(string) $id] ?? null;
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$id);
                $values[] = (float) $it['v'];
            }

            $title = $por === 'escola'
                ? self::chartTitleDistribuicaoVagasNaCidade()
                : __('Vagas em aberto por segmento (curso)');

            $payload = $por === 'escola'
                ? ChartPayload::barHorizontal($title, __('Vagas'), $labels, $values)
                : ChartPayload::bar($title, __('Vagas'), $labels, $values);
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
}
