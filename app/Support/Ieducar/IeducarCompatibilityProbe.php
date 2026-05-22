<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Relatório de compatibilidade da base i-Educar por município (rotinas e schema).
 */
final class IeducarCompatibilityProbe
{
    public const SCHEMA_PROBE_VERSION = '1.1';

    /**
     * Documento JSON para onboarding (`schema_probe.json`).
     *
     * @return array<string, mixed>
     */
    public static function exportDocument(Connection $db, City $city, ?IeducarFilterState $filters = null): array
    {
        $filters ??= new IeducarFilterState(ano_letivo: 'all', escola_id: null, curso_id: null, turno_id: null);

        return self::wrapExportEnvelope(self::report($db, $city, $filters), $city, $filters);
    }

    /**
     * @param  array{
     *   city_id: int,
     *   city_name: string,
     *   total_matriculas?: int,
     *   discrepancy_summary?: array<string, mixed>,
     *   recurso_prova_schema: array<string, mixed>,
     *   routines: list<array<string, mixed>>
     * }  $report
     * @return array<string, mixed>
     */
    public static function wrapExportEnvelope(array $report, City $city, IeducarFilterState $filters): array
    {
        $routines = is_array($report['routines'] ?? null) ? $report['routines'] : [];
        $available = 0;
        $withIssue = 0;
        foreach ($routines as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['availability'] ?? '') === 'available' || ($row['availability'] ?? '') === 'no_data') {
                $available++;
            }
            if (! empty($row['has_issue'])) {
                $withIssue++;
            }
        }

        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : [];
        $discSummary = is_array($report['discrepancy_summary'] ?? null) ? $report['discrepancy_summary'] : [];

        return [
            'schema_probe_version' => self::SCHEMA_PROBE_VERSION,
            'generated_at' => now()->toIso8601String(),
            'city' => [
                'id' => (int) $city->id,
                'name' => (string) $city->name,
                'ibge_municipio' => $city->ibge_municipio,
                'ieducar_schema' => $city->ieducar_schema,
            ],
            'filters' => [
                'ano_letivo' => $filters->ano_letivo,
                'escola_id' => $filters->escola_id,
                'curso_id' => $filters->curso_id,
                'turno_id' => $filters->turno_id,
            ],
            'summary' => array_merge([
                'total_matriculas' => (int) ($report['total_matriculas'] ?? 0),
                'routines_total' => count($routines),
                'routines_available' => $available,
                'routines_with_issue' => $withIssue,
                'recurso_prova_schema_available' => (bool) ($schema['available'] ?? false),
            ], $discSummary),
            'recurso_prova_schema' => $schema,
            'routines' => $routines,
        ];
    }

    /**
     * @return array{
     *   city_id: int,
     *   city_name: string,
     *   filters_label: string,
     *   total_matriculas: int,
     *   discrepancy_summary: array<string, mixed>,
     *   recurso_prova_schema: array<string, mixed>,
     *   routines: list<array<string, mixed>>
     * }
     */
    public static function report(Connection $db, City $city, ?IeducarFilterState $filters = null): array
    {
        $filters ??= new IeducarFilterState(ano_letivo: 'all', escola_id: null, curso_id: null, turno_id: null);
        $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
        $catalog = DiscrepanciesCheckCatalog::definitions();
        $queryMap = DiscrepanciesCheckRunner::queryMap();
        $routines = [];

        foreach ($catalog as $id => $meta) {
            if ($id === 'nee_subnotificacao') {
                continue;
            }
            $spec = $queryMap[$id] ?? null;
            if ($spec === null) {
                $routines[] = self::routineRowUnavailable($id, $meta);

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

            $routines[] = self::routineRow($id, $meta, $eval, $totalMat, $city, $filters);
        }

        if (isset($catalog['nee_subnotificacao'])) {
            $neeRow = DiscrepanciesQueries::neeSubnotificacaoEstimativaPorRede($db, $city, $filters, $totalMat);
            $neeEval = [
                'availability' => $neeRow !== null ? 'available' : 'unavailable',
                'has_issue' => $neeRow !== null,
                'rows' => $neeRow !== null ? [$neeRow] : [],
                'unavailable_reason' => $neeRow === null
                    ? __('Benchmark NEE não aplicável neste filtro (denominador ou patamar).')
                    : null,
            ];
            $routines[] = self::routineRow('nee_subnotificacao', $catalog['nee_subnotificacao'], $neeEval, $totalMat, $city, $filters);
        }

        return [
            'city_id' => (int) $city->id,
            'city_name' => (string) $city->name,
            'filters_label' => self::filtersLabel($filters),
            'total_matriculas' => $totalMat,
            'discrepancy_summary' => DiscrepanciesRoutineMetrics::summaryFromRoutines($routines, $totalMat),
            'recurso_prova_schema' => RecursoProvaSchemaResolver::resolve($db, $city),
            'routines' => $routines,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{availability: string, has_issue: bool, rows?: list<mixed>, unavailable_reason?: ?string}  $eval
     * @return array<string, mixed>
     */
    private static function routineRow(
        string $id,
        array $meta,
        array $eval,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $dimension = DiscrepanciesRoutineMetrics::dimensionFromEval($id, $meta, $eval, $totalMat, $city, $filters);
        $totals = DiscrepanciesRoutineMetrics::occurrenceTotals($eval);
        $presentation = DiscrepanciesRoutineStatus::presentation((string) $dimension['status']);

        return array_merge($dimension, [
            'escola_ids' => $totals['escola_ids'],
            'hint' => $dimension['status_hint'] ?? ($eval['unavailable_reason'] ?? null),
            'ui_status_class' => self::uiStatusClass((string) $dimension['status']),
            'correlacao_resumo' => self::correlacaoResumo($dimension, $totalMat),
            'presentation_chip' => $presentation['chip'] ?? '',
            'presentation_icon' => $presentation['icon'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function routineRowUnavailable(string $id, array $meta): array
    {
        return [
            'id' => $id,
            'title' => (string) ($meta['title'] ?? $id),
            'availability' => 'unavailable',
            'status' => DiscrepanciesRoutineStatus::UNAVAILABLE,
            'status_label' => __('Indisponível'),
            'has_issue' => false,
            'schools_count' => 0,
            'occurrences_total' => 0,
            'total' => 0,
            'row_count' => 0,
            'pct_rede' => null,
            'perda_estimada_anual' => 0.0,
            'ganho_potencial_anual' => 0.0,
            'analyzed' => false,
            'hint' => __('Rotina não implementada.'),
            'ui_status_class' => 'text-gray-500 dark:text-gray-400',
            'correlacao_resumo' => null,
            'escola_ids' => [],
        ];
    }

    private static function filtersLabel(IeducarFilterState $filters): string
    {
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos letivos (consolidado)');
        }
        if ($filters->hasYearSelected()) {
            return __('Ano letivo :ano', ['ano' => $filters->ano_letivo]);
        }

        return __('Ano letivo não selecionado');
    }

    private static function uiStatusClass(string $status): string
    {
        return match ($status) {
            'danger' => 'text-red-700 dark:text-red-300',
            'warning' => 'text-amber-700 dark:text-amber-300',
            DiscrepanciesRoutineStatus::OK => 'text-emerald-700 dark:text-emerald-300',
            DiscrepanciesRoutineStatus::NO_DATA => 'text-sky-700 dark:text-sky-300',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }

    /**
     * @param  array<string, mixed>  $dimension
     */
    private static function correlacaoResumo(array $dimension, int $totalMat): ?string
    {
        if (! ($dimension['has_issue'] ?? false)) {
            return null;
        }

        $occ = (int) ($dimension['occurrences_total'] ?? 0);
        $schools = (int) ($dimension['schools_count'] ?? 0);
        $pct = $dimension['pct_rede'] ?? null;
        $perda = (float) ($dimension['perda_estimada_anual'] ?? 0);

        $parts = [
            __(':occ ocorrência(s) em :esc escola(s)', [
                'occ' => number_format($occ, 0, ',', '.'),
                'esc' => number_format($schools, 0, ',', '.'),
            ]),
        ];

        if ($pct !== null && $totalMat > 0) {
            $parts[] = __(':pct% das matrículas do filtro', ['pct' => number_format((float) $pct, 1, ',', '.')]);
        }

        if ($perda > 0) {
            $parts[] = __('perda est. :v/ano', ['v' => DiscrepanciesFundingImpact::formatBrl($perda)]);
        }

        return implode(' · ', $parts);
    }
}
