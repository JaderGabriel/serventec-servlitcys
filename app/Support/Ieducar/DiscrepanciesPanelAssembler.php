<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Montagem única do painel modular de discrepâncias (consultoria e admin Compatibilidade).
 */
final class DiscrepanciesPanelAssembler
{
    /**
     * @return array{
     *   dimensions: list<array<string, mixed>>,
     *   total_matriculas: int,
     *   issue_rows_by_id: array<string, list<array<string, mixed>>>,
     *   notes: list<string>
     * }
     */
    public static function assemble(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $fundebAnchorAno = null,
    ): array {
        $fundebAnchorAno = $fundebAnchorAno ?? self::resolveFundebAnchorAno($filters);
        $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
        $catalog = DiscrepanciesCheckCatalog::definitions();
        $queryMap = DiscrepanciesCheckRunner::queryMap();
        $dimensions = [];
        $issueRowsById = [];
        $notes = [];

        foreach ($catalog as $id => $meta) {
            if ($id === 'nee_subnotificacao') {
                continue;
            }

            $spec = $queryMap[$id] ?? null;
            if ($spec === null) {
                $dimensions[] = self::dimensionFromEval($id, $meta, [
                    'availability' => 'unavailable',
                    'has_issue' => false,
                    'rows' => [],
                    'unavailable_reason' => __('Rotina não implementada.'),
                ], $totalMat, $city, $filters);

                continue;
            }

            $eval = DiscrepanciesCheckRunner::evaluate(
                $db,
                $city,
                $filters,
                $spec['fn'],
                $spec['probe'],
                isset($spec['hint']) ? (string) $spec['hint'] : null,
            );
            $dimensions[] = self::dimensionFromEval($id, $meta, $eval, $totalMat, $city, $filters);

            if ($eval['has_issue']) {
                $rows = is_array($eval['rows'] ?? null) ? $eval['rows'] : [];
                if ($id === 'recurso_prova_sem_nee' && $rows !== []) {
                    $rows = InclusionRecursoProvaQueries::enriquecerLinhasEscolaComTiposRecurso(
                        $db,
                        $city,
                        $filters,
                        $rows,
                    );
                }
                $issueRowsById[$id] = $rows;
            }
        }

        $neePayload = self::neeSubnotificacaoEval($db, $city, $filters, $totalMat, $catalog);
        if ($neePayload !== null) {
            [$neeMeta, $neeEval] = $neePayload;
            $dimensions[] = self::dimensionFromEval('nee_subnotificacao', $neeMeta, $neeEval, $totalMat, $city, $filters);
            if ($neeEval['has_issue'] && is_array($neeEval['rows'] ?? null) && $neeEval['rows'] !== []) {
                $issueRowsById['nee_subnotificacao'] = $neeEval['rows'];
            }
        }

        $networkKpis = null;
        try {
            $networkKpis = MatriculaChartQueries::redeVagasResumoKpis($db, $city, $filters);
        } catch (\Throwable) {
            $networkKpis = null;
        }

        $dimensions = ConsultoriaOperationalSignals::append(
            $dimensions,
            $networkKpis,
            $totalMat,
            $city,
            $filters,
            $fundebAnchorAno,
        );

        $aee = InclusionDashboardQueries::buildAeeCrossEnrollment($db, $city, $filters);
        if (is_array($aee) && (int) ($aee['nee_matriculas_total'] ?? 0) > 0) {
            $neeTotal = (int) $aee['nee_matriculas_total'];
            $emAee = (int) ($aee['matriculas_em_turmas_aee'] ?? 0);
            $semAee = max(0, $neeTotal - $emAee);
            if ($semAee > 0 && ! isset($issueRowsById['nee_sem_aee'])) {
                $notes[] = __('Cruzamento AEE (rede): :n matrícula(s) NEE sem turma AEE identificada.', [
                    'n' => number_format($semAee, 0, ',', '.'),
                ]);
            }
        }

        return [
            'dimensions' => $dimensions,
            'total_matriculas' => $totalMat,
            'issue_rows_by_id' => $issueRowsById,
            'notes' => $notes,
        ];
    }

    public static function resolveFundebAnchorAno(IeducarFilterState $filters): int
    {
        if ($filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (int) $filters->yearFilterValue();
        }

        return FundebOpenDataImportService::suggestedImportYear();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{availability: string, has_issue: bool, rows?: list<array<string, mixed>>, unavailable_reason?: ?string}  $eval
     * @return array<string, mixed>
     */
    private static function dimensionFromEval(
        string $id,
        array $meta,
        array $eval,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
    ): array {
        return DiscrepanciesRoutineMetrics::dimensionFromEval($id, $meta, $eval, $totalMat, $city, $filters);
    }

    /**
     * Sempre inclui a rotina NEE (como no probe admin) para o hub modular listar o mesmo conjunto.
     *
     * @param  array<string, array<string, mixed>>  $catalog
     * @return ?array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private static function neeSubnotificacaoEval(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $totalMat,
        array $catalog,
    ): ?array {
        if (! isset($catalog['nee_subnotificacao'])) {
            return null;
        }

        $meta = $catalog['nee_subnotificacao'];
        $neeRow = DiscrepanciesQueries::neeSubnotificacaoEstimativaPorRede($db, $city, $filters, $totalMat);

        if ($neeRow !== null) {
            $m = is_array($neeRow['meta'] ?? null) ? $neeRow['meta'] : [];
            $meta['explanation'] = __(
                'A rede tem :nee matrícula(s) NEE (:pct% do total), abaixo do patamar de referência de :bench% (configurável). Estimativa de :gap registro(s) possivelmente omitidos — indicador de subnotificação no Censo e no VAAR de inclusão.',
                [
                    'nee' => number_format((int) ($m['nee_matriculas'] ?? 0), 0, ',', '.'),
                    'pct' => number_format((float) ($m['pct_atual'] ?? 0), 1, ',', '.'),
                    'bench' => number_format((float) ($m['benchmark_pct'] ?? 0), 1, ',', '.'),
                    'gap' => number_format((int) ($neeRow['total'] ?? 0), 0, ',', '.'),
                ]
            );

            return [
                $meta,
                [
                    'availability' => 'available',
                    'has_issue' => true,
                    'rows' => [$neeRow],
                    'unavailable_reason' => null,
                ],
            ];
        }

        return [
            $meta,
            [
                'availability' => 'unavailable',
                'has_issue' => false,
                'rows' => [],
                'unavailable_reason' => __('Benchmark NEE não aplicável neste filtro (denominador ou patamar).'),
            ],
        ];
    }
}
