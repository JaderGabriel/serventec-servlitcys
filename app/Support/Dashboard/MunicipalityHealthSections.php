<?php

namespace App\Support\Dashboard;

/**
 * Modos do Diagnóstico: estratégico (defeito), completo ou progressivo (AJAX).
 */
final class MunicipalityHealthSections
{
    public const MODE_STRATEGIC = 'strategic';

    public const MODE_FULL = 'full';

    public const MODE_PROGRESSIVE = 'progressive';

    public const FUNDEB = 'fundeb';

    public const PROGRAMAS = 'programas';

    public const TEMATICO = 'tematico';

    /**
     * @return list<string>
     */
    public static function deferred(): array
    {
        return [
            self::FUNDEB,
            self::PROGRAMAS,
            self::TEMATICO,
        ];
    }

    public static function isValid(string $section): bool
    {
        return in_array($section, self::deferred(), true);
    }

    public static function mode(): string
    {
        $mode = strtolower(trim((string) config('analytics.municipality_health_mode', self::MODE_STRATEGIC)));

        return in_array($mode, [self::MODE_STRATEGIC, self::MODE_FULL, self::MODE_PROGRESSIVE], true)
            ? $mode
            : self::MODE_STRATEGIC;
    }

    public static function strategicEnabled(): bool
    {
        return self::mode() === self::MODE_STRATEGIC;
    }

    public static function progressiveEnabled(): bool
    {
        if (self::mode() === self::MODE_PROGRESSIVE) {
            return true;
        }

        if (self::mode() !== self::MODE_STRATEGIC) {
            return false;
        }

        return filter_var(
            config('analytics.municipality_health_progressive_sections', false),
            FILTER_VALIDATE_BOOL,
        );
    }
}
