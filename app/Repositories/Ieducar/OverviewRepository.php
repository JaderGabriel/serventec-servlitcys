<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

class OverviewRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   kpis: ?array{escolas: ?int, turmas: ?int, matriculas: ?int},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   filter_note: ?string,
     *   error: ?string
     * }
     */
    public function summary(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $kpis = [
                    'escolas' => $this->countEscolas($db, $city, $filters),
                    'turmas' => $this->countTurmas($db, $city, $filters),
                    'matriculas' => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters),
                ];

                $charts = [];
                if ($kpis['escolas'] !== null || $kpis['turmas'] !== null || $kpis['matriculas'] !== null) {
                    $charts[] = ChartPayload::bar(
                        __('Totais (visão geral)'),
                        __('Quantidade'),
                        [__('Escolas'), __('Turmas'), __('Matrículas')],
                        [
                            (float) ($kpis['escolas'] ?? 0),
                            (float) ($kpis['turmas'] ?? 0),
                            (float) ($kpis['matriculas'] ?? 0),
                        ]
                    );
                }

                try {
                    $evo = MatriculaChartQueries::chartEvolucaoMatriculasPorAno($db, $city, $filters);
                    if ($evo !== null) {
                        if ($charts === []) {
                            $charts[] = $evo;
                        } else {
                            array_splice($charts, 1, 0, [$evo]);
                        }
                    }
                } catch (QueryException) {
                }

                foreach ([
                    fn () => MatriculaChartQueries::matriculasPorCursoTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
                ] as $fn) {
                    try {
                        $c = $fn();
                        if ($c !== null) {
                            $charts[] = $c;
                        }
                    } catch (QueryException) {
                        // Ignorar gráficos opcionais quando a base não tiver as tabelas esperadas.
                    }
                }

                $note = $this->filterNote($filters);

                return [
                    'kpis' => $kpis,
                    'charts' => $charts,
                    'filter_note' => $note,
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'kpis' => null,
                'charts' => [],
                'filter_note' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function filterNote(IeducarFilterState $filters): ?string
    {
        if (! $filters->hasYearSelected()) {
            return null;
        }

        if ($filters->escola_id !== null || $filters->curso_id !== null || $filters->turno_id !== null) {
            return __('Os totais acima aplicam também escola, tipo/segmento e turno quando existirem na turma.');
        }

        return null;
    }

    private function countEscolas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('escola', $city);
            $id = (string) config('ieducar.columns.escola.id');
            $q = $db->table($table);
            if ($filters->escola_id !== null) {
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $id, $filters->escola_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countTurmas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $q = $db->table($table);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where($tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['turno'], $filters->turno_id);

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }
}
