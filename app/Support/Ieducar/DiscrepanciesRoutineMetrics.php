<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Métricas por rotina alinhadas à aba Discrepâncias (ocorrências, escolas, impacto).
 */
final class DiscrepanciesRoutineMetrics
{
    /**
     * @param  array{availability: string, has_issue: bool, rows?: list<array<string, mixed>>}  $eval
     * @return array{schools_count: int, occurrences_total: int, escola_ids: list<string>}
     */
    public static function occurrenceTotals(array $eval): array
    {
        $rows = is_array($eval['rows'] ?? null) ? $eval['rows'] : [];
        $hasIssue = (bool) ($eval['has_issue'] ?? false);

        if (! $hasIssue || $rows === []) {
            return [
                'schools_count' => 0,
                'occurrences_total' => 0,
                'escola_ids' => [],
            ];
        }

        $escolaIds = [];
        $occurrences = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $occurrences += (int) ($row['total'] ?? 0);
            $eid = (string) ($row['escola_id'] ?? '');
            if ($eid !== '') {
                $escolaIds[$eid] = true;
            }
        }

        return [
            'schools_count' => count($rows),
            'occurrences_total' => $occurrences,
            'escola_ids' => array_keys($escolaIds),
        ];
    }

    /**
     * Campos de dimensão (mesma lógica que a aba Discrepâncias).
     *
     * @param  array<string, mixed>  $meta
     * @param  array{availability: string, has_issue: bool, rows?: list<array<string, mixed>>, unavailable_reason?: ?string}  $eval
     * @return array<string, mixed>
     */
    public static function dimensionFromEval(
        string $checkId,
        array $meta,
        array $eval,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $severity = (string) ($meta['severity'] ?? 'warning');
        $resolved = DiscrepanciesRoutineStatus::resolve($checkId, $eval, $totalMat, $city, $filters, $severity);
        $hasIssue = (bool) ($eval['has_issue'] ?? false);
        $totals = self::occurrenceTotals($eval);
        $occurrences = $totals['occurrences_total'];
        $status = (string) $resolved['status'];
        $analyzed = $status === DiscrepanciesRoutineStatus::OK
            || $status === 'warning'
            || $status === 'danger';

        $impactUnits = $checkId === 'escola_sem_geo' && $hasIssue
            ? max(1, $totals['schools_count'])
            : $occurrences;
        $pct = $totalMat > 0 && $hasIssue && $checkId !== 'escola_sem_geo'
            ? round(100.0 * $occurrences / $totalMat, 1)
            : null;
        $funding = $hasIssue
            ? DiscrepanciesFundingImpact::estimate($checkId, $impactUnits, $city, $filters)
            : null;

        $operationalNote = null;
        if ($checkId === 'escola_sem_geo' && $hasIssue) {
            $operationalNote = __(
                ':escolas escola(s) sem posição no mapa (critério alinhado a Cadastro → Unidades). :mat matrícula(s) nessas unidades.',
                [
                    'escolas' => number_format($totals['schools_count'], 0, ',', '.'),
                    'mat' => number_format($occurrences, 0, ',', '.'),
                ]
            );
        }

        return [
            'id' => $checkId,
            'title' => (string) ($meta['title'] ?? ''),
            'vaar_refs' => is_array($meta['vaar_refs'] ?? null) ? $meta['vaar_refs'] : [],
            'availability' => (string) $resolved['availability'],
            'has_issue' => $hasIssue,
            'detected' => $hasIssue,
            'analyzed' => $analyzed,
            'schools_count' => $totals['schools_count'],
            'occurrences_total' => $occurrences,
            'impact_units' => $impactUnits,
            'impact_unit_label' => $checkId === 'escola_sem_geo' ? __('escolas') : __('ocorrências'),
            'total' => $occurrences,
            'row_count' => $totals['schools_count'],
            'escola_ids' => $totals['escola_ids'],
            'pct_rede' => $pct,
            'operational_note' => $operationalNote,
            'correction_tab' => $checkId === 'escola_sem_geo' ? 'school_units' : null,
            'correction_label' => $checkId === 'escola_sem_geo' ? __('Abrir Unidades') : null,
            'ganho_potencial_anual' => (float) ($funding['ganho_potencial_anual'] ?? 0),
            'perda_estimada_anual' => (float) ($funding['perda_anual'] ?? 0),
            'funding_formula' => $funding['formula'] ?? null,
            'funding_explicacao' => $funding['explicacao'] ?? null,
            'status' => $status,
            'status_label' => (string) $resolved['status_label'],
            'status_hint' => $resolved['status_hint'] ?? null,
            'unavailable_reason' => $status === DiscrepanciesRoutineStatus::UNAVAILABLE
                ? ($eval['unavailable_reason'] ?? $resolved['status_hint'])
                : null,
            'severity' => $severity,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $routines
     * @return array{
     *   com_problema: int,
     *   corrigiveis: int,
     *   escolas_afetadas: int,
     *   perda_estimada_anual: float,
     *   ganho_potencial_anual: float,
     *   rotinas_com_pendencia: int,
     *   rotinas_analisadas: int
     * }
     */
    public static function summaryFromRoutines(array $routines, int $totalMat): array
    {
        $comProblema = 0;
        $perda = 0.0;
        $ganho = 0.0;
        $escolas = [];
        $comPendencia = 0;
        $analisadas = 0;

        foreach ($routines as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['analyzed'])) {
                $analisadas++;
            }
            if (! ($row['has_issue'] ?? false)) {
                continue;
            }
            $comPendencia++;
            $comProblema += (int) ($row['occurrences_total'] ?? $row['total'] ?? 0);
            $perda += (float) ($row['perda_estimada_anual'] ?? 0);
            $ganho += (float) ($row['ganho_potencial_anual'] ?? 0);
            foreach ($row['escola_ids'] ?? [] as $eid) {
                if ((string) $eid !== '') {
                    $escolas[(string) $eid] = true;
                }
            }
        }

        return [
            'com_problema' => $comProblema,
            'corrigiveis' => $comProblema,
            'escolas_afetadas' => count($escolas),
            'perda_estimada_anual' => round($perda, 2),
            'ganho_potencial_anual' => round($ganho, 2),
            'rotinas_com_pendencia' => $comPendencia,
            'rotinas_analisadas' => $analisadas,
            'total_matriculas' => $totalMat > 0 ? $totalMat : null,
        ];
    }
}
