<?php

namespace App\Support\Horizonte;

/** Cadência semestral da sincronização SICONFI (agendador Laravel). */
final class HorizonteSiconfiScheduleCadence
{
    public static function day(): int
    {
        return max(1, min(28, (int) config('horizonte.siconfi_sync.schedule.day', 15)));
    }

    /**
     * @return list<int>
     */
    public static function months(): array
    {
        $raw = config('horizonte.siconfi_sync.schedule.months', [1, 7]);
        if (! is_array($raw)) {
            $raw = array_map('intval', explode(',', (string) $raw));
        }

        $months = array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $m): bool => $m >= 1 && $m <= 12)));
        sort($months);

        return $months !== [] ? $months : [1, 7];
    }

    public static function time(): string
    {
        return trim((string) config('horizonte.siconfi_sync.schedule.time', '04:00')) ?: '04:00';
    }

    public static function cronExpression(): string
    {
        [$hour, $minute] = array_pad(explode(':', self::time()), 2, '0');

        return sprintf(
            '%d %d %d %s *',
            max(0, min(59, (int) $minute)),
            max(0, min(23, (int) $hour)),
            self::day(),
            implode(',', self::months()),
        );
    }

    public static function summary(): string
    {
        $monthsLabel = implode(', ', array_map(
            static fn (int $m): string => str_pad((string) $m, 2, '0', STR_PAD_LEFT),
            self::months(),
        ));

        return __('Semestral — dia :day às :time (meses :months)', [
            'day' => (string) self::day(),
            'time' => self::time(),
            'months' => $monthsLabel,
        ]);
    }
}
