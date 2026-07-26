<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteProcurementMarketScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteProcurementMarketScorerTest extends TestCase
{
    #[Test]
    public function proxy_sge_zero_without_signals(): void
    {
        $this->assertSame(0, HorizonteProcurementMarketScorer::proxySge([]));
    }

    #[Test]
    public function proxy_sge_rises_with_registry_and_software(): void
    {
        $score = HorizonteProcurementMarketScorer::proxySge([
            'sge_found' => true,
            'sge_status' => 'registry',
            'transparency_contratos_software' => 2,
            'licitacoes_software' => 1,
            'national_vendor_matched' => 10,
        ]);

        $this->assertGreaterThanOrEqual(70, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    #[Test]
    public function national_vendor_alone_does_not_inflate_proxy(): void
    {
        $this->assertSame(0, HorizonteProcurementMarketScorer::proxySge([
            'national_vendor_matched' => 50,
        ]));
    }

    #[Test]
    public function market_national_status_no_longer_inflates_proxy_alone(): void
    {
        $this->assertSame(0, HorizonteProcurementMarketScorer::proxySge([
            'sge_found' => true,
            'sge_status' => 'market_national',
            'national_vendor_matched' => 90,
        ]));
    }

    #[Test]
    public function market_candidates_give_moderate_proxy(): void
    {
        $score = HorizonteProcurementMarketScorer::proxySge([
            'sge_found' => false,
            'sge_status' => 'market_candidates',
        ]);
        $this->assertSame(25, $score);
    }

    #[Test]
    public function timing_licitacao_scales_with_count(): void
    {
        $this->assertSame(0, HorizonteProcurementMarketScorer::timingLicitacao(0));
        $this->assertSame(40, HorizonteProcurementMarketScorer::timingLicitacao(1));
        $this->assertSame(55, HorizonteProcurementMarketScorer::timingLicitacao(1, 1));
        $this->assertSame(85, HorizonteProcurementMarketScorer::timingLicitacao(7));
        $this->assertSame(100, HorizonteProcurementMarketScorer::timingLicitacao(7, 1));
    }
}
