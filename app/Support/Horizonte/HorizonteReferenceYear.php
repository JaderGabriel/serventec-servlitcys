<?php

namespace App\Support\Horizonte;

use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Ieducar\FundebReferenceYearOrder;

/**
 * Exercício FUNDEB / Censo / SAEB usado pelo mapa Horizonte e pela rotina bimestral.
 */
final class HorizonteReferenceYear
{
    public static function resolve(?int $rawOverride = null): int
    {
        $configured = $rawOverride ?? (int) config('horizonte.reference_year_raw', 0);
        if ($configured > 0 && self::isPlausible($configured)) {
            return $configured;
        }

        return FundebOpenDataImportService::suggestedImportYear();
    }

    public static function isPlausible(int $year): bool
    {
        $current = (int) date('Y');

        return $year >= FundebReferenceYearOrder::MIN_YEAR && $year <= $current;
    }
}
