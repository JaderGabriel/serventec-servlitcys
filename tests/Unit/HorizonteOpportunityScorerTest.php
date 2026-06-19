<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteOpportunityScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonteOpportunityScorerTest extends TestCase
{
    private HorizonteOpportunityScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new HorizonteOpportunityScorer;
    }

    #[Test]
    public function consultoria_active_tier_when_city_ready(): void
    {
        $result = $this->scorer->score([
            'matriculas_censo' => 10000,
            'complementacao_total' => 1_000_000,
            'receita_total' => 5_000_000,
            'saeb_lp' => 220,
            'saeb_mat' => 230,
            'has_fundeb' => true,
            'has_censo' => true,
            'has_saeb' => true,
            'consultoria_active' => true,
            'in_catalog' => true,
        ], ['saeb_p25' => 200, 'compl_ratio_median' => 0.1], 70, 40);

        $this->assertSame('consultoria_active', $result['tier']);
    }

    #[Test]
    public function prospect_high_when_financial_and_pedagogical_pressure(): void
    {
        $result = $this->scorer->score([
            'matriculas_censo' => 50000,
            'complementacao_total' => 2_000_000,
            'receita_total' => 4_000_000,
            'saeb_lp' => 180,
            'saeb_mat' => 175,
            'has_fundeb' => true,
            'has_censo' => true,
            'has_saeb' => true,
            'consultoria_active' => false,
            'in_catalog' => false,
        ], ['saeb_p25' => 210, 'compl_ratio_median' => 0.15], 70, 40);

        $this->assertContains($result['tier'], ['prospect_high', 'prospect_medium']);
        $this->assertGreaterThanOrEqual(40, $result['success_score']);
    }

    #[Test]
    public function data_sparse_without_public_sources(): void
    {
        $result = $this->scorer->score([
            'matriculas_censo' => null,
            'complementacao_total' => null,
            'receita_total' => null,
            'saeb_lp' => null,
            'saeb_mat' => null,
            'has_fundeb' => false,
            'has_censo' => false,
            'has_saeb' => false,
            'consultoria_active' => false,
            'in_catalog' => false,
        ], ['saeb_p25' => null, 'compl_ratio_median' => null], 70, 40);

        $this->assertSame('data_sparse', $result['tier']);
        $this->assertSame(0, $result['success_score']);
    }

    #[Test]
    public function benchmarks_compute_median_and_p25(): void
    {
        $bench = $this->scorer->benchmarks([100, 200, 300, 400], [0.1, 0.2, 0.3]);

        $this->assertSame(200.0, $bench['saeb_p25']);
        $this->assertSame(0.2, $bench['compl_ratio_median']);
    }
}
