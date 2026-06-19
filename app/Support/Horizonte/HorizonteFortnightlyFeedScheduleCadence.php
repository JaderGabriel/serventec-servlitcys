<?php

namespace App\Support\Horizonte;

/** Cadência bimestral do abastecimento Horizonte (agendador Laravel). */
final class HorizonteFortnightlyFeedScheduleCadence
{
    public static function day(): int
    {
        return max(1, min(28, (int) config('horizonte.fortnightly_feed.schedule.day', 1)));
    }

    /**
     * @return list<int>
     */
    public static function months(): array
    {
        $raw = config('horizonte.fortnightly_feed.schedule.months', [1, 3, 5, 7, 9, 11]);
        if (! is_array($raw)) {
            $raw = array_map('intval', explode(',', (string) $raw));
        }

        $months = array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $m): bool => $m >= 1 && $m <= 12)));
        sort($months);

        return $months !== [] ? $months : [1, 3, 5, 7, 9, 11];
    }

    public static function time(): string
    {
        return trim((string) config('horizonte.fortnightly_feed.schedule.time', '03:00')) ?: '03:00';
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

        return __('Bimestral — dia :day às :time (meses :months)', [
            'day' => (string) self::day(),
            'time' => self::time(),
            'months' => $monthsLabel,
        ]);
    }
}
