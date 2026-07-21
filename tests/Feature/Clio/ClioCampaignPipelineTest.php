<?php

namespace Tests\Feature\Clio;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignInference;
use App\Models\User;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fluxo S2→S4→S6 com fixtures: ingest → parse → analyze → export.
 */
final class ClioCampaignPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite necessária para RefreshDatabase neste ambiente.');
        }

        parent::setUp();
        config([
            'clio.enabled' => true,
            'clio.disk' => 'local',
            'legal.require_authenticated_consent' => false,
        ]);
        Storage::fake('local');
    }

    #[Test]
    public function ingest_parse_analyze_e_export_csv_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'Pipeline Clio',
            'uf' => 'BA',
            'ibge_municipio' => '2929752',
            'is_active' => true,
        ]);

        $campaign = ClioCampaign::query()->create([
            'city_id' => $city->id,
            'municipality_name' => $city->name,
            'uf' => 'BA',
            'ibge_municipio' => $city->ibge_municipio,
            'year' => 2026,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'status' => ClioCampaign::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $zip = base_path('tests/fixtures/clio/coleta_2026/Dados_SantoAmaro_smoke.zip');
        app(CampaignIngestService::class)->ingestFromPath($campaign, $zip);
        $parse = app(CampaignParseService::class)->parseCampaign($campaign->fresh());
        $this->assertGreaterThan(0, $parse['parsed'] ?? 0);

        $analyze = app(CampaignAnalyzer::class)->analyze($campaign->fresh());
        $this->assertGreaterThan(0, $analyze['inferences'] ?? 0);

        $campaign->refresh();
        $this->assertSame(ClioCampaign::STATUS_ANALYZED, $campaign->status);
        $this->assertTrue(
            ClioCampaignInference::query()->where('campaign_id', $campaign->id)->where('code', 'INF-COL')->exists()
        );

        $this->actingAs($admin)
            ->get(route('clio.campaigns.export.csv', $campaign))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->get(route('clio.campaigns.export.pdf', $campaign))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('clio.campaigns.export.csv', $campaign))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('clio.campaigns.analyze', $campaign))
            ->assertForbidden();
    }

    #[Test]
    public function clio_desligado_devolve_403(): void
    {
        config(['clio.enabled' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('clio.campaigns.index'))
            ->assertForbidden();
    }

    #[Test]
    public function rx_mostra_bloco_clio_para_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'RX Clio Mun',
            'uf' => 'BA',
            'ibge_municipio' => '2910800',
            'is_active' => true,
        ]);
        ClioCampaign::query()->create([
            'city_id' => $city->id,
            'municipality_name' => $city->name,
            'uf' => 'BA',
            'ibge_municipio' => $city->ibge_municipio,
            'year' => (int) config('clio.layout_year_default', 2026),
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.rx'))
            ->assertOk()
            ->assertSee('Campanhas Educacenso', false)
            ->assertSee('RX Clio Mun', false);
    }
}
