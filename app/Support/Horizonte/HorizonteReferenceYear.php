<?php

namespace App\Support\Horizonte;

use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Ieducar\FundebReferenceYearOrder;

/**
 * Exercício FUNDEB / Censo / SAEB usado pelo mapa Horizonte e pela rotina quinzenal.
 */
final class HorizonteReferenceYear
{
    public static function resolve(): int
    {
        $raw = env('HORIZONTE_REFERENCE_YEAR');
        if ($raw !== null && $raw !== '' && is_numeric(trim((string) $raw))) {
            $year = (int) trim((string) $raw);
            if (self::isPlausible($year)) {
                return $year;
            }
        }

        return FundebOpenDataImportService::suggestedImportYear();
    }

    public static function isPlausible(int $year): bool
    {
        $current = (int) date('Y');

        return $year >= FundebReferenceYearOrder::MIN_YEAR && $year <= $current;
    }
}
