<?php

namespace App\Services\Horizonte;

use App\Support\Horizonte\HorizonteSocialDemandScorer;
use App\Support\Horizonte\HorizonteSaebTrend;

/**
 * Score indicativo de oportunidade / benefício com base em dados públicos agregados.
 */
final class HorizonteOpportunityScorer
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array{saeb_p25: ?float, compl_ratio_median: ?float, transfer_ratio_median: ?float}  $benchmarks
     * @return array<string, mixed>
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
                fiscal: 0,
                learning: 0,
                momentum: 0,
                inclusion: 0,
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
                fiscal: 0,
                learning: 0,
                momentum: 0,
                inclusion: 0,
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
        $learning = (int) ($row['learning_trajectory_score'] ?? HorizonteSaebTrend::score(
            HorizonteSaebTrend::TREND_UNKNOWN,
            [],
        ));
        $pedagogical = (int) round(min(100, 0.65 * $pedagogical + 0.35 * $learning));
        $scale = $this->scaleScore($row['matriculas_censo'], $row['sidra_pop_4_17'] ?? null);
        $social = HorizonteSocialDemandScorer::socialDemandScore(
            $row['matriculas_censo'],
            $row['cadunico_escolar'] ?? null,
            $row['sidra_pop_4_17'] ?? null,
            $row['pct_criancas_pbf'] ?? null,
        );
        $inclusion = (int) ($row['inclusion_gap_score'] ?? 35);
        $social = (int) round(min(100, 0.75 * $social + 0.25 * $inclusion));
        $transfer = HorizonteSocialDemandScorer::transferDependencyScore(
            $row['transfer_total'] ?? null,
            $row['receita_total'],
            $row['complementacao_total'],
            $benchmarks['transfer_ratio_median'] ?? null,
        );
        if (isset($row['pct_receita_propria']) && is_numeric($row['pct_receita_propria'])) {
            $own = max(0.0, min(100.0, (float) $row['pct_receita_propria']));
            $transfer = (int) round(min(100, 0.7 * $transfer + 0.3 * (100 - $own)));
        }
        $fiscal = (int) ($row['fiscal_capacity_score'] ?? 45);
        $momentum = (int) ($row['enrollment_momentum_score'] ?? 35);
        $readiness = $this->dataReadiness($row);

        if (! $row['has_fundeb'] && ! $row['has_censo'] && ! $row['has_saeb'] && ! ($row['has_cadunico'] ?? false)) {
            return $this->packTier(
                success: 0,
                benefit: 0,
                financial: 0,
                pedagogical: 0,
                scale: 0,
                social: 0,
                transfer: 0,
                fiscal: 0,
                learning: 0,
                momentum: 0,
                inclusion: 0,
                readiness: 0,
                tier: 'data_sparse',
                tierLabel: __('Sem dados públicos'),
            );
        }

        $weights = config('horizonte.weights', []);
        $success = (int) round(
            ($weights['financial_pressure'] ?? 0.18) * $financial
            + ($weights['pedagogical_gap'] ?? 0.16) * $pedagogical
            + ($weights['scale'] ?? 0.10) * $scale
            + ($weights['social_demand'] ?? 0.16) * $social
            + ($weights['transfer_dependency'] ?? 0.08) * $transfer
            + ($weights['fiscal_capacity'] ?? 0.10) * (100 - $fiscal)
            + ($weights['enrollment_momentum'] ?? 0.06) * $momentum
            + ($weights['data_readiness'] ?? 0.08) * $readiness
            + ($weights['benefit_scale'] ?? 0.08) * min($scale, $financial)
        );
        $success = max(0, min(100, $success));

        $benefit = (int) round(
            0.24 * $pedagogical
            + 0.22 * $financial
            + 0.16 * $social
            + 0.12 * $scale
            + 0.10 * $momentum
            + 0.08 * (100 - $fiscal)
            + 0.08 * $transfer
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

        return $this->packTier($success, $benefit, $financial, $pedagogical, $scale, $social, $transfer, $fiscal, $learning, $momentum, $inclusion, $readiness, $tier, $tierLabel);
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function dataReadiness(array $row): int
    {
        $n = (float) (int) ($row['has_fundeb'] ?? false)
            + (float) (int) ($row['has_censo'] ?? false)
            + (float) (int) ($row['has_saeb'] ?? false)
            + (float) (int) ($row['has_cadunico'] ?? false)
            + (($row['has_demography'] ?? false) ? 0.5 : 0.0)
            + (($row['has_transfers'] ?? false) ? 0.5 : 0.0)
            + (($row['has_fiscal'] ?? false) ? 0.5 : 0.0)
            + (($row['has_transparency'] ?? false) ? 0.25 : 0.0)
            + (($row['has_pnad'] ?? false) ? 0.25 : 0.0);

        return (int) round(min(100.0, 100 * $n / 5));
    }

    /**
     * @return array<string, mixed>
     */
    private function packTier(
        int $success,
        int $benefit,
        int $financial,
        int $pedagogical,
        int $scale,
        int $social,
        int $transfer,
        int $fiscal,
        int $learning,
        int $momentum,
        int $inclusion,
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
            'fiscal_capacity' => max(0, min(100, $fiscal)),
            'learning_trajectory' => max(0, min(100, $learning)),
            'enrollment_momentum' => max(0, min(100, $momentum)),
            'inclusion_gap' => max(0, min(100, $inclusion)),
            'data_readiness' => max(0, min(100, $readiness)),
            'tier' => $tier,
            'tier_label' => $tierLabel,
        ];
    }
}
