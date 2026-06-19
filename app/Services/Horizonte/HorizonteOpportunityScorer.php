<?php

namespace App\Services\Horizonte;

use App\Support\Horizonte\HorizonteSocialDemandScorer;

/**
 * Score indicativo de oportunidade / benefício com base em dados públicos agregados.
 */
final class HorizonteOpportunityScorer
{
    /**
     * @param  array{
     *     matriculas_censo: ?int,
     *     complementacao_total: ?float,
     *     receita_total: ?float,
     *     saeb_lp: ?float,
     *     saeb_mat: ?float,
     *     cadunico_escolar: ?int,
     *     sidra_pop_4_17: ?int,
     *     pct_criancas_pbf: ?float,
     *     transfer_total: ?float,
     *     has_fundeb: bool,
     *     has_censo: bool,
     *     has_saeb: bool,
     *     has_cadunico: bool,
     *     has_demography: bool,
     *     has_transfers: bool,
     *     consultoria_active: bool,
     *     in_catalog: bool
     * }  $row
     * @param  array{saeb_p25: ?float, compl_ratio_median: ?float, transfer_ratio_median: ?float}  $benchmarks
     * @return array{
     *     success_score: int,
     *     benefit_score: int,
     *     financial_pressure: int,
     *     pedagogical_gap: int,
     *     scale_score: int,
     *     social_demand: int,
     *     transfer_dependency: int,
     *     data_readiness: int,
     *     tier: string,
     *     tier_label: string
     * }
     */
    public function score(array $row, array $benchmarks, int $highThreshold, int $mediumThreshold): array
    {
        if ($row['consultoria_active']) {
            return $this->packTier(
                success: min(100, max(20, $this->dataReadiness($row))),
                benefit: 0,
                financial: 0,
                pedagogical: 0,
                scale: $this->scaleScore($row['matriculas_censo'], $row['sidra_pop_4_17'] ?? null),
                social: 0,
                transfer: 0,
                readiness: $this->dataReadiness($row),
                tier: 'consultoria_active',
                tierLabel: __('Consultoria activa'),
            );
        }

        if ($row['in_catalog'] && ! $row['consultoria_active']) {
            $readiness = $this->dataReadiness($row);

            return $this->packTier(
                success: min(85, 30 + (int) round($readiness * 0.5)),
                benefit: min(90, 25 + (int) round($readiness * 0.4)),
                financial: 0,
                pedagogical: 0,
                scale: $this->scaleScore($row['matriculas_censo'], $row['sidra_pop_4_17'] ?? null),
                social: 0,
                transfer: 0,
                readiness: $readiness,
                tier: 'catalog_pending',
                tierLabel: __('No catálogo · sem base'),
            );
        }

        $financial = $this->financialPressure(
            $row['complementacao_total'],
            $row['receita_total'],
            $row['matriculas_censo'],
            $benchmarks['compl_ratio_median'] ?? null,
        );
        $pedagogical = $this->pedagogicalGap(
            $row['saeb_lp'],
            $row['saeb_mat'],
            $benchmarks['saeb_p25'] ?? null,
        );
        $scale = $this->scaleScore($row['matriculas_censo'], $row['sidra_pop_4_17'] ?? null);
        $social = HorizonteSocialDemandScorer::socialDemandScore(
            $row['matriculas_censo'],
            $row['cadunico_escolar'] ?? null,
            $row['sidra_pop_4_17'] ?? null,
            $row['pct_criancas_pbf'] ?? null,
        );
        $transfer = HorizonteSocialDemandScorer::transferDependencyScore(
            $row['transfer_total'] ?? null,
            $row['receita_total'],
            $row['complementacao_total'],
            $benchmarks['transfer_ratio_median'] ?? null,
        );
        $readiness = $this->dataReadiness($row);

        if (! $row['has_fundeb'] && ! $row['has_censo'] && ! $row['has_saeb'] && ! ($row['has_cadunico'] ?? false)) {
            return $this->packTier(0, 0, 0, 0, 0, 0, 0, 0, 'data_sparse', __('Sem dados públicos'));
        }

        $weights = config('horizonte.weights', []);
        $success = (int) round(
            ($weights['financial_pressure'] ?? 0.22) * $financial
            + ($weights['pedagogical_gap'] ?? 0.18) * $pedagogical
            + ($weights['scale'] ?? 0.12) * $scale
            + ($weights['social_demand'] ?? 0.18) * $social
            + ($weights['transfer_dependency'] ?? 0.10) * $transfer
            + ($weights['data_readiness'] ?? 0.10) * $readiness
            + ($weights['benefit_scale'] ?? 0.10) * min($scale, $financial)
        );
        $success = max(0, min(100, $success));

        $benefit = (int) round(
            0.28 * $pedagogical
            + 0.28 * $financial
            + 0.18 * $social
            + 0.14 * $scale
            + 0.07 * $transfer
            + 0.05 * $readiness
        );
        $benefit = max(0, min(100, $benefit));

        $tier = match (true) {
            $success >= $highThreshold => 'prospect_high',
            $success >= $mediumThreshold => 'prospect_medium',
            default => 'prospect_low',
        };

        $tierLabel = match ($tier) {
            'prospect_high' => __('Alta propensão'),
            'prospect_medium' => __('Média propensão'),
            default => __('Baixa propensão'),
        };

        return $this->packTier($success, $benefit, $financial, $pedagogical, $scale, $social, $transfer, $readiness, $tier, $tierLabel);
    }

