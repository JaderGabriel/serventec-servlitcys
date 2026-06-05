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
            'matricula_count_diagnostics' => is_array($report['matricula_count_diagnostics'] ?? null)
                ? $report['matricula_count_diagnostics']
                : [],
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
    public static function report(
        Connection $db,
        City $city,
        ?IeducarFilterState $filters = null,
        ?int $fundebAnchorAno = null,
    ): array {
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

        try {
            $networkKpis = MatriculaChartQueries::redeVagasResumoKpis($db, $city, $filters);
        } catch (\Throwable) {
            $networkKpis = null;
        }
        $routines = ConsultoriaOperationalSignals::append($routines, $networkKpis, $totalMat, $city, $filters, $fundebAnchorAno);
        $operationalMeta = ConsultoriaOperationalSignals::operationalMeta();
        $routines = array_map(
            static fn (array $routine): array => self::ensureRoutinePresentation(
                $routine,
                $catalog[$routine['id'] ?? ''] ?? $operationalMeta[$routine['id'] ?? ''] ?? [],
                $totalMat,
                $city,
                $filters,
            ),
            $routines,
        );
        $routines = self::sortRoutinesForConsultoria($routines);
        $fundingRef = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        $modules = DiscrepanciesModuleCatalog::buildPanel($routines, []);

        return [
            'city_id' => (int) $city->id,
            'city_name' => (string) $city->name,
            'filters_label' => self::filtersLabel($filters),
            'total_matriculas' => $totalMat,
            'matricula_count_diagnostics' => MatriculaCountDiagnostics::snapshot($db, $city, $filters),
            'discrepancy_summary' => DiscrepanciesRoutineMetrics::summaryFromRoutines($routines, $totalMat),
            'funding_reference' => $fundingRef,
            'funding_metodologia' => DiscrepanciesFundingImpact::metodologiaResumo($city, $filters),
            'recurso_prova_schema' => RecursoProvaSchemaResolver::resolve($db, $city),
            'modules' => $modules,
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
        $severity = (string) ($meta['severity'] ?? 'warning');

        return array_merge($dimension, [
            'explanation' => (string) ($meta['explanation'] ?? ''),
            'impact' => (string) ($meta['impact'] ?? ''),
            'correction' => (string) ($meta['correction'] ?? ''),
            'is_erro' => $severity === 'danger' && ($dimension['has_issue'] ?? false),
            'consultoria_prioridade' => $severity === 'danger' ? __('Erro crítico') : __('Atenção'),
            'escola_ids' => $totals['escola_ids'],
            'hint' => $dimension['status_hint'] ?? ($eval['unavailable_reason'] ?? null),
            'ui_status_class' => self::uiStatusClass((string) $dimension['status']),
            'correlacao_resumo' => self::correlacaoResumo($dimension, $meta, $totalMat),
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
     * @param  array<string, mixed>  $meta
     */
    private static function correlacaoResumo(array $dimension, array $meta, int $totalMat): ?string
    {
        if (! ($dimension['has_issue'] ?? false)) {
            return null;
        }

        $id = (string) ($dimension['id'] ?? '');
        $occ = (int) ($dimension['occurrences_total'] ?? 0);
        $schools = (int) ($dimension['schools_count'] ?? 0);
        $pct = $dimension['pct_rede'] ?? null;
        $perda = (float) ($dimension['perda_estimada_anual'] ?? 0);

        if ($id === 'escola_sem_geo') {
            $parts = [
                __(':esc escola(s) sem posição no mapa', ['esc' => number_format($schools, 0, ',', '.')]),
            ];
            if ($occ > 0) {
                $parts[] = __(':mat matrícula(s) nessas unidades', ['mat' => number_format($occ, 0, ',', '.')]);
            }
            $parts[] = __('perda calculada por escola (alinhado a Unidades)');
        } else {
            $parts = [
                __(':occ ocorrência(s) em :esc escola(s)', [
                    'occ' => number_format($occ, 0, ',', '.'),
                    'esc' => number_format($schools, 0, ',', '.'),
                ]),
            ];
            if ($pct !== null && $totalMat > 0) {
                $parts[] = __(':pct% das matrículas do filtro', ['pct' => number_format((float) $pct, 1, ',', '.')]);
            }
        }

        if ($perda > 0) {
            $parts[] = __('perda est. :v/ano', ['v' => DiscrepanciesFundingImpact::formatBrl($perda)]);
        }

        $impact = trim((string) ($meta['impact'] ?? ''));
        if ($impact !== '') {
            $parts[] = \Illuminate\Support\Str::limit($impact, 120);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function ensureRoutinePresentation(
        array $routine,
        array $meta,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
    ): array {
        if (isset($routine['ui_status_class'])) {
            return $routine;
        }

        $id = (string) ($routine['id'] ?? '');
        if ($meta === [] && $id !== '') {
            $meta = [
                'id' => $id,
                'title' => (string) ($routine['title'] ?? $id),
                'severity' => (string) ($routine['severity'] ?? 'warning'),
            ];
        }

        $presentation = DiscrepanciesRoutineStatus::presentation((string) ($routine['status'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE));
        $severity = (string) ($meta['severity'] ?? $routine['severity'] ?? 'warning');
        $totals = DiscrepanciesRoutineMetrics::occurrenceTotals([
            'has_issue' => (bool) ($routine['has_issue'] ?? false),
            'rows' => ($routine['has_issue'] ?? false)
                ? [['escola_id' => '0', 'escola' => '', 'total' => (int) ($routine['occurrences_total'] ?? $routine['total'] ?? 0)]]
                : [],
        ]);

        return array_merge($routine, [
            'explanation' => (string) ($routine['explanation'] ?? $meta['explanation'] ?? ''),
            'impact' => (string) ($routine['impact'] ?? $meta['impact'] ?? ''),
            'correction' => (string) ($routine['correction'] ?? $meta['correction'] ?? ''),
            'is_erro' => $severity === 'danger' && ($routine['has_issue'] ?? false),
            'consultoria_prioridade' => $severity === 'danger' ? __('Erro crítico') : __('Atenção'),
            'escola_ids' => $routine['escola_ids'] ?? $totals['escola_ids'],
            'hint' => $routine['status_hint'] ?? $routine['hint'] ?? null,
            'ui_status_class' => self::uiStatusClass((string) ($routine['status'] ?? '')),
            'correlacao_resumo' => self::correlacaoResumo($routine, $meta, $totalMat),
            'presentation_chip' => $presentation['chip'] ?? '',
            'presentation_icon' => $presentation['icon'] ?? '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $routines
     * @return list<array<string, mixed>>
     */
    private static function sortRoutinesForConsultoria(array $routines): array
    {
        usort($routines, static function (array $a, array $b): int {
            $hasA = (bool) ($a['has_issue'] ?? false);
            $hasB = (bool) ($b['has_issue'] ?? false);
            if ($hasA !== $hasB) {
                return $hasB <=> $hasA;
            }

            $order = static fn (array $r): int => match ((string) ($r['status'] ?? '')) {
                'danger' => 0,
                'warning' => 1,
                default => 2,
            };
            $oa = $order($a);
            $ob = $order($b);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            $perdaCmp = ((float) ($b['perda_estimada_anual'] ?? 0)) <=> ((float) ($a['perda_estimada_anual'] ?? 0));
            if ($perdaCmp !== 0) {
                return $perdaCmp;
            }

            return ((int) ($b['occurrences_total'] ?? 0)) <=> ((int) ($a['occurrences_total'] ?? 0));
        });

        return $routines;
    }
}
