<?php

namespace App\Support\Ieducar;

/**
 * Ordem de anos para VAAF/FUNDEB: ano de referência (vigente), anos anteriores, depois outras fontes.
 */
final class FundebReferenceYearOrder
{
    public const MIN_YEAR = 2000;

    /**
     * @return list<int> do mais recente ao mais antigo (inclui o ano âncora)
     */
    public static function candidateYears(?int $anchorYear = null, int $maxPastYears = 5): array
    {
        $anchor = $anchorYear ?? (int) date('Y');
        $maxPastYears = max(0, $maxPastYears);
        $years = [];

        for ($offset = 0; $offset <= $maxPastYears; $offset++) {
            $y = $anchor - $offset;
            if ($y >= self::MIN_YEAR) {
                $years[] = $y;
            }
        }

        return $years;
    }
}
