<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSocialDemandScorer;
use App\Support\Horizonte\HorizonteTransferScoring;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonteSocialDemandScorerTest extends TestCase
{
    #[Test]
    public function social_demand_high_when_cadunico_exceeds_censo(): void
    {
        $score = HorizonteSocialDemandScorer::socialDemandScore(
            matriculasCenso: 8000,
            cadunicoEscolar: 12000,
            sidraPop417: null,
            pctCriancasPbf: 45.0,
        );

        $this->assertGreaterThanOrEqual(40, $score);
    }

    #[Test]
    public function transfer_dependency_uses_median_ratio(): void
    {
        $score = HorizonteSocialDemandScorer::transferDependencyScore(
            transferTotal: 2_000_000.0,
            receitaFundeb: 4_000_000.0,
            complementacaoFundeb: 500_000.0,
            medianRatio: 0.25,
        );

        $this->assertGreaterThan(50, $score);
    }

    #[Test]
    public function transfer_scoring_falls_back_to_fundeb_complementacao(): void
    {
        $total = HorizonteTransferScoring::resolveTotalForScoring(
            transfer: null,
            fundeb: ['complementacao_total' => 800_000.0, 'receita_total' => 4_000_000.0],
        );

        $this->assertSame(800_000.0, $total);

        $score = HorizonteSocialDemandScorer::transferDependencyScore(
            transferTotal: $total,
            receitaFundeb: 4_000_000.0,
            complementacaoFundeb: 800_000.0,
            medianRatio: 0.2,
        );

        $this->assertGreaterThan(0, $score);
    }
}
