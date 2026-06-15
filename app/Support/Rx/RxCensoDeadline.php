<?php

namespace App\Support\Rx;

use Illuminate\Support\Carbon;

/**
 * Prazo visual do Censo Escolar para o ano vigente do painel RX (por fase do calendário INEP).
 */
final class RxCensoDeadline
{
    /**
     * @return array<string, mixed>
     */
    public static function forYear(?int $year = null): array
    {
        $calendar = RxCensoCalendar::forYear($year);
        $ano = is_array($calendar) ? (int) ($calendar['ano'] ?? $year ?? date('Y')) : ($year ?? (int) date('Y'));

        if (! is_array($calendar)) {
            return self::legacySimpleDeadline($ano);
        }

        $now = Carbon::now();
        $phase = self::resolvePhase($calendar, $now);

        return [
            'ano' => $ano,
            'phase' => $phase['key'],
            'phase_label' => $phase['label'],
            'phase_note' => $phase['note'],
            'collect_end' => $phase['window_end_iso'],
            'collect_end_label' => RxCensoCalendar::formatDate($phase['window_end_iso']),
            'collect_start_label' => RxCensoCalendar::formatDate($phase['window_start_iso']),
            'reference_date_label' => RxCensoCalendar::formatDate((string) ($calendar['reference_date'] ?? '')),
            'validate_end' => is_array($calendar['stage1'] ?? null)
                ? ($calendar['stage1']['rectification_end'] ?? null)
                : null,
            'days_remaining' => max(0, $phase['days_remaining']),
            'total_days_window' => max(1, $phase['total_days']),
            'elapsed_pct' => $phase['elapsed_pct'],
            'urgency' => $phase['urgency'],
            'status_label' => $phase['status_label'],
            'message' => $phase['message'],
            'countdown_label' => $phase['countdown_label'],
            'next_milestone_label' => $phase['next_milestone_label'] ?? '',
            'next_milestone_date' => $phase['next_milestone_date_label'] ?? '',
            'portaria' => (string) ($calendar['portaria'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $calendar
     * @return array{
     *   key: string,
     *   label: string,
     *   note: string,
     *   window_start_iso: string,
     *   window_end_iso: string,
     *   days_remaining: int,
     *   total_days: int,
     *   elapsed_pct: float,
     *   urgency: string,
     *   status_label: string,
     *   message: string,
     *   countdown_label: string,
     *   next_milestone_label?: string,
     *   next_milestone_date_label?: string
     * }
     */
    private static function resolvePhase(array $calendar, Carbon $now): array
    {
        $s1 = is_array($calendar['stage1'] ?? null) ? $calendar['stage1'] : [];
        $s2 = is_array($calendar['stage2'] ?? null) ? $calendar['stage2'] : [];

        $ref = self::parseStart((string) ($calendar['reference_date'] ?? $s1['collect_start'] ?? ''));
        $s1Start = self::parseStart((string) ($s1['collect_start'] ?? $calendar['reference_date'] ?? ''));
        $s1End = self::parseEnd((string) ($s1['collect_end'] ?? ''));
        $prelim = filled($s1['prelim_dou'] ?? null) ? self::parseStart((string) $s1['prelim_dou']) : null;
        $rectEnd = filled($s1['rectification_end'] ?? null)
            ? self::parseEnd((string) $s1['rectification_end'])
            : ($prelim?->copy()->addDays(max(1, (int) ($s1['rectification_days'] ?? 30)))->endOfDay());
        $s2Start = filled($s2['collect_start'] ?? null) ? self::parseStart((string) $s2['collect_start']) : null;
        $s2End = filled($s2['collect_end'] ?? null) ? self::parseEnd((string) $s2['collect_end']) : null;

        if ($ref && $now->lt($ref)) {
            return self::buildPhase(
                key: 'before_reference',
                label: __('Antes da data de referência'),
                note: __('Organize o cadastro no i-Educar para refletir a situação na data-base do Censo.'),
                windowStart: $now->copy()->startOfYear(),
                windowEnd: $ref,
                targetEnd: $ref,
                now: $now,
                countdownLabel: __('dias até a data de referência'),
                statusWhenOk: __('Preparação'),
                messageOk: __('Faltam :d dia(s) para a data de referência do Censo (:data). Revise turmas e matrículas no i-Educar.', [
                    'data' => RxCensoCalendar::formatDate($ref->format('Y-m-d')),
                ]),
                nextLabel: __('Início da 1ª etapa'),
                nextDate: $s1Start?->format('Y-m-d'),
            );
        }

        if ($s1End && $now->lte($s1End)) {
            return self::buildPhase(
                key: 'stage1_collect',
                label: (string) ($s1['label'] ?? __('1ª etapa — Matrícula inicial')),
                note: __('Período oficial de coleta no Educacenso. Exporte e valide escola a escola.'),
                windowStart: $s1Start ?? $ref ?? $now->copy()->startOfYear(),
                windowEnd: $s1End,
                targetEnd: $s1End,
                now: $now,
                countdownLabel: __('dias até o fim da coleta'),
                statusWhenOk: __('Coleta em andamento'),
                messageOk: __('Restam :d dia(s) para encerrar a 1ª etapa (:data). Priorize exportação e pendências do Censo na tabela abaixo.', [
                    'data' => RxCensoCalendar::formatDate($s1End->format('Y-m-d')),
                ]),
                nextLabel: __('Publicação preliminar (DOU)'),
                nextDate: $prelim?->format('Y-m-d'),
            );
        }

        if ($prelim && $rectEnd && $now->lt($prelim)) {
            return self::buildPhase(
                key: 'awaiting_rectification',
                label: __('Aguardando retificação'),
                note: __('Coleta da 1ª etapa encerrada. Aguarde a publicação preliminar no DOU para a janela de correção.'),
                windowStart: $s1End ?? $now,
                windowEnd: $prelim,
                targetEnd: $prelim,
                now: $now,
                countdownLabel: __('dias até abertura da retificação'),
                statusWhenOk: __('Conferência pendente'),
                messageOk: __('A retificação abre após o DOU (:data). Use este período para auditoria interna e duplicidades de matrícula.', [
                    'data' => RxCensoCalendar::formatDate($prelim->format('Y-m-d')),
                ]),
                nextLabel: __('Início da retificação'),
                nextDate: $prelim->format('Y-m-d'),
            );
        }

        if ($rectEnd && $now->lte($rectEnd)) {
            return self::buildPhase(
                key: 'stage1_rectification',
                label: __('Retificação da 1ª etapa'),
                note: __('Janela de 30 dias para conferir, ratificar e corrigir dados declarados na coleta inicial.'),
                windowStart: $prelim ?? $s1End ?? $now,
                windowEnd: $rectEnd,
                targetEnd: $rectEnd,
                now: $now,
                countdownLabel: __('dias restantes de retificação'),
                statusWhenOk: __('Retificação aberta'),
                messageOk: __('Faltam :d dia(s) para encerrar a retificação (:data). Confirme duplicidades e ajustes no Educacenso.', [
                    'data' => RxCensoCalendar::formatDate($rectEnd->format('Y-m-d')),
                ]),
                nextLabel: __('2ª etapa — Situação do aluno'),
                nextDate: $s2Start?->format('Y-m-d'),
            );
        }

        if ($s2Start && $s2End && $now->lt($s2Start)) {
            return self::buildPhase(
                key: 'between_stages',
                label: __('Entre etapas'),
                note: __('1ª etapa concluída. Prepare rendimento e movimento escolar para a 2ª etapa.'),
                windowStart: $rectEnd ?? $s1End ?? $now,
                windowEnd: $s2Start,
                targetEnd: $s2Start,
                now: $now,
                countdownLabel: __('dias até a 2ª etapa'),
                statusWhenOk: __('Intervalo entre etapas'),
                messageOk: __('A 2ª etapa (Situação do aluno) abre em :data. Cadastre apenas alunos já declarados na 1ª etapa.', [
                    'data' => RxCensoCalendar::formatDate($s2Start->format('Y-m-d')),
                ]),
                nextLabel: (string) ($s2['label'] ?? __('2ª etapa')),
                nextDate: $s2Start->format('Y-m-d'),
            );
        }

        if ($s2Start && $s2End && $now->lte($s2End)) {
            return self::buildPhase(
                key: 'stage2_collect',
                label: (string) ($s2['label'] ?? __('2ª etapa — Situação do aluno')),
                note: __('Informe aprovação, reprovação, abandono e transferência dos estudantes da 1ª etapa.'),
                windowStart: $s2Start,
                windowEnd: $s2End,
                targetEnd: $s2End,
                now: $now,
                countdownLabel: __('dias até o fim da 2ª etapa'),
                statusWhenOk: __('2ª etapa em andamento'),
                messageOk: __('Restam :d dia(s) para encerrar a Situação do aluno (:data).', [
                    'data' => RxCensoCalendar::formatDate($s2End->format('Y-m-d')),
                ]),
            );
        }

        $lastEnd = $s2End ?? $rectEnd ?? $s1End ?? $now;

        return self::buildPhase(
            key: 'closed',
            label: __('Calendário encerrado'),
            note: __('O ciclo do Censo :ano foi concluído. Monitore o próximo exercício e mantenha o i-Educar atualizado.', [
                'ano' => (string) ($calendar['ano'] ?? ''),
            ]),
            windowStart: $s1Start ?? $now->copy()->startOfYear(),
            windowEnd: $lastEnd,
            targetEnd: $lastEnd,
            now: $now,
            countdownLabel: __('prazo encerrado'),
            statusWhenOk: __('Encerrado'),
            messageOk: __('A janela operacional do Censo :ano terminou. Consulte o toolkit para o próximo exercício.', [
                'ano' => (string) ($calendar['ano'] ?? ''),
            ]),
            forcePast: true,
        );
    }

  /**
     * @return array<string, mixed>
     */
    private static function buildPhase(
        string $key,
        string $label,
        string $note,
        Carbon $windowStart,
        Carbon $windowEnd,
        Carbon $targetEnd,
        Carbon $now,
        string $countdownLabel,
        string $statusWhenOk,
        string $messageOk,
        ?string $nextLabel = null,
        ?string $nextDate = null,
        bool $forcePast = false,
    ): array {
        $totalDays = max(1, (int) $windowStart->copy()->startOfDay()->diffInDays($windowEnd->copy()->startOfDay()) + 1);
        $daysRemaining = (int) $now->copy()->startOfDay()->diffInDays($targetEnd->copy()->startOfDay(), false);
        $elapsedDays = max(0, $totalDays - max(0, $daysRemaining));
        $elapsedPct = min(100.0, round(100.0 * $elapsedDays / $totalDays, 1));

        if ($forcePast || $daysRemaining < 0) {
            return [
                'key' => $key,
                'label' => $label,
                'note' => $note,
                'window_start_iso' => $windowStart->format('Y-m-d'),
                'window_end_iso' => $targetEnd->format('Y-m-d'),
                'days_remaining' => 0,
                'total_days' => $totalDays,
                'elapsed_pct' => 100.0,
                'urgency' => 'past',
                'status_label' => __('Prazo encerrado'),
                'message' => $messageOk,
                'countdown_label' => $countdownLabel,
                'next_milestone_label' => $nextLabel ?? '',
                'next_milestone_date_label' => $nextDate !== null ? RxCensoCalendar::formatDate($nextDate) : '',
            ];
        }

        $urgency = match (true) {
            $daysRemaining <= 14 => 'danger',
            $daysRemaining <= 45 => 'warning',
            default => 'ok',
        };

        $statusLabel = match ($urgency) {
            'danger' => __('Prazo crítico'),
            'warning' => __('Atenção ao prazo'),
            default => $statusWhenOk,
        };

        $message = str_replace(':d', (string) $daysRemaining, $messageOk);

        return [
            'key' => $key,
            'label' => $label,
            'note' => $note,
            'window_start_iso' => $windowStart->format('Y-m-d'),
            'window_end_iso' => $targetEnd->format('Y-m-d'),
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
            'elapsed_pct' => $elapsedPct,
            'urgency' => $urgency,
            'status_label' => $statusLabel,
            'message' => $message,
            'countdown_label' => $countdownLabel,
            'next_milestone_label' => $nextLabel ?? '',
            'next_milestone_date_label' => $nextDate !== null ? RxCensoCalendar::formatDate($nextDate) : '',
        ];
    }

    private static function parseStart(string $iso): ?Carbon
    {
        if (! filled($iso)) {
            return null;
        }

        try {
            return Carbon::parse($iso)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseEnd(string $iso): ?Carbon
    {
        if (! filled($iso)) {
            return null;
        }

        try {
            return Carbon::parse($iso)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacySimpleDeadline(int $ano): array
    {
        $deadlines = config('rx.censo_deadlines', []);
        $row = is_array($deadlines) ? ($deadlines[$ano] ?? $deadlines[(string) $ano] ?? null) : null;

        $collectEnd = is_array($row) && filled($row['collect_end'] ?? null)
            ? (string) $row['collect_end']
            : $ano.'-'.(string) config('rx.censo_collect_end_default', '06-30');

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
            'phase' => 'legacy',
            'phase_label' => __('Censo Escolar'),
            'phase_note' => '',
            'collect_end' => $collectEnd,
            'validate_end' => is_array($row) ? ($row['validate_end'] ?? null) : null,
            'collect_end_label' => $end->format('d/m/Y'),
            'collect_start_label' => '',
            'reference_date_label' => '',
            'days_remaining' => max(0, $daysRemaining),
            'total_days_window' => $totalDays,
            'elapsed_pct' => $elapsedPct,
            'urgency' => $urgency,
            'status_label' => $statusLabel,
            'message' => $message,
            'countdown_label' => __('dias restantes'),
            'next_milestone_label' => '',
            'next_milestone_date' => '',
            'portaria' => '',
        ];
    }
}
