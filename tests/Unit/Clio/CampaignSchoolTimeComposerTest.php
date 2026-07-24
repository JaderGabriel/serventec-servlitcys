<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\CampaignSchoolTimeComposer;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignSchoolTimeComposerTest extends TestCase
{
    #[Test]
    public function compose_returns_empty_structure_without_artifacts(): void
    {
        $campaign = new \App\Models\Clio\ClioCampaign;
        $campaign->setRelation('artifacts', collect());
        $campaign->year = 2026;

        $result = (new CampaignSchoolTimeComposer(new RelationCsvAggregator))->compose($campaign);

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['segments']);
        $this->assertNull($result['network']['horas_aluno_semana']);
    }

    #[Test]
    public function ch_options_out_ordena_por_horas(): void
    {
        $composer = new CampaignSchoolTimeComposer(new RelationCsvAggregator);
        $method = new \ReflectionMethod(CampaignSchoolTimeComposer::class, 'chOptionsOut');
        $method->setAccessible(true);

        $rows = $method->invoke($composer, [
            '40' => ['hours' => 40.0, 'turmas' => 2, 'alunos' => 50],
            '20' => ['hours' => 20.0, 'turmas' => 5, 'alunos' => 120],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame(20.0, $rows[0]['hours']);
        $this->assertSame(40.0, $rows[1]['hours']);
        $this->assertSame(5, $rows[0]['turmas']);
    }
}
