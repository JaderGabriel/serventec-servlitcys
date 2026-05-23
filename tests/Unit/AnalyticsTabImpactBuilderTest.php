<?php

namespace Tests\Unit;

use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabImpactBuilder;
use Tests\TestCase;

class AnalyticsTabImpactBuilderTest extends TestCase
{
    public function test_build_returns_not_ready_without_year_filter(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', false, null, []);

        $this->assertFalse($strip['ready']);
        $this->assertSame('neutral', $strip['status']);
    }

    public function test_municipality_context_from_funding_snapshot(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 1000.0,
                'ganho_potencial_anual' => 500.0,
                'com_problema' => 3,
                'corrigiveis' => 2,
                'escolas_afetadas' => 1,
            ],
        ], ['kpis' => ['matriculas' => 1200]]);

        $this->assertNotNull($ctx);
        $this->assertSame(-500.0, $ctx['saldo_liquido']);
        $this->assertSame(1200, $ctx['total_matriculas']);

        $strip = AnalyticsTabImpactBuilder::build('fundeb', true, $ctx, [
            'fundebData' => [
                'resource_projection' => ['available' => true, 'totais' => ['fundeb_base_anual' => 1_000_000]],
                'modules' => [],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertArrayHasKey('saldo', $strip);
        $this->assertSame(1000.0, $strip['saldo']['perda']);
    }

    public function test_discrepancies_strip_reflects_occurrences(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 2500.0,
                'ganho_potencial_anual' => 2500.0,
                'com_problema' => 42,
                'escolas_afetadas' => 5,
            ],
        ], []);

        $strip = AnalyticsTabImpactBuilder::build('discrepancies', true, $ctx, [
            'discrepanciesData' => ['summary' => ['com_problema' => 42, 'escolas_afetadas' => 5, 'perda_estimada_anual' => 2500.0]],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertFalse($strip['show_saldo']);
        $this->assertNull($strip['saldo']);
        $this->assertStringContainsString('42', $strip['status_label']);
        $this->assertContains('discrepancies', AnalyticsTabImpactBuilder::TABS_WITH_STRIP);
        $this->assertContains('discrepancies', AnalyticsTabImpactBuilder::TABS_WITHOUT_SALDO);
    }

    public function test_municipality_health_strip_uses_compliance_score(): void
    {
        $ctx = ['compliance_score' => 72, 'compliance_status' => 'warning', 'compliance_label' => 'Atenção'];

        $strip = AnalyticsTabImpactBuilder::build('municipality_health', true, $ctx, [
            'healthData' => [
                'compliance_score' => 72,
                'compliance_status' => 'warning',
                'compliance_label' => 'Atenção',
                'summary' => ['pendencias_cadastro' => 2, 'modulos_fundeb_alerta' => 1],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertFalse($strip['show_saldo']);
        $this->assertNull($strip['saldo']);
        $this->assertTrue($strip['show_status']);
        $this->assertSame('system', $strip['status_mode']);
        $this->assertSame(72, $strip['tab_score']);
        $this->assertNotEmpty($strip['status_issues']);
        $this->assertContains('municipality_health', AnalyticsTabImpactBuilder::TABS_WITH_STRIP);
        $this->assertContains('municipality_health', AnalyticsTabImpactBuilder::TABS_WITHOUT_SALDO);
    }

    public function test_overview_strip_hides_status_and_saldo(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 3000.0,
                'ganho_potencial_anual' => 3000.0,
                'com_problema' => 10,
            ],
        ], ['kpis' => ['matriculas' => 1200]]);

        $strip = AnalyticsTabImpactBuilder::build('overview', true, $ctx, [
            'overviewData' => ['kpis' => ['matriculas' => 1200]],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertFalse($strip['show_status']);
        $this->assertFalse($strip['show_saldo']);
        $this->assertNull($strip['saldo']);
        $this->assertContains('overview', AnalyticsTabImpactBuilder::TABS_WITHOUT_STATUS);
        $this->assertContains('overview', AnalyticsTabImpactBuilder::TABS_WITHOUT_SALDO);
    }

    public function test_enrollment_error_surfaces_in_status(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, ['total_matriculas' => 100], [
            'enrollmentData' => ['error' => 'timeout', 'distorcao' => ['pct' => 5]],
        ]);

        $this->assertSame('danger', $strip['status']);
        $this->assertStringContainsString('Erro', $strip['status_label']);
        $this->assertNotEmpty($strip['status_issues']);
    }

    public function test_enrollment_strip_uses_distorcao_for_saldo(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, ['total_matriculas' => 300], [
            'enrollmentData' => [
                'kpis' => ['matriculas' => 300, 'turmas_distintas' => 40],
                'distorcao' => ['com' => 50, 'sem' => 200, 'total' => 250, 'pct' => 20.0],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('distorção', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
        $this->assertStringContainsString('matrícula', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
    }

    public function test_enrollment_status_resume_aba_nao_so_distorcao(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, ['pendencias_cadastro' => 5, 'total_matriculas' => 800], [
            'enrollmentData' => [
                'kpis' => ['matriculas' => 800, 'turmas_distintas' => 120, 'ocupacao_pct' => 72.5],
                'distorcao' => ['com' => 10, 'sem' => 90, 'total' => 100, 'pct' => 10.0],
            ],
        ]);

        $this->assertStringContainsString('800', $strip['status_label']);
        $this->assertStringContainsString('120', $strip['status_label']);
        $this->assertStringNotContainsString(__('Distorção idade-série:'), $strip['status_label']);
        $this->assertSame('800', $strip['saldo']['tab_share_value'] ?? null);
    }

    public function test_enrollment_saldo_usa_checks_matricula_quando_discrepancias_na_aba(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, ['total_matriculas' => 500], [
            'enrollmentData' => [
                'kpis' => ['matriculas' => 500, 'turmas_distintas' => 60],
                'distorcao' => null,
            ],
            'discrepanciesData' => [
                'checks' => [
                    ['id' => 'matricula_duplicada', 'titulo' => 'Duplicadas', 'total' => 8],
                    ['id' => 'escola_sem_geo', 'total' => 3],
                ],
            ],
        ]);

        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('Duplicadas', (string) ($strip['saldo']['footnote'] ?? ''));
        $this->assertStringNotContainsString('escola_sem_geo', (string) ($strip['saldo']['footnote'] ?? ''));
    }

    public function test_enrollment_saldo_usa_distorcao_do_contexto_quando_aba_ainda_nao_carregou(): void
    {
        $ctx = [
            'total_matriculas' => 1200,
            'distorcao_com' => 40,
            'distorcao_pct' => 8.0,
            'distorcao_elegivel_total' => 500,
            'distorcao_cobertura_pct' => 41.7,
        ];

        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, $ctx, [
            'enrollmentData' => ['distorcao' => null],
        ]);

        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('1.200', (string) ($strip['saldo']['footnote'] ?? ''));
        $this->assertStringContainsString('40', (string) ($strip['saldo']['footnote'] ?? ''));
    }

    public function test_enrollment_strip_mostra_saldo_e_fundeb_sem_discrepancias(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('enrollment', true, [
            'total_matriculas' => 600,
            'funding_reference' => [
                'vaa_anual' => 5123.45,
                'vaa_label' => 'R$ 5.123,45',
                'vaa_fonte_label' => 'VAAF importado (teste)',
            ],
        ], [
            'enrollmentData' => [
                'kpis' => ['matriculas' => 600, 'turmas_distintas' => 80],
                'distorcao' => ['com' => 0, 'sem' => 100, 'total' => 100, 'pct' => 0.0],
            ],
        ]);

        $this->assertTrue($strip['show_saldo'] ?? true);
        $this->assertNotNull($strip['saldo']);
        $this->assertFalse($strip['saldo']['info_only'] ?? true);
        $this->assertSame(0.0, $strip['saldo']['perda']);
        $this->assertNotEmpty($strip['saldo']['fundeb_calculo'] ?? null);
        $this->assertNotEmpty($strip['saldo']['fundeb_lines'] ?? []);
        $line = (string) ($strip['saldo']['fundeb_lines'][0] ?? '');
        $this->assertStringContainsString('5.123', $line);
        $this->assertStringContainsString('/aluno/ano', $line);
        $this->assertStringContainsString('volume indicativo FUNDEB', $line);
        $this->assertStringNotContainsString('VAAF ref.', $line);
        $this->assertStringContainsString('discrepância', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
    }

    public function test_inclusion_strip_uses_recurso_prova_saldo(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('inclusion', true, [], [
            'inclusionData' => [
                'recurso_prova' => ['sem_nee' => 12, 'nee_sem_recurso' => 3],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('VAAR', (string) ($strip['saldo']['footnote'] ?? ''));
    }

    public function test_inclusion_strip_mostra_fundeb_nee_quando_sem_discrepancias(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('inclusion', true, [], [
            'inclusionData' => [
                'recurso_prova' => ['sem_nee' => 0, 'nee_sem_recurso' => 0],
                'fundeb_nee' => [
                    'available' => true,
                    'matriculas_nee' => 42,
                    'vaaf_fmt' => 'R$ 5.000,00',
                    'vaaf_fonte' => 'Config municipal',
                    'base_anual_fmt' => 'R$ 210.000,00',
                    'peso_educacao_especial' => 1.2,
                    'adicional_anual' => 42000.0,
                    'adicional_anual_fmt' => 'R$ 42.000,00',
                    'total_indicativo_anual_fmt' => 'R$ 252.000,00',
                ],
            ],
        ]);

        $this->assertNotNull($strip['saldo']);
        $this->assertTrue($strip['saldo']['info_only'] ?? false);
        $this->assertNotEmpty($strip['saldo']['fundeb_lines'] ?? []);
        $this->assertStringContainsString('VAAF', (string) ($strip['saldo']['fundeb_lines'][0] ?? ''));
    }

    public function test_performance_strip_usa_fatia_ctx_sem_fluxo(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => ['perda_estimada_anual' => 5000.0, 'ganho_potencial_anual' => 5000.0],
        ], []);

        $strip = AnalyticsTabImpactBuilder::build('performance', true, $ctx, [
            'performanceData' => ['kpis' => []],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertNotNull($strip['saldo']);
        $this->assertFalse($strip['saldo']['info_only'] ?? true);
        $this->assertSame(600.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('VAAR-indicadores', (string) ($strip['saldo']['footnote'] ?? ''));
    }

    public function test_performance_strip_estima_abandono_remanejamento(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('performance', true, [], [
            'performanceData' => [
                'kpis' => [
                    ['id' => 'abandono', 'quantidade' => 8],
                    ['id' => 'remanejamento', 'quantidade' => 2],
                ],
            ],
        ]);

        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('abandono', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
    }

    public function test_attendance_strip_estima_faltas_em_lotes(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('attendance', true, [], [
            'attendanceData' => [
                'rows' => [
                    ['mes' => '2025-03', 'faltas' => 40],
                    ['mes' => '2025-04', 'faltas' => 35],
                ],
                'charts' => [['type' => 'bar']],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertNotNull($strip['saldo']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('falta', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
        $this->assertSame('success', $strip['status']);
    }

    public function test_attendance_unavailable_scores_danger_and_estimates_saldo(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('attendance', true, ['total_matriculas' => 400], [
            'attendanceData' => [
                'unavailable' => true,
                'message' => 'A tabela de faltas não existe ou não está acessível.',
                'rows' => [],
                'charts' => [],
            ],
        ]);

        $this->assertSame('danger', $strip['status']);
        $this->assertSame(15, $strip['tab_score']);
        $this->assertNotNull($strip['saldo']);
        $this->assertFalse($strip['saldo']['info_only'] ?? true);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('falta_aluno', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
        $this->assertNotEmpty($strip['status_issues']);
    }

    public function test_attendance_empty_sem_registos_alerta_nao_neutro(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('attendance', true, ['total_matriculas' => 200], [
            'attendanceData' => [
                'message' => 'Sem registros de falta para os filtros selecionados.',
                'rows' => [],
                'charts' => [],
            ],
        ]);

        $this->assertSame('warning', $strip['status']);
        $this->assertSame(28, $strip['tab_score']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertStringContainsString('lançamento', strtolower((string) ($strip['saldo']['footnote'] ?? '')));
    }

    public function test_school_units_zero_geo_scores_zero_not_fifty(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('school_units', true, [], [
            'schoolUnitsData' => [
                'tab' => [
                    'markers' => [],
                    'waiting' => ['total' => 0],
                    'geo_distribution' => [
                        'escolas_no_escopo' => 12,
                        'total_com_coordenadas' => 0,
                        'marcadores_exibidos' => 0,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertSame(0, $strip['tab_score']);
        $this->assertSame('danger', $strip['status']);
        $this->assertStringContainsString('0 de', $strip['status_label']);
    }

    public function test_school_units_partial_geo_reflects_percentage(): void
    {
        $strip = AnalyticsTabImpactBuilder::build('school_units', true, [], [
            'schoolUnitsData' => [
                'tab' => [
                    'markers' => array_fill(0, 4, ['id' => 1]),
                    'waiting' => ['total' => 0],
                    'geo_distribution' => [
                        'escolas_no_escopo' => 10,
                        'total_com_coordenadas' => 4,
                        'marcadores_exibidos' => 4,
                    ],
                ],
            ],
        ]);

        $this->assertSame(40, $strip['tab_score']);
        $this->assertSame('warning', $strip['status']);
    }

    public function test_other_funding_and_work_done_strip_hide_saldo(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 1000.0,
                'ganho_potencial_anual' => 500.0,
            ],
        ], []);

        $other = AnalyticsTabImpactBuilder::build('other_funding', true, $ctx, [
            'otherFundingData' => ['programs' => []],
        ]);
        $censo = AnalyticsTabImpactBuilder::build('work_done', true, $ctx, [
            'workDoneData' => ['kpis' => []],
        ]);

        $this->assertFalse($other['show_saldo']);
        $this->assertNull($other['saldo']);
        $this->assertFalse($censo['show_saldo']);
        $this->assertNull($censo['saldo']);
        $this->assertContains('other_funding', AnalyticsTabImpactBuilder::TABS_WITHOUT_SALDO);
        $this->assertContains('work_done', AnalyticsTabImpactBuilder::TABS_WITHOUT_SALDO);
    }

    public function test_network_strip_uses_idle_vacancies_for_saldo_when_discrepancies_zero(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
            ],
        ], []);

        $strip = AnalyticsTabImpactBuilder::build('network', true, $ctx, [
            'networkData' => [
                'kpis' => [
                    'vagas_ociosas' => 100,
                    'taxa_ociosidade_pct' => 12.5,
                    'capacidade_total' => 800,
                    'matriculas' => 700,
                ],
            ],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertGreaterThan(0.0, $strip['saldo']['perda']);
        $this->assertGreaterThan(0.0, $strip['saldo']['ganho']);
        $this->assertStringContainsString('vagas ociosas', (string) ($strip['saldo']['footnote'] ?? ''));
        $this->assertSame('100', $strip['saldo']['tab_share_value']);
    }
}
