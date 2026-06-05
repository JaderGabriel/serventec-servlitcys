<?php

namespace App\Support\Funding;

use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Projeção até dezembro: necessidade indicativa × repasses (observado + saldo a repassar / ritmo).
 */
final class FinanceRealtimeYearEndOutlook
{
    private const MARGIN_PCT = 2.0;

    /**
     * @param  array<string, mixed>  $periodicSchedule
     * @return array<string, mixed>
     */
    public static function build(
        float $expectedAnnual,
        float $observedAnnual,
        int $filterYear,
        array $periodicSchedule,
    ): array {
        $needUntilDecember = MoneyMath::roundMoney(max(0, $expectedAnnual));
        $observedYtd = MoneyMath::roundMoney(max(0, $observedAnnual));
        $balanceToRepass = MoneyMath::roundMoney(max(0, $needUntilDecember - $observedYtd));

        $monthsWithTransfers = max(0, (int) ($periodicSchedule['months_with_transfers'] ?? 0));
        $monthlyExpected = (float) ($periodicSchedule['monthly'] ?? 0);
        $currentYear = (int) date('Y');
        $monthsElapsed = $filterYear < $currentYear
            ? 12
            : ($filterYear > $currentYear ? 0 : max(1, min(12, (int) date('n'))));

        $projectedRepass = self::projectRepassUntilDecember(
            $needUntilDecember,
            $observedYtd,
            $balanceToRepass,
            $monthlyExpected,
            $monthsWithTransfers,
            $filterYear,
            $currentYear,
        );

        $gap = MoneyMath::roundMoney($projectedRepass - $needUntilDecember);
        $gapPct = $needUntilDecember > 0
            ? MoneyMath::percentOf(abs($gap), $needUntilDecember)
            : null;

        $classification = self::classify($projectedRepass, $needUntilDecember, $balanceToRepass, $observedYtd);

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        return [
            'need_until_december' => $needUntilDecember,
            'need_until_december_fmt' => $fmt($needUntilDecember),
            'observed_ytd' => $observedYtd,
            'observed_ytd_fmt' => $fmt($observedYtd),
            'balance_to_repass' => $balanceToRepass,
            'balance_to_repass_fmt' => $fmt($balanceToRepass),
            'projected_repass_until_december' => $projectedRepass,
            'projected_repass_until_december_fmt' => $fmt($projectedRepass),
            'gap_until_december' => $gap,
            'gap_until_december_fmt' => $fmt(abs($gap)),
            'gap_sign' => $gap >= 0 ? 'positive' : 'negative',
            'gap_pct' => $gapPct,
            'margin_pct' => self::MARGIN_PCT,
            'months_elapsed' => $monthsElapsed,
            'months_with_transfers' => $monthsWithTransfers,
            'outlook' => $classification['outlook'],
            'outlook_label' => $classification['label'],
            'outlook_detail' => $classification['detail'],
            'formula' => $classification['formula'],
        ];
    }

    private static function projectRepassUntilDecember(
        float $needUntilDecember,
        float $observedYtd,
        float $balanceToRepass,
        float $monthlyExpected,
        int $monthsWithTransfers,
        int $filterYear,
        int $currentYear,
    ): float {
        if ($filterYear < $currentYear) {
            return $observedYtd;
        }

        if ($filterYear > $currentYear) {
            return $needUntilDecember;
        }

        if ($observedYtd > 0 && $monthsWithTransfers > 0) {
            $avgMonthly = $observedYtd / $monthsWithTransfers;

            return MoneyMath::roundMoney($avgMonthly * 12);
        }

        if ($monthlyExpected > 0) {
            return MoneyMath::roundMoney($monthlyExpected * 12);
        }

        return MoneyMath::roundMoney($observedYtd + $balanceToRepass);
    }

    /**
     * @return array{outlook: string, label: string, detail: string, formula: string}
     */
    private static function classify(
        float $projectedRepass,
        float $needUntilDecember,
        float $balanceToRepass,
        float $observedYtd,
    ): array {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        if ($needUntilDecember <= 0) {
            return [
                'outlook' => 'unknown',
                'label' => __('Indisponível'),
                'detail' => __('Importe VAAF e matrículas para estimar a necessidade até dezembro.'),
                'formula' => '',
            ];
        }

        $margin = self::MARGIN_PCT / 100;
        $lower = $needUntilDecember * (1 - $margin);
        $upper = $needUntilDecember * (1 + $margin);
        $gap = MoneyMath::roundMoney($projectedRepass - $needUntilDecember);

        if ($projectedRepass < $lower) {
            $shortfall = MoneyMath::roundMoney($needUntilDecember - $projectedRepass);

            return [
                'outlook' => 'risk',
                'label' => __('Risco de déficit'),
                'detail' => __('A projeção de repasses até dezembro (:proj) fica :falta abaixo da necessidade indicativa (:need). O saldo a repassar (:saldo) pode não cobrir o exercício ao ritmo actual.', [
                    'proj' => $fmt($projectedRepass),
                    'falta' => $fmt($shortfall),
                    'need' => $fmt($needUntilDecember),
                    'saldo' => $fmt($balanceToRepass),
                ]),
                'formula' => __('Projeção (ritmo observado × 12) − necessidade até dez = :gap', [
                    'gap' => '−'.$fmt(abs($gap)),
                ]),
            ];
        }

        if ($projectedRepass > $upper) {
            return [
                'outlook' => 'surplus',
                'label' => __('Sobras projetadas'),
                'detail' => __('Repasse projetado até dezembro (:proj) supera a necessidade indicativa (:need) em mais de :pct%.', [
                    'proj' => $fmt($projectedRepass),
                    'need' => $fmt($needUntilDecember),
                    'pct' => number_format(self::MARGIN_PCT, 0, ',', '.'),
                ]),
                'formula' => __('Projeção − necessidade até dez = +:gap', [
                    'gap' => $fmt($gap),
                ]),
            ];
        }

        return [
            'outlook' => 'close',
            'label' => __('Próximo do valor'),
            'detail' => __('Repasse projetado e necessidade até dezembro dentro da margem de :pct% (observado :obs + saldo a repassar :saldo).', [
                'pct' => number_format(self::MARGIN_PCT, 0, ',', '.'),
                'obs' => $fmt($observedYtd),
                'saldo' => $fmt($balanceToRepass),
            ]),
            'formula' => __('Dentro de ±:pct% da projeção indicativa anual', [
                'pct' => number_format(self::MARGIN_PCT, 0, ',', '.'),
            ]),
        ];
    }
}