    /**
     * @param  list<float|null>  $saebValues
     * @param  list<float|null>  $complRatios
     * @param  list<float|null>  $transferRatios
     * @return array{saeb_p25: ?float, compl_ratio_median: ?float, transfer_ratio_median: ?float}
     */
    public function benchmarks(array $saebValues, array $complRatios, array $transferRatios = []): array
    {
        $saeb = array_values(array_filter($saebValues, static fn ($v) => $v !== null && is_finite((float) $v)));
        sort($saeb);
        $saebP25 = $saeb !== [] ? (float) $saeb[(int) floor(count($saeb) * 0.25)] : null;

        $ratios = array_values(array_filter($complRatios, static fn ($v) => $v !== null && is_finite((float) $v) && (float) $v > 0));
        sort($ratios);
        $median = null;
        if ($ratios !== []) {
            $mid = (int) floor(count($ratios) / 2);
            $median = count($ratios) % 2 === 1
                ? (float) $ratios[$mid]
                : ((float) $ratios[$mid - 1] + (float) $ratios[$mid]) / 2;
        }

        return [
            'saeb_p25' => $saebP25,
            'compl_ratio_median' => $median,
            'transfer_ratio_median' => HorizonteSocialDemandScorer::transferRatioMedian($transferRatios),
        ];
    }

    private function financialPressure(?float $compl, ?float $receita, ?int $matriculas, ?float $medianRatio): int
    {
        if ($compl !== null && $compl > 0 && $receita !== null && $receita > 0) {
            $ratio = $compl / $receita;
            if ($medianRatio !== null && $medianRatio > 0) {
                return max(0, min(100, (int) round(100 * min(2.5, $ratio / $medianRatio) / 2.5)));
            }

            return max(0, min(100, (int) round(min(1.0, $ratio) * 100)));
        }

        if ($compl !== null && $compl > 0 && $matriculas !== null && $matriculas > 0) {
            $perCap = $compl / $matriculas;

            return max(0, min(100, (int) round(min(5000, $perCap) / 50)));
        }

        return $compl !== null && $compl > 0 ? 55 : 0;
    }

    private function pedagogicalGap(?float $lp, ?float $mat, ?float $p25): int
    {
        $values = array_filter([$lp, $mat], static fn ($v) => $v !== null && is_finite((float) $v));
        if ($values === []) {
            return 35;
        }

        $avg = array_sum($values) / count($values);
        if ($p25 !== null && $p25 > 0) {
            if ($avg >= $p25) {
                return max(0, min(45, (int) round(45 * ($p25 / max($avg, 1)))));
            }

            return max(55, min(100, (int) round(100 - (100 * $avg / max($p25, 1)))));
        }

        if ($avg <= 200) {
            return max(50, min(100, (int) round(100 - $avg / 3)));
        }

        return max(0, min(40, (int) round(240 - $avg)));
    }

    private function scaleScore(?int $matriculas, ?int $sidraPop417): int
    {
        $base = $matriculas;
        if (($base === null || $base <= 0) && $sidraPop417 !== null && $sidraPop417 > 0) {
            $base = (int) round($sidraPop417 * 0.85);
        }

        if ($base === null || $base <= 0) {
            return 15;
        }

        $log = log10(max(1, $base));

        return max(0, min(100, (int) round(($log / 5) * 100)));
    }

    private function dataReadiness(array $row): int
    {
        $n = (float) (int) ($row['has_fundeb'] ?? false)
            + (float) (int) ($row['has_censo'] ?? false)
            + (float) (int) ($row['has_saeb'] ?? false)
            + (float) (int) ($row['has_cadunico'] ?? false)
            + (($row['has_demography'] ?? false) ? 0.5 : 0.0)
            + (($row['has_transfers'] ?? false) ? 0.5 : 0.0);

        return (int) round(min(100.0, 100 * $n / 4));
    }

    /**
     * @return array{
     *     success_score: int,
     *     benefit_score: int,
     *     financial_pressure: int,
     *     pedagogical_gap: int,
     *     scale_score: int,
     *     social_demand: int,
     *     transfer_dependency: int,
     *     data_readiness: int,
     *     tier: string,
     *     tier_label: string
     * }
     */
    private function packTier(
        int $success,
        int $benefit,
        int $financial,
        int $pedagogical,
        int $scale,
        int $social,
        int $transfer,
        int $readiness,
        string $tier,
        string $tierLabel,
    ): array {
        return [
            'success_score' => max(0, min(100, $success)),
            'benefit_score' => max(0, min(100, $benefit)),
            'financial_pressure' => max(0, min(100, $financial)),
            'pedagogical_gap' => max(0, min(100, $pedagogical)),
            'scale_score' => max(0, min(100, $scale)),
            'social_demand' => max(0, min(100, $social)),
            'transfer_dependency' => max(0, min(100, $transfer)),
            'data_readiness' => max(0, min(100, $readiness)),
            'tier' => $tier,
            'tier_label' => $tierLabel,
        ];
    }
}
