<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Rede e oferta: vagas por turno/segmento/escola e matrículas por série e escola (expansão e uso da rede).
 */
class NetworkRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>,
     *   vagas_por_unidade_chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string},
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
            return ['charts' => [], 'vagas_por_unidade_chart' => null, 'kpis' => null, 'notes' => [], 'error' => null];
        }

        $charts = [];
        $vagasPorUnidadeChart = null;
        $notes = [];
        $kpis = null;

        try {
            $this->cityData->run($city, function (Connection $db) use ($city, $filters, &$charts, &$vagasPorUnidadeChart, &$notes, &$kpis) {
                try {
                    $kpis = MatriculaChartQueries::redeVagasResumoKpis($db, $city, $filters);
                } catch (QueryException) {
                    $kpis = null;
                }

                try {
                    $vagasPorUnidadeChart = MatriculaChartQueries::vagasAbertasPorEscola($db, $city, $filters);
                } catch (QueryException) {
                    $vagasPorUnidadeChart = null;
                }

                if ($vagasPorUnidadeChart === null) {
                    try {
                        $fallback = MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters);
                        if ($fallback !== null && is_array($fallback)) {
                            $fallback['title'] = __('Matrículas por escola (rede — sem distribuição de vagas)');
                            $fallback['subtitle'] = __(
                                'Não foi possível calcular a distribuição de vagas por turma (capacidade na turma, ligação matrícula↔turma↔escola ou turmas sem vaga livre). Este gráfico mostra o volume de matrículas ativas por unidade como leitura alternativa da rede.'
                            );
                            $fallback['options'] = array_merge($fallback['options'] ?? [], ['panelHeight' => 'xxl']);
                            $vagasPorUnidadeChart = $fallback;
                        }
                    } catch (QueryException) {
                        // Mantém null.
                    }
                }

                foreach ([
                    fn () => MatriculaChartQueries::vagasOciosasPorTurno($db, $city, $filters),
                    fn () => MatriculaChartQueries::vagasAbertasPorCurso($db, $city, $filters),
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

                if ($charts === [] && $vagasPorUnidadeChart === null) {
                    $notes[] = __(
                        'Nenhum gráfico de rede/oferta foi gerado. Confirme em config/ieducar.php: turma (e pivô matricula_turma se aplicável), turno, série, escola, curso, coluna de capacidade na turma (max_aluno), e filtros (ano / escola / segmento / turno).'
                    );
                    if (is_array($kpis) && (int) ($kpis['matriculas'] ?? 0) > 0) {
                        $notes[] = __(
                            'Existem matrículas no filtro, mas as consultas de gráfico falharam (por exemplo ligação turma→turno/escola/série ou nomes de colunas diferentes de «nome» / «nm_curso» / «nm_serie»). Verifique os JOINs na base ou defina IEDUCAR_COL_* nas tabelas correspondentes.'
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            return ['charts' => [], 'vagas_por_unidade_chart' => null, 'kpis' => null, 'notes' => [], 'error' => $e->getMessage()];
        }

        return ['charts' => $charts, 'vagas_por_unidade_chart' => $vagasPorUnidadeChart, 'kpis' => $kpis, 'notes' => $notes, 'error' => null];
    }
}
