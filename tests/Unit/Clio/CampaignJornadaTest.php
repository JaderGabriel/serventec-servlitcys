<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use App\Models\Clio\ClioCampaignInference;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignJornadaTest extends TestCase
{
    #[Test]
    public function aggregate_turmas_detecta_turno_e_ch(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoTurmaEscola_21_7_2026.csv');
        $csv = new CsvReader;
        $data = $csv->read($path, 1);
        $agg = (new RelationCsvAggregator)->aggregateTurmas($data['rows'], $csv);

        $this->assertTrue($agg['columns']['turno']);
        $this->assertTrue($agg['columns']['carga_horaria']);
        $this->assertSame(5, $agg['total']);
        $this->assertArrayHasKey('Manhã', $agg['by_turno']);
        $this->assertArrayHasKey('Integral', $agg['by_turno']);
        $this->assertArrayHasKey('≥ 35 h — tempo integral', $agg['by_ch_band']);
        $this->assertNotEmpty($agg['by_ch_exact'] ?? []);
        $this->assertTrue($agg['turma_profiles']['TUR-INF']['extended']);
        $this->assertTrue($agg['turma_profiles']['TUR-INF']['infantil']);
        $this->assertSame(RelationCsvAggregator::BUCKET_AEE, $agg['turma_profiles']['TUR-AEE']['bucket']);
    }

    #[Test]
    public function aggregate_enrollment_separa_fund_aee_ac_e_infantil_estendido(): void
    {
        $csv = new CsvReader;
        $turmaPath = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoTurmaEscola_21_7_2026.csv');
        $alunoPath = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoAlunoEscola_21_7_2026.csv');
        $turmaAgg = (new RelationCsvAggregator)->aggregateTurmas($csv->read($turmaPath, 1)['rows'], $csv);
        $pattern = (new RelationCsvAggregator)->aggregateEnrollmentDayPatterns(
            $csv->read($alunoPath, 1)['rows'],
            $csv,
            $turmaAgg['turma_profiles'],
        );

        $this->assertSame(6, $pattern['people']);
        $this->assertSame(1, $pattern['fund_aee_contraturno']);
        $this->assertSame(1, $pattern['curricular_ac']);
        $this->assertSame(1, $pattern['infantil_turma_estendida']);
        $this->assertGreaterThanOrEqual(2, $pattern['multi_enrollment']);
    }

    #[Test]
    public function presenter_expoe_secao_jornada_ativas(): void
    {
        $campaign = new \App\Models\Clio\ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => \App\Models\Clio\ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $inf = new ClioCampaignInference([
            'code' => 'INF-JOR',
            'summary' => 'Jornada de teste',
            'payload' => [
                'people' => 6,
                'turmas' => 5,
                'fund_aee_contraturno' => 1,
                'curricular_ac' => 1,
                'infantil_turma_estendida' => 1,
                'multi_enrollment' => 2,
                'has_turno_columns' => true,
                'has_ch_columns' => true,
                'by_turno' => ['Manhã' => 1, 'Tarde' => 3, 'Integral' => 1],
                'by_ch_band' => ['≤ 14 h — parcial curta' => 4, '≥ 35 h — tempo integral' => 1],
                'schools' => [
                    [
                        'inep' => '29174651',
                        'name' => 'Alpha',
                        'functioning' => 'Em atividade',
                        'turmas' => 5,
                        'people' => 6,
                        'fund_aee_contraturno' => 1,
                        'curricular_ac' => 1,
                        'infantil_turma_estendida' => 1,
                        'multi_enrollment' => 2,
                        'by_turno' => ['Manhã' => 1],
                        'by_ch_band' => [],
                        'has_turno' => true,
                        'has_ch' => true,
                    ],
                ],
            ],
        ]);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            [
                'schools_total' => 0,
                'schools_triade_complete' => 0,
                'triade_coverage_pct' => 0,
                'has_acomp' => false,
                'schools' => [],
            ],
            Collection::make(['INF-JOR' => $inf]),
            Collection::make([]),
        );

        $this->assertTrue($dash['jornada']['available'] ?? false);
        $this->assertSame(1, $dash['jornada']['fund_aee_contraturno']);
        $this->assertSame(1, $dash['jornada']['curricular_ac']);
        $this->assertSame(1, $dash['jornada']['infantil_turma_estendida']);
        $this->assertNotEmpty($dash['jornada']['by_turno']);
        $this->assertContains('INF-JOR', collect($dash['highlights'])->pluck('code')->all());
    }
}
