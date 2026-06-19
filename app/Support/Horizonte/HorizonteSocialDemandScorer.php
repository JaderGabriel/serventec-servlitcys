<?php

namespace App\Support\Horizonte;

/**
 * Dimensões de demanda social (CadÚnico / SIDRA) e dependência de repasses (Tesouro).
 */
final class HorizonteSocialDemandScorer
{
    public static function socialDemandScore(
        ?int $matriculasCenso,
        ?int $cadunicoEscolar,
        ?int $sidraPop417,
        ?float $pctCriancasPbf,
    ): int {
        $mat = max(0, (int) ($matriculasCenso ?? 0));
        $denominator = max(
            $cadunicoEscolar ?? 0,
            $sidraPop417 ?? 0,
            $mat > 0 ? (int) round($mat * 1.05) : 0,
        );

        if ($denominator <= 0) {
            if ($pctCriancasPbf !== null && $pctCriancasPbf > 0) {
                return max(0, min(100, (int) round(min(100.0, $pctCriancasPbf) * 0.85)));
            }

            return 0;
        }

        $gap = max(0, $denominator - $mat);
        $gapRatio = min(1.0, $gap / $denominator);
        $base = (int) round($gapRatio * 78);

        $pbfBoost = 0;
        if ($pctCriancasPbf !== null && $pctCriancasPbf > 0) {
            $pbfBoost = (int) round(min(22.0, $pctCriancasPbf * 0.35));
        }

        return max(0, min(100, $base + $pbfBoost));
    }

    public static function transferDependencyScore(
        ?float $transferTotal,
        ?float $receitaFundeb,
        ?float $complementacaoFundeb,
        ?float $medianRatio,
    ): int {
        if ($transferTotal === null || $transferTotal <= 0) {
            return 0;
        }

        $base = max(1.0, (float) ($receitaFundeb ?? 0), (float) ($complementacaoFundeb ?? 0));
        $ratio = $transferTotal / $base;

        if ($medianRatio !== null && $medianRatio > 0) {
            return max(0, min(100, (int) round(100 * min(2.5, $ratio / $medianRatio) / 2.5)));
        }

        return max(0, min(100, (int) round(min(1.0, $ratio) * 100)));
    }

    /**
     * @param  list<float|null>  $ratios
     */
    public static function transferRatioMedian(array $ratios): ?float
    {
        $values = array_values(array_filter($ratios, static fn ($v) => $v !== null && is_finite((float) $v) && (float) $v > 0));
        sort($values);
        if ($values === []) {
            return null;
        }

        $mid = (int) floor(count($values) / 2);

        return count($values) % 2 === 1
            ? (float) $values[$mid]
            : ((float) $values[$mid - 1] + (float) $values[$mid]) / 2;
    }
}
