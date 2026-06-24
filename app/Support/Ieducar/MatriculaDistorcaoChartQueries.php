<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarAnalyticsMetricsScope;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Consultas de distorção idade/série (extraídas de MatriculaChartQueries).
 */
final class MatriculaDistorcaoChartQueries
{
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

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
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

            $escolaSpec = MatriculaChartQueries::escolaJoinSpec($db, $city);
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
        $scope = IeducarAnalyticsMetricsScope::resolve();
        if ($scope !== null && $scope->matches($city, $filters)) {
            $pack = $scope->distorcaoPack();
            if ($pack === null) {
                return null;
            }

            return [
                'com' => (int) $pack['com'],
                'sem' => (int) $pack['sem'],
                'total' => (int) $pack['total'],
                'fonte' => (string) $pack['fonte'],
                'metodo' => (string) ($pack['metodo'] ?? ''),
                'cobertura_pct' => $pack['cobertura_pct'] ?? null,
                'mecanismos' => $scope->distorcaoMecanismos(),
            ];
        }

        $pack = DistorcaoIdadeSerieEngine::contagens($db, $city, $filters);
        if ($pack === null) {
            return self::distorcaoIdadeSerieContagensAutomatico($db, $city, $filters);
        }

        return [
            'com' => (int) $pack['com'],
            'sem' => (int) $pack['sem'],
            'total' => (int) $pack['total'],
            'fonte' => (string) $pack['fonte'],
            'metodo' => (string) ($pack['metodo'] ?? ''),
            'cobertura_pct' => $pack['cobertura_pct'] ?? null,
            'mecanismos' => $pack['mecanismos'] ?? [],
        ];
    }

    /**
     * Comparativo de mecanismos de apuração (schema-probe / diagnóstico).
     *
     * @return list<array<string, mixed>>
     */
    public static function distorcaoIdadeSerieMecanismos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        return DistorcaoIdadeSerieEngine::apurarTodosMecanismos($db, $city, $filters);
    }

    /**
     * @return array{
     *   histograma_faixas: ?array<string, mixed>,
     *   histograma_serie: ?array<string, mixed>,
     *   histograma_escola: ?array<string, mixed>,
     *   situacao_cruzada: list<array<string, mixed>>
     * }
     */
    public static function distorcaoIdadeSerieAnaliticos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $scope = IeducarAnalyticsMetricsScope::resolve();
        if ($scope !== null && $scope->matches($city, $filters)) {
            return $scope->distorcaoAnaliticos();
        }

        return DistorcaoIdadeSerieEngine::analiticos($db, $city, $filters);
    }

    /**
     * Texto para o painel quando não há KPI de distorção mas existem matrículas no filtro.
     */
    public static function distorcaoIdadeSerieCartaoIndisponivelMotivo(Connection $db, City $city, IeducarFilterState $filters): ?string
    {
        return DistorcaoIdadeSerieEngine::motivoIndisponivel($db, $city, $filters);
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

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
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
            $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + '.$margem.')';

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

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
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
            $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + '.$margem.')';

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
     * Matrículas com distorção idade/série, agrupadas por turno × curso (rótulos compostos).
     *
     * @return array{labels: list<string>, values: list<float>}|null
     */
    private static function distorcaoComDistorsaoPorTurnoCursoAutomatico(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
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
            if ($tc['year'] === '' || $tc['serie'] === '' || $tc['curso'] === '' || $tc['turno'] === '') {
                return null;
            }

            $cursoSpec = MatriculaChartQueries::cursoJoinSpec($db, $city);
            $turnoSpec = MatriculaChartQueries::turnoJoinSpec($db, $city);
            if ($cursoSpec === null || $turnoSpec === null) {
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

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
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
            $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + '.$margem.')';

            $serieJoinCol = $tc['serie'];
            $cIdCol = $cursoSpec['idCol'];
            $cNameCol = $cursoSpec['nameCol'];
            $tnId = $turnoSpec['idCol'];
            $tnName = $turnoSpec['nameCol'];

            $base = function () use ($db, $city, $filters, $mat, $mAtivo, $mAluno, $aluno, $aId, $aPessoa, $pessoa, $pId, $serieT, $sId, $birthCol, $serieJoinCol, $limiteExpr, $grammar, $tc, $cursoSpec, $turnoSpec, $cIdCol): Builder {
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

                $tCurFk = $grammar->wrap('t_filter').'.'.$grammar->wrap($tc['curso']);
                $cPk = $grammar->wrap('c').'.'.$grammar->wrap($cIdCol);
                $q->join($cursoSpec['qualified'].' as c', function ($join) use ($db, $tCurFk, $cPk) {
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$tCurFk.')::text = ('.$cPk.')::text');
                    } else {
                        $join->whereRaw('CAST('.$tCurFk.' AS UNSIGNED) = CAST('.$cPk.' AS UNSIGNED)');
                    }
                });
                MatriculaChartQueries::joinTurmaAliasToTurnoCatalog($db, $q, 't_filter', $turnoSpec['qualified'], 'tn', $tc['turno'], $tnId);

                return $q;
            };

            $tidW = $grammar->wrap('tn').'.'.$grammar->wrap($tnId);
            $cidW = $grammar->wrap('c').'.'.$grammar->wrap($cIdCol);
            $rows = $base()
                ->whereRaw($distorcaoCond)
                ->selectRaw($tidW.' as tid')
                ->selectRaw('MAX(tn.'.$grammar->wrap($tnName).') as tname')
                ->selectRaw($cidW.' as cid')
                ->selectRaw('MAX(c.'.$grammar->wrap($cNameCol).') as cname')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy([$tidW, $cidW])
                ->orderByDesc('cnt')
                ->limit(24)
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $tn = trim((string) ($row->tname ?? ''));
                $cn = trim((string) ($row->cname ?? ''));
                if ($tn === '') {
                    $tn = __('Turno #:id', ['id' => (string) ($row->tid ?? '')]);
                }
                if ($cn === '') {
                    $cn = __('Curso #:id', ['id' => (string) ($row->cid ?? '')]);
                }
                $labels[] = $tn.' · '.$cn;
                $values[] = (float) ($row->cnt ?? 0);
            }

            return ['labels' => $labels, 'values' => $values];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Matrículas com distorção idade/série, agrupadas por escola (critério INEP automático).
     *
     * @return array{labels: list<string>, values: list<float>}|null
     */
    private static function distorcaoComDistorsaoPorEscolaAutomatico(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
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
            if ($tc['year'] === '' || $tc['serie'] === '' || $tc['escola'] === '') {
                return null;
            }

            $escolaSpec = MatriculaChartQueries::escolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
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

            $spec = MatriculaChartQueries::serieJoinSpec($db, $city);
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
            $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + '.$margem.')';

            $serieJoinCol = $tc['serie'];
            $eIdCol = $escolaSpec['idCol'];
            $eNameCol = $escolaSpec['nameCol'];

            $base = function () use ($db, $city, $filters, $mat, $mAtivo, $mAluno, $aluno, $aId, $aPessoa, $pessoa, $pId, $serieT, $sId, $birthCol, $serieJoinCol, $limiteExpr, $grammar, $tc, $escolaSpec, $eIdCol): Builder {
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

                $tEscFk = $grammar->wrap('t_filter').'.'.$grammar->wrap($tc['escola']);
                $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eIdCol);
                $q->join($escolaSpec['qualified'].' as e', function ($join) use ($db, $tEscFk, $ePk) {
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$tEscFk.')::text = ('.$ePk.')::text');
                    } else {
                        $join->whereRaw('CAST('.$tEscFk.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                    }
                });

                return $q;
            };

            $eidW = $grammar->wrap('e').'.'.$grammar->wrap($eIdCol);
            $rows = $base()
                ->whereRaw($distorcaoCond)
                ->selectRaw($eidW.' as eid')
                ->selectRaw('MAX(e.'.$grammar->wrap($eNameCol).') as ename')
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy([$eidW])
                ->orderByDesc('cnt')
                ->limit(20)
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            $detail = [];
            foreach ($rows as $row) {
                $eid = (string) ($row->eid ?? '');
                $ename = (string) (($row->ename ?? '') !== '' ? $row->ename : ('#'.$eid));
                $cnt = (int) ($row->cnt ?? 0);
                $labels[] = $ename;
                $values[] = (float) $cnt;
                $detail[] = ['eid' => $eid, 'ename' => $ename, 'cnt' => $cnt];
            }

            return ['labels' => $labels, 'values' => $values, 'detail' => $detail];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Matrículas com distorção idade/série por escola (para diagnóstico de discrepâncias / Censo).
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function distorcaoMatriculasPorEscolaRows(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $by = self::distorcaoComDistorsaoPorEscolaAutomatico($db, $city, $filters);
        if ($by === null || ! isset($by['detail']) || ! is_array($by['detail'])) {
            return [];
        }
        $out = [];
        foreach ($by['detail'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($cnt <= 0) {
                continue;
            }
            $out[] = [
                'escola_id' => (string) ($row['eid'] ?? ''),
                'escola' => (string) ($row['ename'] ?? '—'),
                'total' => $cnt,
            ];
        }

        return $out;
    }

    /**
     * Distorção na rede: onde há casos (turno × curso), critério INEP automático.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    public static function distorcaoIdadeSeriePorTurnoCursoRedeChart(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
        $by = self::distorcaoComDistorsaoPorTurnoCursoAutomatico($db, $city, $filters);
        if ($by === null || $by['labels'] === []) {
            return null;
        }

        [$labels, $values] = ChartPayload::capTailAsOutros($by['labels'], $by['values'], 18, __('Outros turno/curso'));

        $chart = ChartPayload::barHorizontal(
            __('Distorção idade/série — turno e curso (rede)'),
            __('Matrículas com distorção'),
            $labels,
            $values
        );
        $chart['subtitle'] = __(
            'Contagem de matrículas activas com distorção (idade à 31/03 > limite da série + 2 anos), por combinação turno × curso no filtro.'
        );
        $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'xl']);

        return $chart;
    }

    /**
     * Distorção por escola (automático MySQL/MariaDB/PostgreSQL), quando a base tem turma→escola e série.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string}
     */
    public static function distorcaoIdadeSeriePorEscolaAutomaticoChart(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
        $by = self::distorcaoComDistorsaoPorEscolaAutomatico($db, $city, $filters);
        if ($by === null || $by['labels'] === []) {
            return null;
        }

        [$labels, $values] = ChartPayload::capTailAsOutros($by['labels'], $by['values'], 14, __('Outras unidades'));

        $chart = ChartPayload::barHorizontal(
            __('Distorção idade/série — por escola (rede)'),
            __('Matrículas com distorção'),
            $labels,
            $values
        );
        $chart['subtitle'] = __(
            'Critério INEP automático (pessoa.data_nasc, série com idade máxima). Top :n unidades com mais casos no filtro.',
            ['n' => count($labels)]
        );
        $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'xxl']);

        return $chart;
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
     * Data de corte escolar 31/03 do ano letivo da turma (expressão SQL).
     */
    private static function refDateCorteEscolarSql(Connection $db, string $turmaAlias, string $yearCol): string
    {
        return DistorcaoIdadeSerieEngine::refDateCorteEscolarSql($db, $turmaAlias.'.'.$yearCol);
    }

    /**
     * Idade em anos completos na data de referência (expressão SQL).
     */
    private static function idadeAnosCompletosSql(Connection $db, string $refDateExpr, string $pessoaAlias, string $birthCol): string
    {
        return DistorcaoIdadeSerieEngine::idadeAnosCompletosSql($db, $refDateExpr, $pessoaAlias.'.'.$birthCol);
    }
}
