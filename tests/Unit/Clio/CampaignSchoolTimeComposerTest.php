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
}
