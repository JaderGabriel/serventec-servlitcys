<?php

namespace App\Support\Horizonte;

use App\Services\Inep\SaebPlanilhaInepImportService;

/** Anos SAEB considerados no mapa Horizonte (planilhas configuradas + janela do exercício). */
final class HorizonteSaebLookupYears
{
    /**
     * @return list<int>
     */
    public static function forReferenceYear(int $refYear): array
    {
        $configured = SaebPlanilhaInepImportService::parseYearsOption(null);
        $window = [
            $refYear,
            $refYear - 1,
            $refYear - 2,
            $refYear - 3,
            $refYear - 4,
        ];

        $years = array_values(array_unique(array_merge($configured, $window)));
        rsort($years);

        return array_values(array_filter(
            $years,
            static fn (int $year): bool => $year >= 1995 && $year <= 2100,
        ));
    }
}
