<?php

namespace App\Support\Cadunico;

/** Cadência bimestral do sync CUN-04 (PBF/NBF/BPC Portal). */
final class CadunicoBeneficiosPortalScheduleCadence
{
    public static function day(): int
    {
        return max(1, min(28, (int) config('ieducar.cadunico.beneficios_portal.schedule.day', 9)));
    }

    /**
     * @return list<int>
     */
    public static function months(): array
    {
        $raw = config('ieducar.cadunico.beneficios_portal.schedule.months', [1, 3, 5, 7, 9, 11]);
        $months = array_values(array_filter(array_map('intval', is_array($raw) ? $raw : [])));
        $months = array_values(array_filter($months, static fn (int $m): bool => $m >= 1 && $m <= 12));

        return $months !== [] ? $months : [1, 3, 5, 7, 9, 11];
    }

    public static function time(): string
    {
        return trim((string) config('ieducar.cadunico.beneficios_portal.schedule.time', '05:30')) ?: '05:30';
    }

    public static function cronExpression(): string
    {
        [$hour, $minute] = array_pad(explode(':', self::time()), 2, '0');
        $h = max(0, min(23, (int) $hour));
        $m = max(0, min(59, (int) $minute));
        $day = self::day();
        $months = implode(',', self::months());

        return sprintf('%d %d %d %s *', $m, $h, $day, $months);
    }
}
