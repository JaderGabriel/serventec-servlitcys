<?php

namespace Tests\Unit\Clio;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\User;
use App\Services\Clio\Ingest\CampaignIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite necessária para RefreshDatabase neste ambiente.');
        }

        parent::setUp();
    }

    #[Test]
    public function ingere_zip_fixture_com_kinds_correctos(): void
    {
        Storage::fake('local');
        config(['clio.enabled' => true, 'clio.disk' => 'local']);

        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'Santo Amaro Smoke',
            'uf' => 'BA',
            'ibge_municipio' => '2929000',
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
        $result = app(CampaignIngestService::class)->ingestFromPath($campaign, $zip);

        $this->assertGreaterThanOrEqual(4, $result['stored']);
        $this->assertSame(0, $result['ignored']); // locks counted as ignored inside expander (not returned as ignored count from zip children)

        $kinds = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('kind')
            ->all();

        $this->assertContains('pacote_zip', $kinds);
        $this->assertContains('relacao_aluno_escola', $kinds);
        $this->assertContains('relacao_turma_escola', $kinds);
        $this->assertContains('relacao_profissional_escola', $kinds);

        $this->assertTrue(
            ClioCampaignArtifact::query()
                ->where('campaign_id', $campaign->id)
                ->where('kind', '!=', 'pacote_zip')
                ->where('parse_status', ClioCampaignArtifact::PARSE_PENDING)
                ->exists()
        );

        $campaign->refresh();
        $this->assertSame(ClioCampaign::STATUS_INGESTING, $campaign->status);
        $this->assertGreaterThanOrEqual(1, $campaign->schools()->count());
    }

    #[Test]
    public function duplicata_com_parse_failed_volta_a_pending_para_reprocessar(): void
    {
        Storage::fake('local');
        config(['clio.enabled' => true, 'clio.disk' => 'local']);

        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'Itamari Teste',
            'uf' => 'BA',
            'ibge_municipio' => '2915000',
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

        $csv = "Data de Referência,UF,Código da escola,Nome da escola\n21/07/2026,BA,29123456,Escola Teste\n";
        $tmpDir = sys_get_temp_dir().'/clio_ingest_'.uniqid();
        mkdir($tmpDir);
        $path = $tmpDir.'/Relatorio_Acomp_Coleta_1Etapa_21072026.csv';
        file_put_contents($path, $csv);

        try {
            $ingest = app(CampaignIngestService::class);
            $first = $ingest->ingestFromPath($campaign, $tmpDir);
            $this->assertSame(1, $first['stored']);
            $artifact = $first['artifacts'][0];
            $artifact->update([
                'parse_status' => ClioCampaignArtifact::PARSE_FAILED,
                'parse_meta' => array_merge(is_array($artifact->parse_meta) ? $artifact->parse_meta : [], [
                    'code' => 'EDU-REL-COLS',
                ]),
            ]);

            $second = $ingest->ingestFromPath($campaign, $tmpDir);
            $this->assertSame(1, $second['duplicates']);
            $this->assertSame(0, $second['stored']);
            $retried = $second['artifacts'][0]->fresh();
            $this->assertSame(ClioCampaignArtifact::PARSE_PENDING, $retried->parse_status);

            $stats = app(\App\Services\Clio\Parse\CampaignParseService::class)->parseCampaign($campaign->fresh());
            $this->assertGreaterThanOrEqual(1, $stats['parsed']);
            $this->assertSame(ClioCampaignArtifact::PARSE_OK, $retried->fresh()->parse_status);
        } finally {
            @unlink($path);
            @rmdir($tmpDir);
        }
    }
}
