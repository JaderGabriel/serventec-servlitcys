<?php

namespace App\Services\Cadunico;

use App\Models\CadunicoTerritorioSnapshot;

/**
 * Estima lacuna por território (CUN-02): directo com CadÚnico real ou rateio IBGE.
 */
final class CadunicoTerritorialGapEstimator
{
    public const FONTE_IBGE_PREFIX = 'ibge_censo';

    /**
     * @param  array<string, mixed>  $gap  Resultado de CadunicoRedeGapAnalyzer
     */
    public static function isIbgeRateioCollection(iterable $rows): bool
    {
        $hasRow = false;
        foreach ($rows as $row) {
            $hasRow = true;
            $fonte = strtolower((string) ($row->fonte ?? ''));
            if ($fonte !== '' && ! str_starts_with($fonte, self::FONTE_IBGE_PREFIX)) {
                return false;
            }
        }

        return $hasRow;
    }

    /**
     * @param  array<string, mixed>  $gap
     */
    public static function estimateForTerritory(
        CadunicoTerritorioSnapshot $row,
        array $gap,
        int $baseRede,
        int $cadTerrSum,
        int $gapTotal,
        bool $ibgeRateio,
    ): int {
        $cadLocal = $row->totalEscolar();
        if ($cadLocal <= 0) {
            return 0;
        }

        $porFaixaGap = is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [];
        $faixaMetodo = (string) ($gap['faixa_metodo'] ?? '');

        if (! $ibgeRateio && $faixaMetodo === CadunicoFaixaEtariaMetodo::IDADE) {
            $fromFaixa = self::gapFromFaixaBreakdown($row, $porFaixaGap);
            if ($fromFaixa !== null) {
                return $fromFaixa;
            }
        }

        if (! $ibgeRateio && $cadTerrSum > 0 && $baseRede > 0) {
            $cadMunicipal = max(1, (int) ($gap['cadunico_total_escolar'] ?? 0));
            $ieducarEst = (int) round($baseRede * ($cadLocal / $cadMunicipal));

            return max(0, $cadLocal - $ieducarEst);
        }

        if ($gapTotal <= 0 || $cadTerrSum <= 0) {
            return max(0, $cadLocal - (int) round(($gap['ieducar_matriculas'] ?? 0) * ($cadLocal / max(1, $cadTerrSum))));
        }

        if ($faixaMetodo === CadunicoFaixaEtariaMetodo::IDADE && $porFaixaGap !== []) {
            $weighted = self::gapFromFaixaRateio($row, $porFaixaGap, $gapTotal, $cadTerrSum);
            if ($weighted !== null) {
                return $weighted;
            }
        }

        $share = $cadLocal / $cadTerrSum;

        return max(0, (int) round($gapTotal * $share));
    }

    /**
     * @param  list<array<string, mixed>>  $porFaixaGap
     */
    private static function gapFromFaixaBreakdown(CadunicoTerritorioSnapshot $row, array $porFaixaGap): ?int
    {
        $sum = 0;
        $used = false;
        $cadMunicipalTotal = max(
            1,
            array_sum(array_map(
                static fn (array $f): int => (int) ($f['cadunico'] ?? 0),
                array_values(array_filter($porFaixaGap, static fn ($f): bool => is_array($f))),
            )),
        );

        foreach ($porFaixaGap as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $key = (string) ($faixa['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $cadMunicipal = (int) ($faixa['cadunico'] ?? 0);
            $ieducarReal = (int) ($faixa['ieducar'] ?? $faixa['ieducar_estimado'] ?? 0);
            if ($cadMunicipal <= 0) {
                continue;
            }

            $cadTerr = max(0, (int) ($row->{$key} ?? 0));
            if ($cadTerr <= 0 && $row->totalEscolar() > 0) {
                $cadTerr = (int) round($cadMunicipal * ($row->totalEscolar() / $cadMunicipalTotal));
            }
            if ($cadTerr <= 0) {
                continue;
            }

            $ieducarTerr = (int) round($ieducarReal * ($cadTerr / $cadMunicipal));
            $sum += max(0, $cadTerr - $ieducarTerr);
            $used = true;
        }

        return $used ? $sum : null;
    }

    /**
     * @param  list<array<string, mixed>>  $porFaixaGap
     */
    private static function gapFromFaixaRateio(
        CadunicoTerritorioSnapshot $row,
        array $porFaixaGap,
        int $gapTotal,
        int $cadTerrSum,
    ): ?int {
        $gapSum = 0;
        foreach ($porFaixaGap as $faixa) {
            if (is_array($faixa)) {
                $gapSum += max(0, (int) ($faixa['gap'] ?? 0));
            }
        }
        if ($gapSum <= 0) {
            return null;
        }

        $weighted = 0.0;
        foreach ($porFaixaGap as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $key = (string) ($faixa['key'] ?? '');
            $gapF = max(0, (int) ($faixa['gap'] ?? 0));
            $cadM = max(0, (int) ($faixa['cadunico'] ?? 0));
            if ($key === '' || $gapF <= 0 || $cadM <= 0) {
                continue;
            }
            $cadT = max(0, (int) ($row->{$key} ?? 0));
            if ($cadT <= 0) {
                $cadT = $row->totalEscolar() > 0
                    ? (int) round($cadM * ($row->totalEscolar() / max(1, $cadTerrSum)))
                    : 0;
            }
            if ($cadT <= 0) {
                continue;
            }
            $weighted += $gapF * ($cadT / $cadM);
        }

        if ($weighted <= 0) {
            return null;
        }

        return max(0, (int) round($weighted));
    }
}
