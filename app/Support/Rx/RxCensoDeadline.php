<?php

namespace App\Support\Rx;

use Illuminate\Support\Carbon;

/**
 * Prazo visual do Censo Escolar para o ano vigente do painel RX.
 */
final class RxCensoDeadline
{
    /**
     * @return array{
     *   ano: int,
     *   collect_end: string,
     *   validate_end: ?string,
     *   collect_end_label: string,
     *   days_remaining: int,
     *   total_days_window: int,
     *   elapsed_pct: float,
     *   urgency: 'ok'|'warning'|'danger'|'past',
     *   status_label: string,
     *   message: string
     * }
     */
    public static function forYear(?int $year = null): array
    {
        $ano = $year ?? (int) config('rx.vigente_year', (int) date('Y'));
        $deadlines = config('rx.censo_deadlines', []);
        $row = is_array($deadlines) ? ($deadlines[$ano] ?? $deadlines[(string) $ano] ?? null) : null;

        $collectEnd = is_array($row) && filled($row['collect_end'] ?? null)
            ? (string) $row['collect_end']
            : $ano.'-'.(string) config('rx.censo_collect_end_default', '06-30');

        $validateEnd = is_array($row) && filled($row['validate_end'] ?? null)
            ? (string) $row['validate_end']
            : null;

        $end = Carbon::parse($collectEnd)->endOfDay();
        $start = Carbon::create($ano, 1, 1)->startOfDay();
        $now = Carbon::now();
        $totalDays = max(1, (int) $start->diffInDays($end) + 1);
        $daysRemaining = (int) $now->diffInDays($end, false);

        if ($daysRemaining < 0) {
            $urgency = 'past';
            $statusLabel = __('Prazo encerrado');
            $message = __('A janela de preenchimento do Censo :ano terminou em :data.', [
                'ano' => (string) $ano,
                'data' => $end->format('d/m/Y'),
            ]);
            $elapsedPct = 100.0;
        } elseif ($daysRemaining <= 14) {
            $urgency = 'danger';
            $statusLabel = __('Prazo crítico');
            $message = __('Faltam :d dia(s) para o prazo do Censo :ano (:data).', [
                'd' => (string) $daysRemaining,
                'ano' => (string) $ano,
                'data' => $end->format('d/m/Y'),
            ]);
            $elapsedPct = min(100.0, round(100.0 * ($totalDays - $daysRemaining) / $totalDays, 1));
        } elseif ($daysRemaining <= 45) {
            $urgency = 'warning';
            $statusLabel = __('Atenção ao prazo');
            $message = __('Restam :d dias até :data (Censo :ano).', [
                'd' => (string) $daysRemaining,
                'data' => $end->format('d/m/Y'),
                'ano' => (string) $ano,
            ]);
            $elapsedPct = min(100.0, round(100.0 * ($totalDays - $daysRemaining) / $totalDays, 1));
        } else {
            $urgency = 'ok';
            $statusLabel = __('Dentro do prazo');
            $message = __(':d dias até o prazo do Censo :ano (:data).', [
                'd' => (string) $daysRemaining,
                'ano' => (string) $ano,
                'data' => $end->format('d/m/Y'),
            ]);
            $elapsedPct = min(100.0, round(100.0 * ($totalDays - $daysRemaining) / $totalDays, 1));
        }

        return [
            'ano' => $ano,
            'collect_end' => $collectEnd,
            'validate_end' => $validateEnd,
            'collect_end_label' => $end->format('d/m/Y'),
            'days_remaining' => max(0, $daysRemaining),
            'total_days_window' => $totalDays,
            'elapsed_pct' => $elapsedPct,
            'urgency' => $urgency,
            'status_label' => $statusLabel,
            'message' => $message,
        ];
    }
}
