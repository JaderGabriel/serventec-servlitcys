<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Rede e oferta: turnos, séries e distribuição de turmas (decisões de expansão e janelas).
 */
class NetworkRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>,
     *   kpis: ?array{
     *     capacidade_total: int,
     *     matriculas: int,
     *     vagas_ociosas: int,
     *     taxa_ociosidade_pct: ?float,
     *     turmas_com_capacidade: int
     *   },
     *   notes: list<string>,
     *   error: ?string
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['charts' => [], 'kpis' => null, 'notes' => [], 'error' => null];
        }

        $charts = [];
        $notes = [];
        $kpis = null;

        try {
            $this->cityData->run($city, function (Connection $db) use ($city, $filters, &$charts, &$notes, &$kpis) {
                try {
                    $kpis = MatriculaChartQueries::redeVagasResumoKpis($db, $city, $filters);
                } catch (QueryException) {
                    $kpis = null;
                }

                foreach ([
                    fn () => MatriculaChartQueries::vagasOciosasPorTurno($db, $city, $filters),
                    fn () => MatriculaChartQueries::vagasAbertasPorCurso($db, $city, $filters),
                    fn () => MatriculaChartQueries::vagasAbertasPorEscola($db, $city, $filters),
                    fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorTurno($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                ] as $fn) {
                    try {
                        $c = $fn();
                        if ($c !== null) {
                            $charts[] = $c;
                        }
                    } catch (QueryException) {
                        // Bases sem tabelas esperadas (ex.: turno em outro schema).
                    }
                }

                if ($charts === []) {
                    $notes[] = __(
                        'Não foi possível montar gráficos de rede/oferta. Confirme turma, turno, série e escola em config/ieducar.php.'
                    );
                }
            });
        } catch (\Throwable $e) {
            return ['charts' => [], 'kpis' => null, 'notes' => [], 'error' => $e->getMessage()];
        }

        return ['charts' => $charts, 'kpis' => $kpis, 'notes' => $notes, 'error' => null];
    }
}
