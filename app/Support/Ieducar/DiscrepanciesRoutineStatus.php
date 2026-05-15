<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Estados visuais das rotinas de discrepância / consultoria.
 *
 * - unavailable: schema ou probe impede execução
 * - no_data: rotina executável, mas sem universo de análise no filtro actual
 * - ok: dados analisados, sem pendência
 * - warning / danger: pendência detectada
 */
final class DiscrepanciesRoutineStatus
{
    public const UNAVAILABLE = 'unavailable';

    public const NO_DATA = 'no_data';

    public const OK = 'ok';

    /** Rotinas que podem analisar algo sem matrículas activas no filtro. */
    private const NON_MATRICULA_SCOPED = [
        'escola_sem_geo',
    ];

    /**
     * @param  array{
     *   availability: string,
     *   has_issue: bool,
     *   rows?: list<array<string, mixed>>,
     *   unavailable_reason?: ?string
     * }  $eval
     * @return array{
     *   status: string,
     *   status_label: string,
     *   status_hint: ?string,
     *   availability: string
     * }
     */
    public static function resolve(
        string $checkId,
        array $eval,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
        string $severity = 'warning',
    ): array {
        $availability = (string) ($eval['availability'] ?? self::UNAVAILABLE);
        $hasIssue = (bool) ($eval['has_issue'] ?? false);

        if ($availability === self::UNAVAILABLE) {
            return [
                'status' => self::UNAVAILABLE,
                'status_label' => __('Indisponível'),
                'status_hint' => $eval['unavailable_reason'] ?? __('Rotina indisponível nesta base.'),
                'availability' => self::UNAVAILABLE,
            ];
        }

        if ($hasIssue) {
            $status = $severity === 'danger' ? 'danger' : 'warning';

            return [
                'status' => $status,
                'status_label' => $severity === 'danger' ? __('Pendência crítica') : __('Pendência'),
                'status_hint' => __('Cadastro analisado no filtro; foram encontradas ocorrências.'),
                'availability' => 'available',
            ];
        }

        $noData = self::noDataReason($checkId, $totalMat, $city, $filters);
        if ($noData !== null) {
            return [
                'status' => self::NO_DATA,
                'status_label' => __('Sem dados para analisar'),
                'status_hint' => $noData,
                'availability' => 'no_data',
            ];
        }

        return [
            'status' => self::OK,
            'status_label' => __('Sem pendência'),
            'status_hint' => $totalMat > 0
                ? __('Cadastro verificado no filtro; nenhuma ocorrência desta rotina.')
                : __('Universo de análise verificado; nenhuma ocorrência desta rotina.'),
            'availability' => 'available',
        ];
    }

    public static function requiresMatriculasInScope(string $checkId): bool
    {
        return ! in_array($checkId, self::NON_MATRICULA_SCOPED, true);
    }

    private static function noDataReason(
        string $checkId,
        int $totalMat,
        City $city,
        IeducarFilterState $filters,
    ): ?string {
        if ($checkId === 'escola_sem_geo') {
            if ($totalMat > 0) {
                return null;
            }
            if (SchoolGeoPositionResolver::countCacheUnits(
                $city,
                $filters->escola_id !== null ? (int) $filters->escola_id : null,
            ) > 0) {
                return null;
            }

            return __('Sem matrículas no filtro e sem unidades em school_unit_geos para esta cidade. Sincronize geo ou seleccione ano/escola com cadastro.');
        }

        if (! self::requiresMatriculasInScope($checkId)) {
            return null;
        }

        if ($totalMat > 0) {
            return null;
        }

        if (! $filters->hasYearSelected()) {
            return __('Seleccione o ano letivo para analisar matrículas activas nesta rotina.');
        }

        return __('Não há matrículas activas no filtro (ano, escola, curso ou turno). A rotina não pôde verificar o cadastro — diferente de «sem pendência».');
    }

    /**
     * @return array{label: string, hint: string, chip: string, icon: string}
     */
    public static function presentation(string $status): array
    {
        return match ($status) {
            'danger' => [
                'label' => __('Pendência crítica'),
                'hint' => '',
                'chip' => 'border-red-400 bg-red-50 text-red-950 dark:bg-red-950/40 dark:text-red-100',
                'icon' => '✕',
            ],
            'warning' => [
                'label' => __('Pendência'),
                'hint' => '',
                'chip' => 'border-amber-400 bg-amber-50 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100',
                'icon' => '!',
            ],
            self::OK => [
                'label' => __('Sem pendência'),
                'hint' => '',
                'chip' => 'border-emerald-400 bg-emerald-50 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100',
                'icon' => '✓',
            ],
            self::NO_DATA => [
                'label' => __('Sem dados para analisar'),
                'hint' => '',
                'chip' => 'border-sky-400 bg-sky-50 text-sky-950 dark:bg-sky-950/40 dark:text-sky-100',
                'icon' => '○',
            ],
            default => [
                'label' => __('Indisponível'),
                'hint' => '',
                'chip' => 'border-slate-300 bg-slate-100 text-slate-600 dark:bg-slate-800/50 dark:text-slate-300',
                'icon' => '—',
            ],
        };
    }
}
