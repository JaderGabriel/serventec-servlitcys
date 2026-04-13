<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\IeducarSqlPlaceholders;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaTurmaJoin;
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
     *   kpis: list<array{id: string, label: string, percent: ?float, quantidade: int}>,
     *   distorcao_pct: ?float,
     *   distorcao_note: ?string
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
            'distorcao_pct' => null,
            'distorcao_note' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $mat = IeducarSchema::resolveTable('matricula', $city);
                $col = (string) config('ieducar.columns.matricula_situacao.aprovado');
                if ($col === '' || ! IeducarColumnInspector::columnExists($db, $mat, $col, $city)) {
                    return [
                        'rows' => [],
                        'message' => __('Não foi encontrada a coluna de situação na tabela de matrícula. Defina IEDUCAR_COL_MATRICULA_APROVADO (por defeito «aprovado») em config/ieducar.php.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                        'kpis' => [],
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                    ];
                }

                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
                $q->selectRaw('m.'.$col.' as chave, COUNT(*) as c')
                    ->groupBy('m.'.$col);

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
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                    ];
                }

                if ($rows->isEmpty()) {
                    return [
                        'rows' => [],
                        'message' => __('Sem matrículas activas para os filtros seleccionados.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                        'kpis' => [],
                        'distorcao_pct' => null,
                        'distorcao_note' => null,
                    ];
                }

                $counts = [];
                $labels = [];
                $values = [];
                $tableRows = [];
                foreach ($rows as $row) {
                    $k = $row->chave;
                    $c = (int) ($row->c ?? 0);
                    $key = $this->normalizeSituacaoKey($k);
                    $counts[$key] = $c;
                    $label = $this->labelSituacaoMatricula($k);
                    $labels[] = $label;
                    $values[] = $c;
                    $tableRows[] = ['label' => $label, 'quantidade' => $c];
                }

                $total = array_sum($counts);
                $ind = $this->buildIndicadoresRede($counts, $total);

                $charts = [];

                $dLabels = [];
                $dVals = [];
                foreach ($ind['buckets'] as $b) {
                    if ($b['q'] > 0) {
                        $dLabels[] = $b['label'];
                        $dVals[] = $b['q'];
                    }
                }
                if ($dLabels !== []) {
                    $charts[] = ChartPayload::doughnut(__('Indicadores agregados (rede)'), $dLabels, $dVals);
                }

                $kpiBarLabels = [];
                $kpiBarVals = [];
                foreach ($ind['kpis'] as $kpi) {
                    if ($kpi['percent'] !== null) {
                        $kpiBarLabels[] = $kpi['label'];
                        $kpiBarVals[] = $kpi['percent'];
                    }
                }
                if ($kpiBarLabels !== []) {
                    $charts[] = ChartPayload::barHorizontal(
                        __('Taxas sobre o total de matrículas (rede)'),
                        __('Percentagem'),
                        $kpiBarLabels,
                        $kpiBarVals
                    );
                }

                $chart = ChartPayload::bar(
                    __('Matrículas por situação (:col)', ['col' => $col]),
                    __('Matrículas'),
                    $labels,
                    $values
                );
                $charts[] = $chart;

                [$distorcaoPct, $distorcaoNote] = $this->tryDistorcaoRede($db, $city);

                return [
                    'rows' => $tableRows,
                    'message' => '',
                    'error' => null,
                    'chart' => $chart,
                    'charts' => $charts,
                    'kpis' => $ind['kpis'],
                    'distorcao_pct' => $distorcaoPct,
                    'distorcao_note' => $distorcaoNote,
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
                'distorcao_pct' => null,
                'distorcao_note' => null,
            ];
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{buckets: list<array{label: string, q: int}>, kpis: list<array{id: string, label: string, percent: ?float, quantidade: int}>}
     */
    private function buildIndicadoresRede(array $counts, int $total): array
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

        $kpis = [
            ['id' => 'aprovacao', 'label' => __('Taxa de aprovação'), 'percent' => $pct($aprov), 'quantidade' => $aprov],
            ['id' => 'reprovacao', 'label' => __('Taxa de reprovação'), 'percent' => $pct($reprov), 'quantidade' => $reprov],
            ['id' => 'em_curso', 'label' => __('Taxa em curso / exame'), 'percent' => $pct($emCurso), 'quantidade' => $emCurso],
            ['id' => 'reclassificacao', 'label' => __('Taxa de reclassificação'), 'percent' => $pct($reclass), 'quantidade' => $reclass],
            ['id' => 'abandono', 'label' => __('Taxa de abandono'), 'percent' => $pct($aband), 'quantidade' => $aband],
            ['id' => 'remanejamento', 'label' => __('Taxa de remanejamento'), 'percent' => $pct($remanej), 'quantidade' => $remanej],
            ['id' => 'evasao', 'label' => __('Abandono + remanejamento (fluxo para fora)'), 'percent' => $pct($evasaoComb), 'quantidade' => $evasaoComb],
        ];

        return ['buckets' => $buckets, 'kpis' => $kpis];
    }

    private function normalizeSituacaoKey(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return (string) $v;
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
