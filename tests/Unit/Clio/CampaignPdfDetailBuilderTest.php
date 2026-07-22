<?php

namespace Tests\Unit\Clio;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Models\User;
use App\Services\Clio\Export\CampaignPdfDetailBuilder;
use App\Services\Clio\Export\CampaignPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignPdfDetailBuilderTest extends TestCase
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
        ]);
    }

    #[Test]
    public function monta_tabelas_de_distorcao_demografia_e_nee_com_destaque_sem_aee(): void
    {
        $campaign = $this->seedCampaignFromAlphaFixture();

        $tables = app(CampaignPdfDetailBuilder::class)->build($campaign->fresh(['artifacts.school', 'inferences']));

        $this->assertNotEmpty($tables['distortion_by_etapa']);
        $this->assertSame(1, $tables['missing_demographics_total']);
        $this->assertSame('Cor/Raça', $tables['missing_demographics'][0]['faltando']);

        $this->assertGreaterThanOrEqual(3, $tables['nee_total']);
        $this->assertGreaterThanOrEqual(2, $tables['nee_without_aee']);

        $withAee = collect($tables['nee_students'])->firstWhere('has_aee', true);
        $withoutAee = collect($tables['nee_students'])->firstWhere('has_aee', false);
        $this->assertNotNull($withAee);
        $this->assertNotNull($withoutAee);
        $this->assertStringContainsString('NEE sem matrícula AEE', $withoutAee['aee_flag']);
        $this->assertFalse($tables['nee_students'][0]['has_aee']);
        $this->assertNotSame('—', $tables['nee_students'][0]['name']);
        $this->assertNotSame('', $tables['nee_students'][0]['name']);
        $this->assertArrayHasKey('cpf', $tables['nee_students'][0]);
        $this->assertSame('ALUNO_004', $tables['missing_demographics'][0]['name']);
    }

    #[Test]
    public function pontos_de_atencao_colocam_rede_por_ultimo(): void
    {
        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'PDF Sort',
            'uf' => 'BA',
            'ibge_municipio' => '2910800',
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
            'status' => ClioCampaign::STATUS_ANALYZED,
            'created_by' => $admin->id,
        ]);
        $school = ClioCampaignSchool::query()->create([
            'campaign_id' => $campaign->id,
            'inep_code' => '29174651',
            'name' => 'Escola Alpha',
        ]);
        ClioCampaignFinding::query()->create([
            'campaign_id' => $campaign->id,
            'school_id' => null,
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'code' => 'CLIO-DEM-COR-VAZIO',
            'message' => 'Aviso rede',
        ]);
        ClioCampaignFinding::query()->create([
            'campaign_id' => $campaign->id,
            'school_id' => $school->id,
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'code' => 'CLIO-COE-TRIADE',
            'message' => 'Aviso escola',
        ]);

        $sorted = $campaign->fresh()->findings
            ->where('severity', ClioCampaignFinding::SEVERITY_WARNING)
            ->sortBy(fn (ClioCampaignFinding $f): int => $f->school_id === null ? 1 : 0)
            ->values();

        $this->assertSame('Aviso escola', $sorted[0]->message);
        $this->assertSame('Aviso rede', $sorted[1]->message);
        $this->assertNull($sorted[1]->school_id);
    }

    #[Test]
    public function export_pdf_gera_binario(): void
    {
        $campaign = $this->seedCampaignFromAlphaFixture();
        $response = app(CampaignPdfExporter::class)->download($campaign->fresh([
            'schools',
            'artifacts.school',
            'inferences',
            'findings.school',
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    private function seedCampaignFromAlphaFixture(): ClioCampaign
    {
        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'Mairi Teste PDF',
            'uf' => 'BA',
            'ibge_municipio' => '2920100',
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
            'status' => ClioCampaign::STATUS_ANALYZED,
            'created_by' => $admin->id,
        ]);
        $school = ClioCampaignSchool::query()->create([
            'campaign_id' => $campaign->id,
            'inep_code' => '29174651',
            'name' => 'Escola Municipal Alpha',
        ]);

        $base = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha');
        $disk = (string) config('clio.disk', 'local');
        $root = 'clio/test-pdf-'.uniqid();
        Storage::disk($disk)->makeDirectory($root);

        foreach (
            [
                'relacao_aluno_escola' => 'RelacaoAlunoEscola_21_7_2026.csv',
                'relacao_turma_escola' => 'RelacaoTurmaEscola_21_7_2026.csv',
            ] as $kind => $file
        ) {
            $rel = $root.'/'.$file;
            Storage::disk($disk)->put($rel, (string) file_get_contents($base.'/'.$file));
            ClioCampaignArtifact::query()->create([
                'campaign_id' => $campaign->id,
                'school_id' => $school->id,
                'kind' => $kind,
                'original_name' => $file,
                'storage_path' => $rel,
                'sha256' => hash('sha256', $rel),
                'size_bytes' => 100,
                'parse_status' => ClioCampaignArtifact::PARSE_OK,
                'row_count' => 5,
                'parse_meta' => [],
            ]);
        }

        return $campaign->fresh(['artifacts.school', 'inferences']);
    }
}
