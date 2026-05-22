<?php

namespace App\Support\Finance;

/**
 * Arredondamento e fórmulas financeiras indicativas (FUNDEB, discrepâncias, repasses).
 *
 * Política: valores em R$ com 2 casas (meio para cima); percentuais configuráveis por chamada.
 */
final class MoneyMath
{
    public const MONEY_DECIMALS = 2;

    public const PERCENT_DEFAULT_DECIMALS = 1;

    public static function roundMoney(float $value): float
    {
        return round($value, self::MONEY_DECIMALS, PHP_ROUND_HALF_UP);
    }

    public static function roundPercent(float $value, int $decimals = self::PERCENT_DEFAULT_DECIMALS): float
    {
        return round($value, $decimals, PHP_ROUND_HALF_UP);
    }

    public static function multiplyVaaf(int $matriculas, float $vaaf): float
    {
        return self::roundMoney(max(0, $matriculas) * max(0.0, $vaaf));
    }

    /**
     * Perda ou ganho indicativo: ocorrências × VAAF × peso do eixo.
     */
    public static function impactFromOccurrences(int $occurrences, float $vaaf, float $peso): float
    {
        return self::roundMoney(max(0, $occurrences) * max(0.0, $vaaf) * max(0.0, $peso));
    }

    public static function percentOf(float $part, float $whole, int $decimals = self::PERCENT_DEFAULT_DECIMALS): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return self::roundPercent(($part / $whole) * 100, $decimals);
    }

    public static function formatBrl(float $value): string
    {
        return 'R$ '.number_format($value, self::MONEY_DECIMALS, ',', '.');
    }
}
