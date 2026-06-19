<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSocialDemandScorer;
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
}
