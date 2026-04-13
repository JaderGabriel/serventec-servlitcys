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
 * Frequência: agregação de registos de falta por mês (tabela falta_aluno).
 */
class AttendanceRepository
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
                $fa = IeducarSchema::resolveTable('falta_aluno', $city);
                if (! IeducarColumnInspector::tableExists($db, $fa)) {
                    return [
                        'rows' => [],
                        'message' => __('A tabela de faltas não existe ou não está acessível. Ajuste IEDUCAR_TABLE_FALTA_ALUNO em config/ieducar.php.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                $matCol = (string) config('ieducar.columns.falta_aluno.matricula');
                $dataCol = (string) config('ieducar.columns.falta_aluno.data');
                if (! IeducarColumnInspector::columnExists($db, $fa, $matCol)) {
                    return [
                        'rows' => [],
                        'message' => __('Coluna de ligação à matrícula não encontrada em falta_aluno. Defina IEDUCAR_COL_FALTA_MATRICULA.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }
                if (! IeducarColumnInspector::columnExists($db, $fa, $dataCol)
                    && IeducarColumnInspector::columnExists($db, $fa, 'data')) {
                    $dataCol = 'data';
                }
                if (! IeducarColumnInspector::columnExists($db, $fa, $dataCol)) {
                    return [
                        'rows' => [],
                        'message' => __('Coluna de data da falta não encontrada. Defina IEDUCAR_COL_FALTA_DATA (ex.: data_falta).'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                $mat = IeducarSchema::resolveTable('matricula', $city);
                $mId = (string) config('ieducar.columns.matricula.id');
                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                $grammar = $db->getQueryGrammar();
                $dataWrapped = $grammar->wrap('fa.'.$dataCol);

                $driver = $db->getDriverName();
                $monthExpr = $driver === 'pgsql'
                    ? "to_char({$dataWrapped}::date, 'YYYY-MM')"
                    : "DATE_FORMAT({$dataWrapped}, '%Y-%m')";

                $q = $db->table($fa.' as fa')
                    ->join($mat.' as m', 'fa.'.$matCol, '=', 'm.'.$mId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

                $yearVal = $filters->yearFilterValue();
                if ($yearVal !== null) {
                    if ($driver === 'pgsql') {
                        $q->whereRaw('EXTRACT(YEAR FROM '.$dataWrapped.'::date) = ?', [$yearVal]);
                    } else {
                        $q->whereRaw('YEAR('.$dataWrapped.') = ?', [$yearVal]);
                    }
                }

                $q->selectRaw($monthExpr.' as ym')
                    ->selectRaw('COUNT(*) as c')
                    ->groupByRaw($monthExpr);

                try {
                    $rows = $q->orderByDesc('ym')->limit(12)->get()->sortBy('ym')->values();
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
                        'message' => __('Sem registos de falta para os filtros seleccionados.'),
                        'error' => null,
                        'chart' => null,
                        'charts' => [],
                    ];
                }

                $labels = [];
                $values = [];
                $tableRows = [];
                foreach ($rows as $row) {
                    $ym = (string) ($row->ym ?? '');
                    $c = (int) ($row->c ?? 0);
                    $labels[] = $ym;
                    $values[] = $c;
                    $tableRows[] = ['mes' => $ym, 'faltas' => $c];
                }

                $chart = ChartPayload::bar(
                    __('Registos de falta por mês'),
                    __('Quantidade'),
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
}
