<?php

namespace App\Support\Rx;

use Illuminate\Support\Carbon;

/**
 * Calendário oficial do Censo Escolar / Educacenso por ano de referência (INEP).
 */
final class RxCensoCalendar
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forYear(?int $year = null): ?array
    {
        $ano = $year ?? (int) config('rx.vigente_year', (int) date('Y'));
        $calendars = config('rx.censo_calendar', []);
        $row = is_array($calendars) ? ($calendars[$ano] ?? $calendars[(string) $ano] ?? null) : null;

        if (! is_array($row)) {
            return self::fallbackFromLegacyDeadlines($ano);
        }

        $stage1 = is_array($row['stage1'] ?? null) ? $row['stage1'] : [];
        $stage2 = is_array($row['stage2'] ?? null) ? $row['stage2'] : [];

        $rectDays = max(1, (int) ($stage1['rectification_days'] ?? 30));
        $prelimDou = filled($stage1['prelim_dou'] ?? null) ? (string) $stage1['prelim_dou'] : null;
        $rectEnd = $prelimDou !== null
            ? Carbon::parse($prelimDou)->addDays($rectDays)->format('Y-m-d')
            : null;

        return [
            'ano' => $ano,
            'portaria' => (string) ($row['portaria'] ?? ''),
            'source_url' => (string) ($row['source_url'] ?? ''),
            'reference_date' => (string) ($row['reference_date'] ?? $stage1['collect_start'] ?? ''),
            'stage1' => [
                ...$stage1,
                'rectification_end' => $rectEnd,
            ],
            'stage2' => $stage2,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fallbackFromLegacyDeadlines(int $ano): ?array
    {
        $deadlines = config('rx.censo_deadlines', []);
        $row = is_array($deadlines) ? ($deadlines[$ano] ?? $deadlines[(string) $ano] ?? null) : null;

        if (! is_array($row) || ! filled($row['collect_end'] ?? null)) {
            return null;
        }

        $collectEnd = (string) $row['collect_end'];

        return [
            'ano' => $ano,
            'portaria' => '',
            'source_url' => 'https://www.gov.br/inep/pt-br/acesso-a-informacao/perguntas-frequentes/censo-escolar',
            'reference_date' => $ano.'-05-28',
            'stage1' => [
                'label' => __('1ª etapa — Matrícula inicial'),
                'collect_start' => $ano.'-05-28',
                'collect_end' => $collectEnd,
                'prelim_dou' => null,
                'rectification_days' => 30,
                'rectification_end' => null,
                'fundeb_send' => null,
                'results_final' => null,
            ],
            'stage2' => [],
        ];
    }

    public static function formatDate(?string $iso): string
    {
        if (! filled($iso)) {
            return '—';
        }

        try {
            return Carbon::parse($iso)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $iso;
        }
    }
}
