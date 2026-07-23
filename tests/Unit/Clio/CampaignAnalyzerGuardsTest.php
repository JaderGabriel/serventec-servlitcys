<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignInference;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignAnalyzerGuardsTest extends TestCase
{
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

    #[Test]
    public function school_scope_stats_ignora_extintas_na_triade(): void
    {
        $campaign = new ClioCampaign;

        $active = new \App\Models\Clio\ClioCampaignSchool([
            'inep_code' => '1',
            'name' => 'Ativa',
            'functioning_status' => 'Em Atividade',
        ]);
        $active->setRelation('artifacts', new Collection([
            new \App\Models\Clio\ClioCampaignArtifact(['kind' => 'relacao_aluno_escola']),
            new \App\Models\Clio\ClioCampaignArtifact(['kind' => 'relacao_turma_escola']),
            new \App\Models\Clio\ClioCampaignArtifact(['kind' => 'relacao_profissional_escola']),
        ]));

        $extinct = new \App\Models\Clio\ClioCampaignSchool([
            'inep_code' => '2',
            'name' => 'Extinta',
            'functioning_status' => 'Extinta',
        ]);
        $extinct->setRelation('artifacts', new Collection);

        $incomplete = new \App\Models\Clio\ClioCampaignSchool([
            'inep_code' => '3',
            'name' => 'Incompleta',
            'functioning_status' => 'Em atividade',
        ]);
        $incomplete->setRelation('artifacts', new Collection([
            new \App\Models\Clio\ClioCampaignArtifact(['kind' => 'relacao_aluno_escola']),
        ]));

        $campaign->setRelation('schools', new Collection([$active, $extinct, $incomplete]));

        $scope = $campaign->schoolScopeStats();
        $this->assertSame(2, $scope['active']);
        $this->assertSame(1, $scope['other']);
        $this->assertSame(1, $scope['triade_complete']);
        $this->assertSame(50.0, $scope['triade_pct']);
        $this->assertSame(50.0, $campaign->triadeCoveragePct());
    }
}
