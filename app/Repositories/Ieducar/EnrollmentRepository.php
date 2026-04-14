<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\QueryException;

class EnrollmentRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * Gráficos e KPIs de matrículas (distorção por escola/rede, séries/cursos, escolas, turno, vagas).
     *
     * @return array{
     *   rows: list<object>,
     *   kpis: ?array{matriculas: int, turmas_distintas: int, ocupacao_pct: ?float},
     *   distorcao: ?array{com: int, sem: int, total: int, pct: ?float, fonte: string},
     *   fluxo_taxas: ?array{total: int, abandono_q: int, remanejamento_q: int, evasao_q: int, abandono_pct: ?float, evasao_pct: ?float},
     *   unidades_escolares: ?list<array{nome: string, total: int}>,
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>
     * }
     */
    public function sample(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return [
                'rows' => [],
                'kpis' => null,
                'distorcao' => null,
                'fluxo_taxas' => null,
                'unidades_escolares' => null,
                'error' => null,
                'chart' => null,
                'charts' => [],
            ];
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters) {
                try {
                    $kpis = MatriculaChartQueries::enrollmentResumoKpis($db, $city, $filters);
                    $unidadesEscolares = MatriculaChartQueries::matriculasPorUnidadesEscolaresCard($db, $city, $filters, 24);

                    $fluxoTaxas = MatriculaChartQueries::taxasAbandonoEvasaoFluxoEscolar($db, $city, $filters);

                    $distCont = MatriculaChartQueries::distorcaoIdadeSerieContagens($db, $city, $filters);
                    $distorcao = null;
                    if ($distCont !== null && ($distCont['total'] ?? 0) > 0) {
                        $tot = (int) $distCont['total'];
                        $com = (int) $distCont['com'];
                        $distorcao = [
                            'com' => $com,
                            'sem' => (int) $distCont['sem'],
                            'total' => $tot,
                            'pct' => round(100.0 * $com / $tot, 1),
                            'fonte' => (string) $distCont['fonte'],
                        ];
                    }

                    $charts = [];
                    $distPorEscola = MatriculaChartQueries::distorcaoIdadeSeriePorEscolaFisica($db, $city, $filters);
                    if ($distPorEscola !== null) {
                        $charts[] = $distPorEscola;
                    }

                    $dist = MatriculaChartQueries::distorcaoIdadeSerieRedeChart($db, $city, $filters);
                    if ($dist !== null) {
                        $charts[] = $dist;
                    }

                    $porEscola = MatriculaChartQueries::matriculasPorEscolaRelatorioDireto($db, $city, $filters, 15)
                        ?? MatriculaChartQueries::matriculasPorEscolaComOutros($db, $city, $filters, 14)
                        ?? MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters);
                    if ($porEscola !== null) {
                        $charts[] = $porEscola;
                    }

                    foreach ([
                        fn () => MatriculaChartQueries::matriculasPorSerieEducacensoCompleto($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorCursoEducacensoCompleto($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorTurno($db, $city, $filters),
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

                    // Card extra para evitar "buraco" visual e manter a temática de matrículas:
                    // distribuição do fluxo (abandono/remanejamento) quando existir base de situação INEP.
                    if (is_array($fluxoTaxas) && (int) ($fluxoTaxas['total'] ?? 0) > 0) {
                        $abandono = (int) ($fluxoTaxas['abandono_q'] ?? 0);
                        $remanej = (int) ($fluxoTaxas['remanejamento_q'] ?? 0);
                        $outros = max(0, (int) ($fluxoTaxas['total'] ?? 0) - $abandono - $remanej);

                        $charts[] = ChartPayload::doughnut(
                            __('Matrículas — fluxo escolar (abandono/remanejamento)'),
                            [__('Abandono (11)'), __('Remanejamento (16)'), __('Outras situações / sem código')],
                            [$abandono, $remanej, $outros],
                        );
                    }

                    return [
                        'rows' => [],
                        'kpis' => $kpis,
                        'distorcao' => $distorcao,
                        'fluxo_taxas' => $fluxoTaxas,
                        'unidades_escolares' => $unidadesEscolares,
                        'error' => null,
                        'chart' => $charts[0] ?? null,
                        'charts' => $charts,
                    ];
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'kpis' => null,
                        'distorcao' => null,
                        'fluxo_taxas' => null,
                        'unidades_escolares' => null,
                        'error' => __('Não foi possível listar matrículas. Ajuste config/ieducar.php (tabela e colunas).').' '.$e->getMessage(),
                        'chart' => null,
                        'charts' => [],
                    ];
                }
            });
        } catch (\Throwable $e) {
            return ['rows' => [], 'kpis' => null, 'distorcao' => null, 'fluxo_taxas' => null, 'unidades_escolares' => null, 'error' => $e->getMessage(), 'chart' => null, 'charts' => []];
        }
    }
}
