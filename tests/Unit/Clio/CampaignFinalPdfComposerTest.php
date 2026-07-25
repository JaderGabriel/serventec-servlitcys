<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Export\CampaignFinalPdfComposer;
use App\Services\Clio\Export\DiagnosticoGeralComposer;
use App\Services\Clio\Parse\CampaignParseService;
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

        $campaign = new \App\Models\Clio\ClioCampaign;
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

    private function composer(): CampaignFinalPdfComposer
    {
        return new CampaignFinalPdfComposer(
            app(CampaignParseService::class),
            app(CampaignAnalysisPresenter::class),
            app(DiagnosticoGeralComposer::class),
            app(\App\Services\Horizonte\HorizonteMunicipioEnrollmentSeriesService::class),
        );
    }
}
