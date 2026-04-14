<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\IeducarSqlPlaceholders;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaSituacaoResolver;
use App\Support\Ieducar\MatriculaTurmaJoin;
use App\Support\Ieducar\PerformanceInepPanel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Desempenho / situação da matrícula (campo aprovado ou equivalente na base iEducar).
 */
class PerformanceRepository
{
    /** @var list<string> */
    private const APROVACAO = ['2', '5', '12', '13', '14'];

    /** @var list<string> */
    private const REPROVACAO = ['3', '6', '8'];

    /** @var list<string> */
    private const EM_CURSO = ['1', '4', '7'];

    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   message: string,
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   kpis: list<array<string, mixed>>,
     *   kpi_meta: array{
     *     total_matriculas: int,
     *     campo_situacao: string,
     *     denominador_texto: string,
     *     alerta_ano_encerrado: ?string
     *   },
     *   distorcao_pct: ?float,
     *   distorcao_note: ?string,
     *   inep_panel: ?array<string, mixed>
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'rows' => [],
            'message' => '',
            'error' => null,
            'chart' => null,
            'charts' => [],
            'kpis' => [],
            'kpi_meta' => [
                'total_matriculas' => 0,
                'campo_situacao' => '',
                'denominador_texto' => '',
                'alerta_ano_encerrado' => null,
            ],
            'distorcao_pct' => null,
            'distorcao_note' => null,
            'inep_panel' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $inepPanel = PerformanceInepPanel::build($db, $city, $filters);

