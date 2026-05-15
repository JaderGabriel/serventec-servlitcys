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
    public const SCHEMA_PROBE_VERSION = '1.0';

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
            if (($row['availability'] ?? '') === 'available') {
                $available++;
            }
            if (! empty($row['has_issue'])) {
                $withIssue++;
            }
        }

        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : [];

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
            'summary' => [
                'total_matriculas' => (int) ($report['total_matriculas'] ?? 0),
                'routines_total' => count($routines),
                'routines_available' => $available,
                'routines_with_issue' => $withIssue,
                'recurso_prova_schema_available' => (bool) ($schema['available'] ?? false),
            ],
            'recurso_prova_schema' => $schema,
            'routines' => $routines,
        ];
    }

    /**
     * @return array{
     *   city_id: int,
     *   city_name: string,
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
            $spec = $queryMap[$id] ?? null;
            if ($spec === null) {
                $routines[] = [
                    'id' => $id,
                    'title' => (string) ($meta['title'] ?? $id),
                    'availability' => 'unavailable',
                    'has_issue' => false,
                    'row_count' => 0,
                    'hint' => __('Rotina não implementada.'),
                ];

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

            $resolved = DiscrepanciesRoutineStatus::resolve(
                $id,
                $eval,
                $totalMat,
                $city,
                $filters,
                (string) ($meta['severity'] ?? 'warning'),
            );

            $routines[] = self::routineRow($id, $meta, $eval, $resolved);
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
            $resolved = DiscrepanciesRoutineStatus::resolve(
                'nee_subnotificacao',
                $neeEval,
                $totalMat,
                $city,
                $filters,
                (string) ($catalog['nee_subnotificacao']['severity'] ?? 'warning'),
            );
            $routines[] = self::routineRow('nee_subnotificacao', $catalog['nee_subnotificacao'], $neeEval, $resolved);
        }

        return [
            'city_id' => (int) $city->id,
            'city_name' => (string) $city->name,
            'total_matriculas' => $totalMat,
            'recurso_prova_schema' => RecursoProvaSchemaResolver::resolve($db, $city),
            'routines' => $routines,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{availability: string, has_issue: bool, rows?: list<mixed>, unavailable_reason?: ?string}  $eval
     * @param  array{status: string, status_label: string, status_hint: ?string, availability: string}  $resolved
     * @return array<string, mixed>
     */
    private static function routineRow(string $id, array $meta, array $eval, array $resolved): array
    {
        return [
            'id' => $id,
            'title' => (string) ($meta['title'] ?? $id),
            'availability' => (string) $resolved['availability'],
            'status' => (string) $resolved['status'],
            'status_label' => (string) $resolved['status_label'],
            'has_issue' => (bool) ($eval['has_issue'] ?? false),
            'row_count' => count($eval['rows'] ?? []),
            'hint' => $resolved['status_hint'] ?? ($eval['unavailable_reason'] ?? null),
        ];
    }
}
