<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Desempenho / situação da matrícula (campo aprovado ou equivalente na base iEducar).
 */
class PerformanceRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   message: string,
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'message' => '', 'error' => null, 'chart' => null, 'charts' => []];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $mat = IeducarSchema::resolveTable('matricula', $city);
                $col = (string) config('ieducar.columns.matricula_situacao.aprovado');
                if ($col === '' || ! IeducarColumnInspector::columnExists($db, $mat, $col)) {
                    return [
                        'rows' => [],
                        'message' => __('Não foi encontrada a coluna de situação na tabela de matrícula. Defina IEDUCAR_COL_MATRICULA_APROVADO (por defeito «aprovado») em config/ieducar.php.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                $q = $db->table($mat.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
                $q->selectRaw('m.'.$col.' as chave, COUNT(*) as c')
                    ->groupBy('m.'.$col);

                try {
                    $rows = $q->orderByDesc('c')->limit(24)->get();
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'message' => '',
                        'error' => $e->getMessage(),
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                if ($rows->isEmpty()) {
                    return [
                        'rows' => [],
                        'message' => __('Sem matrículas activas para os filtros seleccionados.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                $labels = [];
                $values = [];
                $tableRows = [];
                foreach ($rows as $row) {
                    $k = $row->chave;
                    $c = (int) ($row->c ?? 0);
                    $label = $this->labelSituacaoMatricula($k);
                    $labels[] = $label;
                    $values[] = $c;
                    $tableRows[] = ['label' => $label, 'quantidade' => $c];
                }

                $chart = ChartPayload::bar(
                    __('Matrículas por situação (:col)', ['col' => $col]),
                    __('Matrículas'),
                    $labels,
                    $values
                );

                return [
                    'rows' => $tableRows,
                    'message' => '',
                    'error' => null,
                    'chart' => $chart,
                    'charts' => $chart !== null ? [$chart] : [],
                ];
            });
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'message' => '',
                'error' => $e->getMessage(),
                'chart' => null,
                'charts' => [],
            ];
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
