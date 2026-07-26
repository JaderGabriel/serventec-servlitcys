<?php

namespace App\Support\Admin;

/**
 * Qualifica a saúde global do sistema a partir do Module Monitor + Pulse.
 *
 * @phpstan-type ModuleSummary array{total: int, healthy: int, warning: int, critical: int, unknown: int}
 */
final class ModuleMonitorSystemHealth
{
    /**
     * @param  ModuleSummary  $moduleSummary
     * @param  array<string, mixed>  $system
     * @param  array<string, mixed>|null  $horizonteKpi
     * @return array{
     *     score: int,
     *     grade: string,
     *     label: string,
     *     tone: string,
     *     summary: string,
     *     dimensions: list<array{id: string, label: string, score: int, detail: string}>
     * }
     */
    public static function qualify(
        array $moduleSummary,
        array $system,
        bool $pulseAvailable,
        bool $snapshotFresh,
        ?array $horizonteKpi = null,
    ): array {
        $total = max(1, (int) ($moduleSummary['total'] ?? 0));
        $critical = (int) ($moduleSummary['critical'] ?? 0);
        $warning = (int) ($moduleSummary['warning'] ?? 0);
        $unknown = (int) ($moduleSummary['unknown'] ?? 0);
        $healthy = (int) ($moduleSummary['healthy'] ?? 0);

        $modulesScore = (int) round(100 * ($healthy / $total));
        $modulesScore -= $critical * 12;
        $modulesScore -= $warning * 4;
        $modulesScore -= $unknown * 2;
        $modulesScore = max(0, min(100, $modulesScore));

        $systemStatus = (string) ($system['status'] ?? 'unknown');
        $infraScore = match ($systemStatus) {
            'healthy' => 100,
            'warning' => 70,
            'critical' => 35,
            default => 55,
        };
        if (($system['queue_connection'] ?? '') === 'sync' && app()->environment('production')) {
            $infraScore = min($infraScore, 50);
        }

        $telemetryScore = 100;
        if (! $pulseAvailable) {
            $telemetryScore -= 35;
        }
        if (! $snapshotFresh) {
            $telemetryScore -= 25;
        }
        $telemetryScore = max(0, $telemetryScore);

        $horizonteScore = 100;
        $horizonteDetail = __('Horizonte desactivado ou indisponível');
        if (is_array($horizonteKpi)) {
            $universe = max(1, (int) ($horizonteKpi['universe'] ?? 0));
            $triad = (int) ($horizonteKpi['triad'] ?? 0);
            $triadPct = (int) round(($triad / $universe) * 100);
            $phasesOk = (int) ($horizonteKpi['phases_ok'] ?? 0);
            $phasesTotal = max(1, (int) ($horizonteKpi['phases_total'] ?? 1));
            $phasePct = (int) round(($phasesOk / $phasesTotal) * 100);
            $horizonteScore = (int) round(($triadPct * 0.6) + ($phasePct * 0.4));
            $horizonteDetail = __('Triád :t% · fases :p%', ['t' => $triadPct, 'p' => $phasePct]);
        }

        $score = (int) round(
            ($modulesScore * 0.45)
            + ($infraScore * 0.25)
            + ($telemetryScore * 0.15)
            + ($horizonteScore * 0.15)
        );
        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };

        $tone = match ($grade) {
            'A', 'B' => 'emerald',
            'C' => 'amber',
            'D' => 'orange',
            default => 'rose',
        };

        $label = match ($grade) {
            'A' => __('Excelente'),
            'B' => __('Bom'),
            'C' => __('Aceitável'),
            'D' => __('Frágil'),
            default => __('Crítico'),
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'label' => $label,
            'tone' => $tone,
            'summary' => __(':healthy/:total módulos OK · :crit críticos · nota :grade (:score)', [
                'healthy' => $healthy,
                'total' => $total,
                'crit' => $critical,
                'grade' => $grade,
                'score' => $score,
            ]),
            'dimensions' => [
                [
                    'id' => 'modules',
                    'label' => __('Módulos'),
                    'score' => $modulesScore,
                    'detail' => __(':h saudáveis · :w atenção · :c críticos · :u por avaliar', [
                        'h' => $healthy,
                        'w' => $warning,
                        'c' => $critical,
                        'u' => $unknown,
                    ]),
                ],
                [
                    'id' => 'infra',
                    'label' => __('Infra / filas'),
                    'score' => $infraScore,
                    'detail' => (string) ($system['status_hint'] ?? $systemStatus),
                ],
                [
                    'id' => 'telemetry',
                    'label' => __('Telemetria'),
                    'score' => $telemetryScore,
                    'detail' => $pulseAvailable
                        ? ($snapshotFresh
                            ? __('Pulse activo · sondas actualizadas')
                            : __('Pulse activo · sondas desactualizadas'))
                        : __('Pulse indisponível'),
                ],
                [
                    'id' => 'horizonte',
                    'label' => __('Horizonte'),
                    'score' => $horizonteScore,
                    'detail' => $horizonteDetail,
                ],
            ],
        ];
    }
}
