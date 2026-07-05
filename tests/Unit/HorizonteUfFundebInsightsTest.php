<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFundebRepasseOutlook;
use App\Support\Horizonte\HorizonteUfFundebInsights;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteUfFundebInsightsTest extends TestCase
{
    #[Test]
    public function agrega_receita_e_comparativo_nacional(): void
    {
        $refYear = 2025;
        $currentYear = HorizonteFundebRepasseOutlook::currentYear();
        if ($currentYear <= $refYear) {
            $this->markTestSkipped('Ano corrente deve ser maior que o ano de referência.');
        }

        $baMarkers = [
            $this->marker('BA', 1000000.0, 50000.0, 10000.0, 250000.0),
            $this->marker('BA', 800000.0, 40000.0, 8000.0, 200000.0),
        ];
        $spMarkers = [
            $this->marker('SP', 5000000.0, 200000.0, 50000.0, 1200000.0),
        ];

        $nationalByUf = HorizonteUfFundebInsights::aggregateNationalByUf(
            array_merge($baMarkers, $spMarkers),
            $refYear,
            $currentYear,
        );

        $insights = HorizonteUfFundebInsights::forRegional(
            'BA',
            $baMarkers,
            $refYear,
            $currentYear,
            $nationalByUf,
        );

        $this->assertSame('BA', $insights['uf']);
        $this->assertSame(1800000.0, $insights['receita_portaria_total']);
        $this->assertSame(90000.0, $insights['complementacao_total']);
        $this->assertSame(18000, $insights['matriculas_fundeb']);
        $this->assertTrue($insights['realtime']['available']);
        $this->assertSame(450000.0, $insights['realtime']['observed_total']);
        $this->assertSame(2, $insights['national']['rank_receita']);
        $this->assertSame(2, $insights['national']['total_ufs']);
        $this->assertNotNull($insights['national']['share_receita_pct']);
        $this->assertArrayHasKey('portaria', $insights);
    }

    #[Test]
    public function overview_fundeb_metrics_para_tooltip_nacional(): void
    {
        $refYear = 2025;
        $currentYear = HorizonteFundebRepasseOutlook::currentYear();
        if ($currentYear <= $refYear) {
            $this->markTestSkipped('Ano corrente deve ser maior que o ano de referência.');
        }

        $nationalByUf = HorizonteUfFundebInsights::aggregateNationalByUf(
            [
                $this->marker('BA', 1000000.0, 500000.0, 10000.0, 250000.0),
                $this->marker('SP', 5000000.0, 1000000.0, 50000.0, 1200000.0),
            ],
            $refYear,
            $currentYear,
        );

        $metrics = HorizonteUfFundebInsights::overviewFundebMetrics($nationalByUf);

        $this->assertSame(1, $metrics['SP']['rank_receita']);
        $this->assertSame(2, $metrics['BA']['rank_receita']);
        $this->assertSame(2, $metrics['SP']['total_ufs']);
        $this->assertSame(6000000.0, $metrics['SP']['total_previsto']);
        $this->assertSame(16.7, $metrics['SP']['pct_federal']);
        $this->assertSame(33.3, $metrics['BA']['pct_federal']);
    }

    /**
     * @return array<string, mixed>
     */
    private function marker(
        string $uf,
        float $receita,
        float $compl,
        int $matriculas,
        float $observed,
    ): array {
        $currentYear = HorizonteFundebRepasseOutlook::currentYear();

        return [
            'uf' => $uf,
            'has_fundeb' => true,
            'fundeb_receita_total' => $receita,
            'complementacao_fundeb' => $compl,
            'fundeb_matriculas_base' => $matriculas,
            'fundeb_ano' => 2025,
            'fundeb_realtime_observed' => $observed,
            'fundeb_realtime_expected' => $observed * 2,
            'fundeb_realtime_balance' => $observed,
            'fundeb_realtime_last_recorded_at' => now()->toIso8601String(),
            'fundeb_realtime_last_transfer_label' => 'abr/'.$currentYear,
        ];
    }
}
