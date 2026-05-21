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
        $this->assertStringContainsString('42', $strip['status_label']);
        $this->assertContains('discrepancies', AnalyticsTabImpactBuilder::TABS_WITH_STRIP);
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
        $this->assertTrue($strip['show_status']);
        $this->assertSame('system', $strip['status_mode']);
        $this->assertSame(72, $strip['tab_score']);
        $this->assertNotEmpty($strip['status_issues']);
        $this->assertContains('municipality_health', AnalyticsTabImpactBuilder::TABS_WITH_STRIP);
    }

    public function test_overview_strip_hides_status(): void
    {
        $ctx = ['compliance_score' => 80, 'compliance_status' => 'success', 'total_matriculas' => 500, 'pendencias_cadastro' => 0];

        $strip = AnalyticsTabImpactBuilder::build('overview', true, $ctx, [
            'overviewData' => ['kpis' => ['matriculas' => 500]],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertFalse($strip['show_status']);
        $this->assertContains('overview', AnalyticsTabImpactBuilder::TABS_WITHOUT_STATUS);
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
