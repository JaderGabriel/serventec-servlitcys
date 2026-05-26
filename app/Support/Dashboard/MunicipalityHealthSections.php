<?php

namespace App\Support\Dashboard;

/**
 * Secções do Diagnóstico carregadas progressivamente (AJAX).
 */
final class MunicipalityHealthSections
{
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

    public static function progressiveEnabled(): bool
    {
        return filter_var(
            config('analytics.municipality_health_progressive_sections', true),
            FILTER_VALIDATE_BOOL,
        );
    }
}
