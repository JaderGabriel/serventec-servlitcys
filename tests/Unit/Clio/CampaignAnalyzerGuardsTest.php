<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignInference;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignAnalyzerGuardsTest extends TestCase
{
    #[Test]
    public function mascara_identificadores_em_achados_dup(): void
    {
        $analyzer = new CampaignAnalyzer(app(CampaignParseService::class));
        $method = new ReflectionMethod(CampaignAnalyzer::class, 'maskIdentifier');

        $this->assertSame('[redacted]', $method->invoke($analyzer, '12345678901'));
        $this->assertSame('AB**YZ', $method->invoke($analyzer, 'AB12YZ'));
        $this->assertSame('****', $method->invoke($analyzer, 'abcd'));
    }

    #[Test]
    public function triade_pct_vem_de_inf_coe_nao_inf_col(): void
    {
        $campaign = new ClioCampaign;
        $col = new ClioCampaignInference([
            'code' => 'INF-COL',
            'payload' => ['nao_iniciou' => 1],
        ]);
        $coe = new ClioCampaignInference([
            'code' => 'INF-COE',
            'payload' => ['triade_coverage_pct' => 87.5],
        ]);
        $campaign->setRelation('inferences', new Collection([$col, $coe]));

        $this->assertSame(87.5, $campaign->triadeCoveragePct());
    }
}
