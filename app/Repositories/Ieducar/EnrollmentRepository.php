<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\QueryException;

class EnrollmentRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * Gráficos e KPIs de matrículas (hierarquia Educacenso: nível → série → curso; escolas, turno, vagas).
     *
     * @return array{
     *   rows: list<object>,
     *   kpis: ?array{matriculas: int, turmas_distintas: int, ocupacao_pct: ?float},
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>
     * }
     */
    public function sample(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'kpis' => null, 'error' => null, 'chart' => null, 'charts' => []];
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters) {
                try {
                    $kpis = MatriculaChartQueries::enrollmentResumoKpis($db, $city, $filters);

                    $charts = [];
                    $dist = MatriculaChartQueries::distorcaoIdadeSerieRedeChart($db, $city, $filters);
                    if ($dist !== null) {
                        $charts[] = $dist;
                    }

                    $porEscola = MatriculaChartQueries::matriculasPorEscolaComOutros($db, $city, $filters, 14)
                        ?? MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters);
                    if ($porEscola !== null) {
                        $charts[] = $porEscola;
                    }

                    foreach ([
                        fn () => MatriculaChartQueries::matriculasPorNivelEnsinoEducacenso($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorSerieEducacensoCompleto($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorCursoEducacensoCompleto($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorTurno($db, $city, $filters),
                        fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
                        fn () => MatriculaChartQueries::vagasAbertasPorCurso($db, $city, $filters),
                        fn () => MatriculaChartQueries::vagasAbertasPorEscola($db, $city, $filters),
                    ] as $fn) {
                        try {
                            $c = $fn();
                            if ($c !== null) {
                                $charts[] = $c;
                            }
                        } catch (QueryException) {
                        }
                    }

                    return [
                        'rows' => [],
                        'kpis' => $kpis,
                        'error' => null,
                        'chart' => $charts[0] ?? null,
                        'charts' => $charts,
                    ];
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'kpis' => null,
                        'error' => __('Não foi possível listar matrículas. Ajuste config/ieducar.php (tabela e colunas).').' '.$e->getMessage(),
                        'chart' => null,
                        'charts' => [],
                    ];
                }
            });
        } catch (\Throwable $e) {
            return ['rows' => [], 'kpis' => null, 'error' => $e->getMessage(), 'chart' => null, 'charts' => []];
        }
    }
}