                $mat = IeducarSchema::resolveTable('matricula', $city);
                $spec = MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city);
                if ($spec === null) {
                    $chartsInep = [];
                    if (($inepPanel['consolidated_chart'] ?? null) !== null) {
                        $chartsInep[] = $inepPanel['consolidated_chart'];
                    }

                    return [
                        'rows' => [],
                        'message' => __('Não foi possível determinar a situação da matrícula: confirme colunas «aprovado» ou «ref_cod_matricula_situacao» em matricula e a tabela «matricula_situacao» (IEDUCAR_TABLE_MATRICULA_SITUACAO) com «codigo» INEP.'),
                        'error' => null,
                        'chart' => $chartsInep[0] ?? null,
                        'charts' => $chartsInep,
                        'kpis' => [],
                        'kpi_meta' => [
                            'total_matriculas' => 0,
                            'campo_situacao' => '',
                            'denominador_texto' => '',
                            'alerta_ano_encerrado' => null,
                        ],
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                        'inep_panel' => $inepPanel,
                    ];
                }

                $campoSituacao = $spec['campo_situacao'];
                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                $spec['applyJoins']($q);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
                $q->selectRaw($spec['chaveExpr'].' as chave, COUNT(*) as c')
                    ->groupByRaw($spec['groupByExpr']);

                try {
                    $rows = $q->orderByDesc('c')->get();
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'message' => '',
                        'error' => $e->getMessage(),
                        'chart' => null,
                        'charts' => [],
                        'kpis' => [],
                        'kpi_meta' => [
                            'total_matriculas' => 0,
                            'campo_situacao' => $campoSituacao,
                            'denominador_texto' => '',
                            'alerta_ano_encerrado' => null,
                        ],
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                        'inep_panel' => $inepPanel,
                    ];
                }

                if ($rows->isEmpty()) {
                    $chartsInep = [];
                    if (($inepPanel['consolidated_chart'] ?? null) !== null) {
                        $chartsInep[] = $inepPanel['consolidated_chart'];
                    }

                    return [
                        'rows' => [],
                        'message' => __('Sem matrículas ativas para os filtros seleccionados.'),
                        'error' => null,
                        'chart' => $chartsInep[0] ?? null,
                        'charts' => $chartsInep,
                        'kpis' => [],
                        'kpi_meta' => [
                            'total_matriculas' => 0,
                            'campo_situacao' => $campoSituacao,
                            'denominador_texto' => '',
                            'alerta_ano_encerrado' => null,
                        ],
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                        'inep_panel' => $inepPanel,
                    ];
                }

                $counts = [];
                foreach ($rows as $row) {
                    $k = $row->chave;
                    $c = (int) ($row->c ?? 0);
                    $key = $this->normalizeSituacaoKey($k);
                    $counts[$key] = ($counts[$key] ?? 0) + $c;
                }
                arsort($counts);

                $labels = [];
                $values = [];
                $tableRows = [];
                foreach ($counts as $key => $c) {
                    $label = $this->labelSituacaoMatricula($key === '' ? null : $key);
                    $labels[] = $label;
                    $values[] = $c;
                    $tableRows[] = ['label' => $label, 'quantidade' => $c];
                }

                $total = array_sum($counts);
                $ind = $this->buildIndicadoresRede($counts, $total, $filters, $campoSituacao);

                $charts = [];

                $dLabels = [];
                $dVals = [];
                foreach ($ind['buckets'] as $b) {
                    if ($b['q'] > 0) {
                        $dLabels[] = $b['label'];
                        $dVals[] = $b['q'];
                    }
                }
                if ($dLabels === [] && $total > 0) {
                    $dLabels = [__('Total de matrículas (agregado)')];
                    $dVals = [$total];
                }
                if ($dLabels !== []) {
                    $agg = ChartPayload::doughnut(__('Indicadores agregados (rede)'), $dLabels, $dVals);
                    $agg['subtitle'] = __(
                        'Distribuição das matrículas ativas do filtro pelas categorias de situação agregadas na rede. Cada fatia corresponde a uma categoria; o tamanho reflecte a participação no total considerado no denominador comum.'
                    );
                    $charts[] = $agg;
                }

                $kpiBarLabels = [];
                $kpiBarVals = [];
                foreach ($ind['kpis'] as $kpi) {
                    if (($kpi['include_in_bar_chart'] ?? true) && $kpi['percent'] !== null) {
                        $kpiBarLabels[] = (string) ($kpi['chart_label'] ?? $kpi['label']);
                        $kpiBarVals[] = $kpi['percent'];
                    }
                }
                if ($kpiBarLabels !== []) {
                    $charts[] = ChartPayload::barHorizontal(
                        __('Taxas sobre o total de matrículas ativas (mesmo denominador)'),
                        __('Percentagem'),
                        $kpiBarLabels,
                        $kpiBarVals
                    );
                }

                $chart = ChartPayload::bar(
                    __('Matrículas por situação (:col)', ['col' => $campoSituacao]),
                    __('Matrículas'),
                    $labels,
                    $values
                );
                $charts[] = $chart;

                $distorcaoChart = MatriculaChartQueries::distorcaoIdadeSerieRedeChart($db, $city, $filters);
                if ($distorcaoChart !== null) {
                    array_unshift($charts, $distorcaoChart);
                }

                if (($inepPanel['consolidated_chart'] ?? null) !== null) {
                    $charts[] = $inepPanel['consolidated_chart'];
                }

                [$distorcaoPct, $distorcaoNote] = $this->tryDistorcaoRede($db, $city);

                return [
                    'rows' => $tableRows,
                    'message' => '',
                    'error' => null,
                    'chart' => $chart,
                    'charts' => $charts,
                    'kpis' => $ind['kpis'],
                    'kpi_meta' => $ind['kpi_meta'],
                    'distorcao_pct' => $distorcaoPct,
                    'distorcao_note' => $distorcaoNote,
                    'inep_panel' => $inepPanel,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'message' => '',
                'error' => $e->getMessage(),
                'chart' => null,
                'charts' => [],
                'kpis' => [],
                'kpi_meta' => [
                    'total_matriculas' => 0,
                    'campo_situacao' => '',
                    'denominador_texto' => '',
                    'alerta_ano_encerrado' => null,
                ],
                'distorcao_pct' => null,
                'distorcao_note' => null,
                'inep_panel' => null,
            ];
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{
     *   buckets: list<array{label: string, q: int}>,
     *   kpis: list<array<string, mixed>>,
     *   kpi_meta: array{
     *     total_matriculas: int,
     *     campo_situacao: string,
     *     denominador_texto: string,
     *     alerta_ano_encerrado: ?string
     *   }
     * }
     */
    private function buildIndicadoresRede(array $counts, int $total, IeducarFilterState $filters, string $situacaoCol): array
    {
        $pct = static function (int $n) use ($total): ?float {
            if ($total <= 0) {
                return null;
            }

            return round(100.0 * $n / $total, 1);
        };

        $sum = function (array $codes) use ($counts): int {
            $s = 0;
            foreach ($codes as $c) {
                $s += $counts[$c] ?? 0;
            }

            return $s;
        };

        $aprov = $sum(self::APROVACAO);
        $reprov = $sum(self::REPROVACAO);
        $emCurso = $sum(self::EM_CURSO);
        $reclass = $counts['10'] ?? 0;
        $aband = $counts['11'] ?? 0;
        $remanej = $counts['16'] ?? 0;
        $known = $aprov + $reprov + $emCurso + $reclass + $aband + $remanej;
        $outros = max(0, $total - $known);

        $buckets = [
            ['label' => __('Aprovação'), 'q' => $aprov],
            ['label' => __('Reprovação'), 'q' => $reprov],
            ['label' => __('Em curso / exame / paralela'), 'q' => $emCurso],
            ['label' => __('Reclassificação'), 'q' => $reclass],
            ['label' => __('Abandono'), 'q' => $aband],
            ['label' => __('Remanejamento'), 'q' => $remanej],
            ['label' => __('Outros'), 'q' => $outros],
        ];

        $evasaoComb = $aband + $remanej;

        $denominadorTxt = __(
            'Denominador comum: :total matrículas com matricula.ativo conforme configuração, campo «:col» (situação) agrupado por código i-Educar, com os filtros de ano/turma aplicados.',
            ['total' => $total, 'col' => $situacaoCol]
        );

        $emPct = $pct($emCurso);
        $yearVal = $filters->yearFilterValue();
        $currentYear = (int) date('Y');
        $alertaAno = null;
        if ($yearVal !== null && $yearVal < $currentYear && $emPct !== null && $emPct >= 30.0) {
            $alertaAno = __(
                'O ano letivo seleccionado já terminou, mas uma parte relevante das matrículas aparece como «em curso / exame / paralela» (códigos 1, 4 e 7). Isso costuma indicar que a situação final ainda não foi actualizada no i-Educar — não é uma taxa pedagógica de «andamento» do ano.'
            );
        }

        $kpi = function (
            string $id,
            string $label,
            string $chartLabel,
            string $desc,
            string $formula,
            ?float $p,
            int $q,
            bool $inBar
        ) {
            return [
                'id' => $id,
                'label' => $label,
                'chart_label' => $chartLabel,
                'description' => $desc,
                'formula' => $formula,
                'percent' => $p,
                'quantidade' => $q,
                'include_in_bar_chart' => $inBar,
            ];
        };

        $kpis = [
            $kpi(
                'aprovacao',
                __('Taxa de aprovação'),
                __('Aprovação — cód. 2, 5, 12, 13, 14'),
                __('Percentagem de matrículas cuja situação corresponde a aprovação (códigos i-Educar 2, 5, 12, 13 e 14) em relação ao total de matrículas ativas no filtro.'),
                __('(matrículas com situação ∈ {2,5,12,13,14}) ÷ (total de matrículas no denominador) × 100'),
                $pct($aprov),
                $aprov,
                true
            ),
            $kpi(
                'reprovacao',
                __('Taxa de reprovação'),
                __('Reprovação — cód. 3, 6, 8'),
                __('Percentagem de matrículas com reprovação, retido ou reprovação por faltas (códigos 3, 6 e 8).'),
                __('(situação ∈ {3,6,8}) ÷ (total) × 100'),
                $pct($reprov),
                $reprov,
                true
            ),
            $kpi(
                'em_curso',
                __('Taxa «em curso / exame / paralela»'),
                __('Em curso / exame / paralela — cód. 1, 4, 7'),
                __('Percentagem com situação «em curso», «em exame» ou «paralela» (códigos 1, 4 e 7). O campo reflecte o estado no registo; em anos já encerrados, valores muito altos indicam sobretudo falta de fechamento ou atualização na base de dados.'),
                __('(situação ∈ {1,4,7}) ÷ (total) × 100'),
                $pct($emCurso),
                $emCurso,
                true
            ),
            $kpi(
                'reclassificacao',
                __('Taxa de reclassificação'),
                __('Reclassificação — cód. 10'),
                __('Percentagem com situação «reclassificado» (código 10).'),
                __('(situação = 10) ÷ (total) × 100'),
                $pct($reclass),
                $reclass,
                true
            ),
            $kpi(
                'abandono',
                __('Taxa de abandono'),
                __('Abandono — cód. 11'),
                __('Percentagem com situação «abandono» (código 11).'),
                __('(situação = 11) ÷ (total) × 100'),
                $pct($aband),
                $aband,
                true
            ),
            $kpi(
                'remanejamento',
                __('Taxa de remanejamento'),
                __('Remanejamento — cód. 16'),
                __('Percentagem com situação «remanejado» (código 16).'),
                __('(situação = 16) ÷ (total) × 100'),
                $pct($remanej),
                $remanej,
                true
            ),
            $kpi(
                'evasao',
                __('Taxa abandono + remanejamento (combinada)'),
                __('Abandono + remanejamento'),
                __('Soma das matrículas com abandono ou remanejamento (códigos 11 e 16). A percentagem é (11+16)÷total; coincide com a soma das percentagens de abandono e remanejamento. Não entra no gráfico de barras para evitar redundância com as duas anteriores.'),
                __('((situação = 11) + (situação = 16)) ÷ (total) × 100'),
                $pct($evasaoComb),
                $evasaoComb,
                false
            ),
        ];

        $kpiMeta = [
            'total_matriculas' => $total,
            'campo_situacao' => $situacaoCol,
            'denominador_texto' => $denominadorTxt,
            'alerta_ano_encerrado' => $alertaAno,
        ];

        return ['buckets' => $buckets, 'kpis' => $kpis, 'kpi_meta' => $kpiMeta];
    }

    private function normalizeSituacaoKey(mixed $v): string
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
     * @return array{0: ?float, 1: ?string}
     */
    private function tryDistorcaoRede(Connection $db, City $city): array
    {
        $sql = (string) config('ieducar.sql.distorcao_rede', '');
        $sql = trim($sql);
        if ($sql === '') {
            return [null, null];
        }

        try {
            $sql = IeducarSqlPlaceholders::interpolate($sql, $city);
            $row = $db->selectOne($sql);
            if ($row === null) {
                return [null, null];
            }
            $arr = (array) $row;
            $num = null;
            foreach ($arr as $v) {
                if (is_numeric($v)) {
                    $num = (float) $v;
                    break;
                }
            }

            return [$num, null];
        } catch (\Throwable) {
            return [null, __('Não foi possível executar IEDUCAR_SQL_DISTORCAO_REDE.')];
        }
    }

    private function labelSituacaoMatricula(mixed $v): string
    {
        if ($v === null || $v === '') {
            return __('Não informado');
        }

        $k = (string) $v;
        $map = [
            '0' => __('Não definido'),
            '1' => __('Em curso'),
            '2' => __('Aprovado'),
            '3' => __('Reprovado'),
            '4' => __('Em exame'),
            '5' => __('Aprovado após exame'),
            '6' => __('Retido'),
            '7' => __('Paralela'),
            '8' => __('Reprovado por faltas'),
            '9' => __('Falecido'),
            '10' => __('Reclassificado'),
            '11' => __('Abandono'),
            '12' => __('Aprovado sem exame'),
            '13' => __('Aprovado com dependência'),
            '14' => __('Aprovado pelo conselho'),
            '15' => __('Retido familiar'),
            '16' => __('Remanejado'),
        ];

        return $map[$k] ?? __('Código :c', ['c' => $k]);
    }
}
