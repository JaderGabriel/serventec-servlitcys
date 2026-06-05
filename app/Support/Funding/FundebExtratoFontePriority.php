<?php

namespace App\Support\Funding;

use App\Models\MunicipalTransferSnapshot;

/**
 * Prioridade de fontes de repasse para totais (evita somar o mesmo programa em CKAN + SISWEB + BB).
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
            if (! FundebTransferScope::matchesFinanceRealtimeProgram($row)) {
                continue;
            }
            $pid = (string) $row->programa_id;
            if (! isset($fundeb[$pid]) || self::rank((string) $row->fonte) < self::rank((string) $fundeb[$pid]->fonte)) {
                $fundeb[$pid] = $row;
            }
        }

        return array_values($fundeb);
    }

    /**
     * Uma linha por programa (fonte de maior prioridade), excluindo agregados UF.
     *
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $rows
     * @return list<\App\Models\MunicipalTransferSnapshot>
     */
    public static function pickPrimaryPerProgram(array $rows): array
    {
        $byProgram = [];
        foreach (FundebTransferScope::municipalSnapshotsOnly($rows) as $row) {
            $pid = (string) $row->programa_id;
            if ($pid === '') {
                continue;
            }
            if (! isset($byProgram[$pid]) || self::rank((string) $row->fonte) < self::rank((string) $byProgram[$pid]->fonte)) {
                $byProgram[$pid] = $row;
            }
        }

        return array_values($byProgram);
    }

    /**
     * Soma deduplicada por ano (um valor por programa × exercício).
     *
     * @param  iterable<\App\Models\MunicipalTransferSnapshot>  $rows
     * @return array<int, float>
     */
    public static function totalsByYearDeduped(iterable $rows): array
    {
        /** @var array<int, array<string, MunicipalTransferSnapshot>> $byYearProgram */
        $byYearProgram = [];

        foreach ($rows as $row) {
            if (FundebTransferScope::isUfAggregated($row)) {
                continue;
            }
            $year = (int) $row->ano;
            $pid = (string) $row->programa_id;
            if ($year < 2000 || $pid === '') {
                continue;
            }
            if (
                ! isset($byYearProgram[$year][$pid])
                || self::rank((string) $row->fonte) < self::rank((string) $byYearProgram[$year][$pid]->fonte)
            ) {
                $byYearProgram[$year][$pid] = $row;
            }
        }

        $totals = [];
        foreach ($byYearProgram as $year => $programs) {
            $totals[$year] = round(array_sum(array_map(
                static fn (MunicipalTransferSnapshot $r): float => (float) $r->valor,
                $programs,
            )), 2);
        }
        ksort($totals);

        return $totals;
    }

    /**
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $rows
     */
    public static function sumDedupedValor(array $rows): float
    {
        return round(array_sum(array_map(
            static fn (MunicipalTransferSnapshot $r): float => (float) $r->valor,
            self::pickPrimaryPerProgram($rows),
        )), 2);
    }
}
