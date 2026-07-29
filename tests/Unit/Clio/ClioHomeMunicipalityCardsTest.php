<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Home\ClioHomeMunicipalityCards;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioHomeMunicipalityCardsTest extends TestCase
{
    #[Test]
    public function agrupa_coletas_do_mesmo_municipio_num_unico_card(): void
    {
        $older = new ClioCampaign([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'city_id' => 10,
            'municipality_name' => 'Irecê',
            'uf' => 'BA',
            'ibge_municipio' => '2914604',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'reference_date' => '2026-05-01',
        ]);
        $older->created_at = Carbon::parse('2026-05-10 10:00:00');
        $older->findings_error_count = 0;
        $older->findings_warning_count = 0;
        $older->artifacts_count = 3;
        $older->setRelation('schools', collect());
        $older->setRelation('inferences', collect());
        $older->setRelation('artifacts', collect());
        $older->setRelation('acompArtifact', null);

        $newer = new ClioCampaign([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'city_id' => 10,
            'municipality_name' => 'Irecê',
            'uf' => 'BA',
            'ibge_municipio' => '2914604',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_PARSED,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'reference_date' => '2026-07-01',
        ]);
        $newer->created_at = Carbon::parse('2026-07-15 12:00:00');
        $newer->findings_error_count = 2;
        $newer->findings_warning_count = 1;
        $newer->artifacts_count = 5;
        $newer->setRelation('schools', collect());
        $newer->setRelation('inferences', collect());
        $newer->setRelation('artifacts', collect());
        $newer->setRelation('acompArtifact', null);

        $other = new ClioCampaign([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'city_id' => 20,
            'municipality_name' => 'Salvador',
            'uf' => 'BA',
            'ibge_municipio' => '2927408',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_DRAFT,
            'profile' => ClioCampaign::PROFILE_CONSULTANCY,
            'reference_date' => null,
        ]);
        $other->created_at = Carbon::parse('2026-06-01 08:00:00');
        $other->findings_error_count = 0;
        $other->findings_warning_count = 0;
        $other->artifacts_count = 0;
        $other->setRelation('schools', collect());
        $other->setRelation('inferences', collect());
        $other->setRelation('artifacts', collect());
        $other->setRelation('acompArtifact', null);

        $cards = (new ClioHomeMunicipalityCards)->group(collect([$older, $newer, $other]));

        $this->assertCount(2, $cards);
        $irece = $cards->firstWhere('municipality_name', 'Irecê');
        $this->assertNotNull($irece);
        $this->assertCount(2, $irece['campaigns']);
        $this->assertSame((string) $newer->uuid, (string) $irece['selected']->uuid);
        $this->assertSame((string) $newer->uuid, $irece['alpine']['selectedId']);
        $this->assertCount(2, $irece['alpine']['collections']);
        $this->assertStringContainsString('01/07/2026', $irece['alpine']['collections'][0]['label']);
        $this->assertStringContainsString('15/07/2026', $irece['alpine']['collections'][0]['label']);
    }
}
