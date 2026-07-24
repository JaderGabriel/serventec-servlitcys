<?php

namespace Tests\Unit\Clio;

use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioEnrollmentStage;
use App\Models\Bi\BiClioInclusion;
use App\Models\Bi\BiClioQuality;
use App\Models\Bi\BiClioSchool;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Bi\ClioBiDashboardComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioBiDashboardComposerTest extends TestCase
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
    public function charts_inclui_triade_matriculas_etapas_e_inclusao(): void
    {
        $city = City::factory()->create([
            'name' => 'Município BI Teste',
            'uf' => 'BA',
            'ibge_municipio' => '2910800',
        ]);

        $campaign = ClioCampaign::query()->create([
            'city_id' => $city->id,
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'municipality_name' => $city->name,
            'uf' => $city->uf,
        ]);

        $bi = BiClioCampaign::query()->create([
            'campaign_id' => $campaign->id,
            'city_id' => $city->id,
            'year' => 2026,
            'municipality_name' => $city->name,
            'uf' => $city->uf,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'triade_pct' => 85.5,
            'schools_active' => 4,
            'schools_total' => 5,
            'mat_curricular' => 1000,
            'mat_aee' => 40,
            'mat_ac' => 10,
            'findings_errors' => 2,
            'findings_warnings' => 1,
            'distortion_pct' => 12.0,
            'density_avg' => 22.5,
            'nee_people' => 30,
            'refreshed_at' => now(),
        ]);

        BiClioEnrollmentStage::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => null,
            'etapa' => 'Ensino Fundamental de 9 anos - 1º Ano',
            'qt_alunos' => 120,
            'qt_turmas' => 5,
        ]);
        BiClioEnrollmentStage::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => null,
            'etapa' => 'Educação Infantil - Pré-escola',
            'qt_alunos' => 80,
            'qt_turmas' => 4,
        ]);

        BiClioSchool::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => '12345678',
            'name' => 'Escola Alfa',
            'is_active' => true,
            'triade_parts' => 2,
            'rows_aluno' => 50,
            'delta_curricular' => -15,
            'findings_errors' => 3,
        ]);
        BiClioSchool::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => '87654321',
            'name' => 'Escola Beta',
            'is_active' => true,
            'triade_parts' => 3,
            'rows_aluno' => 40,
            'delta_curricular' => 0,
            'findings_errors' => 0,
        ]);

        BiClioQuality::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => '12345678',
            'missing_triad' => true,
        ]);
        BiClioQuality::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => '87654321',
            'missing_triad' => false,
        ]);

        BiClioInclusion::query()->create([
            'campaign_id' => $campaign->id,
            'inep' => '12345678',
            'qt_nee_people' => 10,
            'qt_deficiency' => 6,
            'qt_disorder' => 3,
            'qt_ah' => 1,
            'qt_without_aee' => 2,
            'qt_aee_without_nee' => 1,
        ]);

        $charts = (new ClioBiDashboardComposer(new EtapaLabelOrder))->charts((int) $campaign->id, $bi);

        $this->assertArrayHasKey('triade', $charts);
        $this->assertSame('doughnut', $charts['triade']['type']);
        $this->assertArrayHasKey('matriculas', $charts);
        $this->assertCount(3, $charts['matriculas']['labels']);
        $this->assertArrayHasKey('etapas', $charts);
        $this->assertNotEmpty($charts['etapas']['labels']);
        $this->assertArrayHasKey('inclusao', $charts);
        $this->assertArrayHasKey('aee_gap', $charts);
        $this->assertArrayHasKey('qualidade', $charts);
        $this->assertArrayHasKey('escolas', $charts);
    }
}
