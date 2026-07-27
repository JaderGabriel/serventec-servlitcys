<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Export\CampaignActiveCensusMatrixBuilder;
use App\Services\Clio\Export\CampaignFinalPdfComposer;
use App\Services\Clio\Export\CensusExposurePdfTables;
use App\Services\Clio\Export\DiagnosticoGeralComposer;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Horizonte\HorizonteMunicipioEnrollmentSeriesService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignFinalPdfComposerTest extends TestCase
{
    #[Test]
    public function findings_for_filtra_por_token_do_codigo(): void
    {
        $composer = $this->composer();
        $method = new ReflectionMethod(CampaignFinalPdfComposer::class, 'findingsFor');

        $findings = collect([
            new ClioCampaignFinding([
                'code' => 'CLIO-DIS-ALTA',
                'severity' => ClioCampaignFinding::SEVERITY_ERROR,
                'message' => 'Distorção alta',
            ]),
            new ClioCampaignFinding([
                'code' => 'CLIO-TRA-RURAL',
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'message' => 'Transporte',
            ]),
            new ClioCampaignFinding([
                'code' => 'CLIO-NEE-SUB',
                'severity' => ClioCampaignFinding::SEVERITY_INFO,
                'message' => 'NEE',
            ]),
        ]);

        $rows = $method->invoke($composer, $findings, ['DIS', 'NEE']);

        $this->assertCount(2, $rows);
        $this->assertSame('CLIO-DIS-ALTA', $rows[0]['code']);
        $this->assertSame('CLIO-NEE-SUB', $rows[1]['code']);
    }

    #[Test]
    public function make_theme_marca_status_por_severidade(): void
    {
        $composer = $this->composer();
        $method = new ReflectionMethod(CampaignFinalPdfComposer::class, 'makeTheme');

        $withErrors = $method->invoke(
            $composer,
            'distorcao',
            'Distorção',
            'Lead',
            [['label' => 'A', 'value' => '1']],
            ['Diagnóstico curto'],
            [],
            [['severity' => ClioCampaignFinding::SEVERITY_ERROR, 'code' => 'X', 'message' => 'Erro', 'school' => null]],
        );

        $this->assertSame('Com erros', $withErrors['status']);
        $this->assertSame('rose', $withErrors['status_tone']);
        $this->assertSame(1, $withErrors['error_count']);

        $stable = $method->invoke(
            $composer,
            'rede',
            'Rede',
            'Lead',
            [['label' => 'A', 'value' => '1']],
            ['Ok'],
            [],
            [],
        );

        $this->assertSame('Estável', $stable['status']);
        $this->assertSame('emerald', $stable['status_tone']);
    }

    #[Test]
    public function tempos_escolares_usa_school_time_e_faixas_de_ch(): void
    {
        $composer = $this->composer();
        $method = new ReflectionMethod(CampaignFinalPdfComposer::class, 'themeTemposEscolares');

        $dashboard = [
            'highlights' => [
                ['code' => 'INF-JOR', 'summary' => 'Jornada disponível na rede.'],
            ],
            'jornada' => [
                'available' => true,
                'fund_aee_contraturno' => 12,
                'curricular_ac' => 4,
                'note_fund_aee' => 'Nota fund+AEE',
                'note_infantil' => '',
                'note_ch' => 'Nota CH',
                'school_time' => [
                    'available' => true,
                    'network' => [
                        'ch_media_aluno' => 22.5,
                        'horas_aluno_semana' => 22.5,
                    ],
                    'segments' => [
                        [
                            'label' => 'Fundamental I',
                            'turmas' => 10,
                            'alunos' => 200,
                            'ch_media_aluno' => 20,
                            'horas_aluno_semana' => 20,
                        ],
                    ],
                ],
                'by_ch_band' => [
                    ['short' => 'Parcial', 'label' => '20–24 h', 'count' => 8, 'pct' => 40],
                    ['short' => 'Integral', 'label' => '≥ 35 h', 'count' => 2, 'pct' => 10],
                ],
            ],
        ];

        $theme = $method->invoke($composer, $dashboard, collect());

        $this->assertNotNull($theme);
        $this->assertSame('tempos_escolares', $theme['key']);
        $this->assertSame('TEMPOS ESCOLARES', $theme['title']);
        $this->assertCount(2, $theme['tables']);
        $this->assertSame('Alunos e tempo na escola', $theme['tables'][0]['title']);
        $this->assertSame('Turmas por carga horária', $theme['tables'][1]['title']);
        $this->assertContains('Jornada disponível na rede.', $theme['diagnosis']);
        $this->assertContains('Nota fund+AEE', $theme['diagnosis']);
    }

    #[Test]
    public function inclusao_marca_atencao_quando_ha_nee_sem_aee(): void
    {
        $composer = $this->composer();
        $method = new ReflectionMethod(CampaignFinalPdfComposer::class, 'themeInclusao');

        $campaign = new ClioCampaign;
        $campaign->id = 1;
        $campaign->setRelation('schools', collect());

        $dashboard = [
            'highlights' => [
                ['code' => 'INF-NEE', 'summary' => 'Inclusão: 100 pessoa(s) · NEE sem AEE 57.'],
            ],
            'profile' => [
                'available' => true,
                'nee_flagged' => 100,
                'nee_without_aee' => 57,
                'nee_aee_without_condition' => 3,
                'underreporting_flagged' => 0,
                'by_nee' => [
                    ['label' => 'TEA', 'count' => 40],
                    ['label' => 'Deficiência intelectual', 'count' => 30],
                ],
            ],
            'report' => [],
        ];

        $theme = $method->invoke($composer, $campaign, $dashboard, collect());

        $this->assertNotNull($theme);
        $this->assertSame('Atenção', $theme['status']);
        $this->assertSame('amber', $theme['status_tone']);
        $this->assertSame('57', $theme['kpis'][1]['value']);
        $this->assertSame(__('Tipificação NEE (agregado)'), $theme['tables'][0]['title']);
        $this->assertTrue(
            collect($theme['findings'])->contains(fn (array $f): bool => ($f['code'] ?? '') === 'CLIO-NEE-SEM-AEE')
        );
    }

    #[Test]
    public function census_exposure_tables_converte_matriz_da_analise(): void
    {
        $tables = (new CensusExposurePdfTables)->format([
            'available' => true,
            'year' => 2025,
            'infantil' => [
                'title' => 'Educação infantil',
                'columns' => [
                    ['key' => 'creche_parcial', 'label' => 'Creche Parcial'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'creche_parcial' => [
                        'Urbana' => ['regular' => 10, 'especial' => 1],
                        'Rural' => ['regular' => 2, 'especial' => 0],
                    ],
                ],
            ],
            'fundamental' => [
                'title' => 'Educação fundamental',
                'columns' => [
                    ['key' => 'ai_parcial', 'label' => 'Fundamental I · Parcial'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'ai_parcial' => [
                        'Urbana' => ['regular' => 40, 'especial' => 0],
                        'Rural' => ['regular' => 5, 'especial' => 0],
                    ],
                ],
            ],
            'eja' => [
                'title' => 'EJA presencial fundamental',
                'columns' => [
                    ['key' => 'eja', 'label' => 'EJA Presencial Fundamental'],
                ],
                'rows' => [
                    'regular' => 'Regular',
                    'especial' => 'Especial',
                ],
                'values' => [
                    'eja' => [
                        'Urbana' => ['regular' => 8, 'especial' => 0],
                        'Rural' => ['regular' => 1, 'especial' => 0],
                    ],
                ],
            ],
            'geral' => [
                'title' => 'Análise geral',
                'columns' => [
                    ['key' => 'geral', 'label' => 'GERAL'],
                    ['key' => 'especial', 'label' => 'Educação Especial'],
                ],
                'values' => [
                    'geral' => 66,
                    'especial' => 1,
                ],
            ],
        ]);

        $this->assertCount(5, $tables);
        $this->assertStringContainsString('Exposição das matrículas', (string) ($tables[0]['title'] ?? ''));
        $this->assertStringContainsString('Educação infantil', (string) ($tables[0]['title'] ?? ''));
        $this->assertSame(['Matrícula', 'Creche Parcial'], $tables[0]['headers']);
        $this->assertSame('Regular', $tables[0]['rows'][0][0]);
        $this->assertSame('10 / 2', $tables[0]['rows'][0][1]['text']);
        $this->assertSame('urbana', $tables[0]['rows'][0][1]['parts'][0]['tone']);
        $this->assertSame('rural', $tables[0]['rows'][0][1]['parts'][2]['tone']);
        $this->assertSame('Especial', $tables[0]['rows'][1][0]);
        $this->assertSame('1 / 0', $tables[0]['rows'][1][1]['text']);
        $this->assertSame(CensusExposurePdfTables::KIND_LEGEND, $tables[3]['kind']);
        $this->assertStringContainsString('Análise geral', (string) ($tables[4]['title'] ?? ''));
        $this->assertSame(['66', '1'], $tables[4]['rows'][0]);
    }

    #[Test]
    public function matriculas_mantem_fluxo_com_modalidade_e_etapas_apos_exposicao(): void
    {
        $composer = $this->composer();
        $method = new ReflectionMethod(CampaignFinalPdfComposer::class, 'themeMatriculas');
        $campaign = new ClioCampaign;
        $campaign->year = 2025;
        $campaign->setRelation('schools', collect());

        $dashboard = [
            'highlights' => [],
            'report' => [
                'available' => true,
                'totals' => [
                    ['label' => 'Curriculares', 'value' => '100'],
                ],
                'matricula_modalidade' => [
                    ['label' => 'Regular', 'count' => 90, 'pct' => 90],
                ],
                'matriculas_por_ano' => [
                    ['label' => '1º Ano', 'count' => 20],
                ],
                'quality_notes' => [],
            ],
        ];

        $theme = $method->invoke($composer, $campaign, $dashboard, collect());

        $this->assertNotNull($theme);
        $this->assertSame('matriculas', $theme['key']);
        $titles = array_map(static fn (array $t): string => (string) ($t['title'] ?? ''), $theme['tables']);
        $this->assertContains(__('Modalidade (Acompanhamento)'), $titles);
        $this->assertContains(__('Matrículas por etapa (Relação)'), $titles);
        $this->assertSame(__('Modalidade (Acompanhamento)'), $titles[0]);
    }

    private function composer(): CampaignFinalPdfComposer
    {
        return new CampaignFinalPdfComposer(
            app(CampaignParseService::class),
            app(CampaignAnalysisPresenter::class),
            app(DiagnosticoGeralComposer::class),
            app(HorizonteMunicipioEnrollmentSeriesService::class),
            app(CampaignActiveCensusMatrixBuilder::class),
        );
    }
}
