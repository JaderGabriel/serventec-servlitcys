<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignAnalysisPresenterTest extends TestCase
{
    #[Test]
    public function consolida_kpis_triade_e_achados(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $coverage = [
            'schools_total' => 2,
            'schools_triade_complete' => 1,
            'triade_coverage_pct' => 50.0,
            'has_acomp' => true,
            'reference_date' => '2026-07-21',
            'schools' => [
                [
                    'inep' => '29085608',
                    'name' => 'Escola A',
                    'aluno' => true,
                    'turma' => true,
                    'profissional' => true,
                    'triade' => true,
                ],
                [
                    'inep' => '29085667',
                    'name' => 'Escola B',
                    'aluno' => true,
                    'turma' => false,
                    'profissional' => false,
                    'triade' => false,
                ],
            ],
        ];

        $finding = new ClioCampaignFinding([
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'code' => 'CLIO-TEST',
            'message' => 'Revisar arquivo',
        ]);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            $coverage,
            collect(),
            collect([$finding]),
        );

        $this->assertSame(50.0, $dash['triade']['pct']);
        $this->assertSame(1, $dash['triade']['complete']);
        $this->assertCount(6, $dash['kpis']);
        $this->assertCount(2, $dash['schools']);
        $this->assertSame(1, $dash['findings']['warning_count']);
        $this->assertArrayHasKey('glossary', $dash);
        $this->assertNotEmpty($dash['glossary']);
        $this->assertArrayHasKey('school_filters', $dash);
        $this->assertArrayHasKey('counters', $dash);
        $this->assertSame(1, $dash['counters']['warnings']);
        $this->assertSame(0, $dash['counters']['errors']);

        $incomplete = collect($dash['schools'])->firstWhere('inep', '29085667');
        $this->assertSame(__('Incompleta'), $incomplete['status']);
        $this->assertSame('incomplete', $incomplete['filter']);
        $this->assertContains(__('Turmas'), $incomplete['missing']);
        $this->assertArrayHasKey('report', $dash);
    }

    #[Test]
    public function escolas_extintas_nao_aparecem_como_incompletas(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Saubara',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $extinta = new ClioCampaignSchool([
            'inep_code' => '29199999',
            'name' => 'Escola Extinta Gamma',
            'functioning_status' => 'Extinta',
            'collection_form' => 'Não iniciou',
            'dependency' => 'Municipal',
        ]);
        $ativa = new ClioCampaignSchool([
            'inep_code' => '29174651',
            'name' => 'Escola Municipal Alpha',
            'functioning_status' => 'Em Atividade',
            'collection_form' => 'Educacenso Web',
            'dependency' => 'Municipal',
        ]);
        $campaign->setRelation('schools', collect([$extinta, $ativa]));
        $campaign->setRelation('artifacts', new Collection);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            [
                'schools_total' => 2,
                'schools_triade_complete' => 0,
                'triade_coverage_pct' => 0,
                'has_acomp' => true,
                'schools' => [
                    [
                        'inep' => '29199999',
                        'name' => 'Escola Extinta Gamma',
                        'aluno' => false,
                        'turma' => false,
                        'profissional' => false,
                        'triade' => false,
                    ],
                    [
                        'inep' => '29174651',
                        'name' => 'Escola Municipal Alpha',
                        'aluno' => true,
                        'turma' => false,
                        'profissional' => false,
                        'triade' => false,
                    ],
                ],
            ],
            collect(),
            collect(),
        );

        $ext = collect($dash['schools'])->firstWhere('inep', '29199999');
        $inc = collect($dash['schools'])->firstWhere('inep', '29174651');
        $this->assertTrue($ext['inactive']);
        $this->assertSame('inactive', $ext['filter']);
        $this->assertSame(__('Extinta'), $ext['status']);
        $this->assertSame([], $ext['missing']);
        $this->assertSame('incomplete', $inc['filter']);
        $this->assertSame(1, $dash['counters']['schools_incomplete']);
        $this->assertSame(1, $dash['counters']['schools_inactive']);
        $this->assertSame('29174651', $dash['schools'][0]['inep']);
        $this->assertSame('29199999', $dash['schools'][1]['inep']);
    }

    #[Test]
    public function consolida_painel_da_escola(): void
    {
        $school = new ClioCampaignSchool([
            'inep_code' => '29085608',
            'name' => 'Escola Alpha',
            'functioning_status' => 'Em atividade',
            'collection_form' => 'Em andamento',
            'dependency' => 'Municipal',
            'meta' => ['total_curricular' => 120],
        ]);
        $artifact = new ClioCampaignArtifact([
            'kind' => 'relacao_aluno_escola',
            'original_name' => 'RelacaoAlunoEscola.csv',
            'parse_status' => 'ok',
            'row_count' => 118,
        ]);
        $school->setRelation('artifacts', collect([$artifact]));

        $finding = new ClioCampaignFinding([
            'severity' => ClioCampaignFinding::SEVERITY_ERROR,
            'code' => 'CLIO-TRI',
            'message' => 'Falta turma',
        ]);

        $dash = (new CampaignAnalysisPresenter)->presentSchool(
            $school,
            [
                'inep' => '29085608',
                'name' => 'Escola Alpha',
                'aluno' => true,
                'turma' => false,
                'profissional' => false,
                'triade' => false,
            ],
            collect([$finding]),
        );

        $this->assertSame(__('Com erros'), $dash['status']);
        $this->assertSame(1, $dash['findings']['error_count']);
        $this->assertContains(__('Turmas'), $dash['triade']['missing']);
        $this->assertCount(6, $dash['kpis']);
        $this->assertCount(1, $dash['files']);
    }
}
