<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCensoEscolaQueries;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\QueryException;

/**
 * Métricas RX por município (ano vigente vs anterior) — sem indicadores financeiros.
 */
final class RxCityMetricsCollector
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(City $city, int $vigenteYear): array
    {
        $prevYear = $vigenteYear - 1;
        $base = [
            'city_id' => (int) $city->id,
            'city_name' => (string) $city->name,
            'uf' => (string) $city->uf,
            'driver' => $city->effectiveIeducarDriver(),
            'ok' => false,
            'error' => null,
            'vigente_ano' => $vigenteYear,
            'anterior_ano' => $prevYear,
            'alunos_vigente' => 0,
            'alunos_anterior' => 0,
            'matriculas_vigente' => 0,
            'matriculas_anterior' => 0,
            'turmas_vigente' => 0,
            'turmas_anterior' => 0,
            'enturmacoes_vigente' => 0,
            'enturmacoes_anterior' => 0,
            'matriculas_delta' => 0,
            'matriculas_delta_pct' => null,
            'progresso_cadastro_pct' => null,
            'registros_restantes' => 0,
            'dias_para_meta' => null,
            'horas_estimadas' => null,
            'censo' => [
                'available' => false,
                'total_escolas' => 0,
                'exportadas' => 0,
                'fechadas' => 0,
                'pendentes' => 0,
                'pct_concluido' => null,
            ],
            'cadastro_ritmo_quinzena' => 0,
        ];

        try {
            return $this->cityData->run($city, function ($db) use ($city, $vigenteYear, $prevYear, $base) {
                $filtersVigente = new IeducarFilterState((string) $vigenteYear, null, null, null);
                $filtersAnterior = new IeducarFilterState((string) $prevYear, null, null, null);

                $matV = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filtersVigente) ?? 0;
                $matA = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filtersAnterior) ?? 0;

                $alunosV = IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filtersVigente);
                $alunosA = IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filtersAnterior);

                $turmasV = IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filtersVigente);
                $turmasA = IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filtersAnterior);
                $entV = IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filtersVigente);
                $entA = IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filtersAnterior);

                $baseline = IeducarWorkActivityQueries::baselineFromPreviousYear($db, $city, $filtersVigente);
                $periods = ['day' => 0, 'week' => 0, 'fortnight' => 0];
                $ctx = IeducarWorkActivityQueries::matriculaActivityContext($db, $city);
                if ($ctx['available'] && filled($ctx['date_col'] ?? null)) {
                    $periods = IeducarWorkActivityQueries::matriculaCountsByPeriod(
                        $db,
                        $city,
                        $filtersVigente,
                        (string) $ctx['date_col'],
                        $ctx['user_col'] ?? null,
                    );
                }

                $estimativa = IeducarWorkActivityQueries::buildEstimate(
                    $baseline,
                    $periods,
                    $turmasV,
                    $matV,
                    $entV,
                    [],
                );

                $censo = IeducarCensoEscolaQueries::schoolStatuses($db, $city, $filtersVigente);
                $summary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
                $totalEsc = (int) ($summary['total_escolas'] ?? 0);
                $exportadas = (int) ($summary['exportadas'] ?? 0);
                $fechadas = (int) ($summary['fechadas'] ?? 0);
                $pendentes = (int) ($summary['pendentes'] ?? 0);
                $concluidas = $exportadas + $fechadas;
                $pctCenso = $totalEsc > 0 ? round(100.0 * $concluidas / $totalEsc, 1) : null;

                $delta = $matV - $matA;
                $deltaPct = $matA > 0 ? round(100.0 * $delta / $matA, 1) : null;

                $metaMat = (int) ($estimativa['meta_matriculas_ano_anterior'] ?? 0);
                $progresso = $metaMat > 0
                    ? (float) ($estimativa['progresso_matriculas_pct'] ?? null)
                    : null;

                return array_merge($base, [
                    'ok' => true,
                    'alunos_vigente' => $alunosV,
                    'alunos_anterior' => $alunosA,
                    'matriculas_vigente' => $matV,
                    'matriculas_anterior' => $matA,
                    'turmas_vigente' => $turmasV,
                    'turmas_anterior' => $turmasA,
                    'enturmacoes_vigente' => $entV,
                    'enturmacoes_anterior' => $entA,
                    'matriculas_delta' => $delta,
                    'matriculas_delta_pct' => $deltaPct,
                    'progresso_cadastro_pct' => $progresso,
                    'registros_restantes' => (int) ($estimativa['registros_restantes_estimados'] ?? 0),
                    'dias_para_meta' => $estimativa['dias_para_concluir_ritmo_atual'] ?? null,
                    'horas_estimadas' => $estimativa['horas_totais_estimadas'] ?? null,
                    'censo' => [
                        'available' => (bool) ($censo['available'] ?? false),
                        'total_escolas' => $totalEsc,
                        'exportadas' => $exportadas,
                        'fechadas' => $fechadas,
                        'pendentes' => $pendentes,
                        'pct_concluido' => $pctCenso,
                        'source_label' => $censo['source_label'] ?? null,
                    ],
                    'cadastro_ritmo_quinzena' => (int) ($estimativa['cadastros_ultima_quinzena'] ?? 0),
                ]);
            });
        } catch (QueryException $e) {
            $base['error'] = $e->getMessage();

            return $base;
        } catch (\Throwable $e) {
            $base['error'] = $e->getMessage();

            return $base;
        }
    }
}
