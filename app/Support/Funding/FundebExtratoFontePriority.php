<?php

namespace App\Support\Funding;

/**
 * Prioridade de fontes FUNDEB para totais (evita somar o mesmo repasse em CKAN + espelho SISWEB).
 */
final class FundebExtratoFontePriority
{
    /** @var list<string> */
    private const ORDER = [
        'tesouro_csv',
        'sisweb_export',
        'bb_extrato',
        'sisweb_ckan',
        'tesouro_publicacao',
        'tesouro',
        'portal_transparencia',
    ];

    public static function rank(string $fonte): int
    {
        $fonte = strtolower(trim($fonte));
        $idx = array_search($fonte, self::ORDER, true);

        return $idx === false ? 999 : $idx;
    }

    /**
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $rows
     * @return list<\App\Models\MunicipalTransferSnapshot>
     */
    public static function pickPrimaryFundebRows(array $rows): array
    {
        $fundeb = [];
        foreach ($rows as $row) {
            if (FundebTransferScope::isUfAggregated($row)) {
                continue;
            }
            $blob = mb_strtolower((string) $row->programa_id.' '.(string) $row->programa_label);
            if (! str_contains($blob, 'fundeb') && ! str_contains($blob, 'fnde')) {
                continue;
            }
            $pid = (string) $row->programa_id;
            if (! isset($fundeb[$pid]) || self::rank((string) $row->fonte) < self::rank((string) $fundeb[$pid]->fonte)) {
                $fundeb[$pid] = $row;
            }
        }

        return array_values($fundeb);
    }
}
