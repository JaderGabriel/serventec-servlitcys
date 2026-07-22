<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\RelacaoTurmaEscolaParser;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignReportSectionTest extends TestCase
{
    #[Test]
    public function agregador_classifica_tipos_de_turma_educacenso(): void
    {
        $agg = new RelationCsvAggregator;
        $this->assertSame(RelationCsvAggregator::BUCKET_AEE, $agg->classifyTipoTurma('Atendimento Educacional Especializado (AEE)'));
        $this->assertSame(RelationCsvAggregator::BUCKET_AC, $agg->classifyTipoTurma('Atividade complementar'));
        $this->assertSame(RelationCsvAggregator::BUCKET_CURRICULAR, $agg->classifyTipoTurma('Curricular'));
    }

    #[Test]
    public function parser_turma_grava_agregados_por_etapa_e_tipo(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoTurmaEscola_21_7_2026.csv');
        $result = (new RelacaoTurmaEscolaParser(new CsvReader))->parse($path, new \App\Models\Clio\ClioCampaignArtifact);

        $this->assertSame(4, $result->rowCount);
        $this->assertSame(2, $result->meta['aggregates']['by_tipo_bucket']['curricular']);
        $this->assertSame(1, $result->meta['aggregates']['by_tipo_bucket']['aee']);
        $this->assertSame(1, $result->meta['aggregates']['by_tipo_bucket']['atividade_complementar']);
        $this->assertArrayHasKey('Ensino Fundamental de 9 anos - 1º Ano', $result->meta['aggregates']['by_etapa_ensino']);
    }

    #[Test]
    public function presenter_monta_secao_report_municipal(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $inferences = collect([
            'INF-MAT' => new ClioCampaignInference([
                'code' => 'INF-MAT',
                'summary' => 'Matrícula teste',
                'payload' => [
                    'acomp_curricular_sum' => 165,
                    'acomp_aee_sum' => 8,
                    'acomp_ac_sum' => 15,
                    'relacao_aluno_rows' => 3,
                    'by_etapa_ensino' => ['Ensino Fundamental de 9 anos - 1º Ano' => 2],
                    'has_acomp_aee_column' => true,
                    'has_acomp_ac_column' => true,
                    'schools' => [
                        [
                            'inep' => '29174651',
                            'name' => 'Alpha',
                            'alunos' => 3,
                            'acomp_curricular' => 120,
                            'acomp_aee' => 8,
                            'acomp_ac' => 15,
                        ],
                    ],
                ],
            ]),
            'INF-TUR' => new ClioCampaignInference([
                'code' => 'INF-TUR',
                'summary' => 'Turmas teste',
                'payload' => [
                    'relacao_turma_rows' => 4,
                    'by_etapa_ensino' => ['Ensino Fundamental de 9 anos - 1º Ano' => 1],
                    'by_etapa_agregada' => ['Anos iniciais' => 2],
                    'by_mediacao' => ['Presencial' => 4],
                    'by_tipo_bucket' => [
                        'curricular' => 2,
                        'aee' => 1,
                        'atividade_complementar' => 1,
                        'outra' => 0,
                    ],
                    'schools' => [
                        [
                            'inep' => '29174651',
                            'name' => 'Alpha',
                            'turmas' => 4,
                            'curricular' => 2,
                            'aee' => 1,
                            'atividade_complementar' => 1,
                        ],
                    ],
                ],
            ]),
        ]);

        $finding = new ClioCampaignFinding([
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'code' => 'CLIO-TUR-AEE-AUSENTE',
            'message' => 'Falta turma AEE',
        ]);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            [
                'schools_total' => 1,
                'schools_triade_complete' => 1,
                'triade_coverage_pct' => 100.0,
                'has_acomp' => true,
                'schools' => [],
            ],
            $inferences,
            collect([$finding]),
        );

        $this->assertTrue($dash['report']['available']);
        $this->assertNotEmpty($dash['report']['totals']);
        $this->assertNotEmpty($dash['report']['turmas_por_ano']);
        $this->assertNotEmpty($dash['report']['schools']);
        $this->assertSame(1, $dash['report']['schools'][0]['turmas_aee']);
        $this->assertNotEmpty($dash['report']['apontamentos']);
        $this->assertArrayHasKey('acomp', $dash);
        $this->assertTrue($dash['acomp']['available']);
        $this->assertArrayHasKey('schools_overview', $dash);
        $this->assertArrayHasKey('cross_checks', $dash);
    }

    #[Test]
    public function presenter_monta_cruzamentos_quando_ha_inf_xchk(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $inferences = collect([
            'INF-XCHK' => new ClioCampaignInference([
                'code' => 'INF-XCHK',
                'summary' => 'Cruzamentos teste',
                'payload' => [
                    'has_acomp' => true,
                    'acomp_by_etapa_note' => 'Sem desagregação por ano',
                    'network' => [
                        'acomp_curricular' => 100,
                        'relacao_aluno_rows' => 98,
                        'delta_curricular' => -2,
                        'ok' => false,
                    ],
                    'etapa_compare' => [
                        ['etapa' => '1º Ano', 'alunos' => 10, 'turmas' => 1, 'flag' => null],
                        ['etapa' => '2º Ano', 'alunos' => 5, 'turmas' => 0, 'flag' => 'alunos_sem_turma'],
                    ],
                    'etapas_alunos_sem_turma' => 1,
                    'etapas_turmas_sem_aluno' => 0,
                    'etapa_ok' => false,
                ],
            ]),
            'INF-MAT' => new ClioCampaignInference([
                'code' => 'INF-MAT',
                'summary' => 'Mat',
                'payload' => [
                    'acomp_curricular_sum' => 100,
                    'relacao_aluno_rows' => 98,
                ],
            ]),
        ]);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            [
                'schools_total' => 0,
                'schools_triade_complete' => 0,
                'triade_coverage_pct' => 0,
                'has_acomp' => true,
                'reference_date' => '2026-07-21',
                'schools' => [],
            ],
            $inferences,
            collect(),
        );

        $this->assertTrue($dash['cross_checks']['available']);
        $this->assertCount(2, $dash['cross_checks']['checks']);
        $this->assertFalse($dash['cross_checks']['checks'][0]['ok']);
        $this->assertCount(2, $dash['cross_checks']['etapa_rows']);
    }
}
