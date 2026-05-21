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
            'healthData' => ['compliance_score' => 72, 'compliance_status' => 'warning', 'compliance_label' => 'Atenção'],
        ]);

        $this->assertTrue($strip['ready']);
        $this->assertSame(72, $strip['tab_score']);
        $this->assertContains('municipality_health', AnalyticsTabImpactBuilder::TABS_WITH_STRIP);
    }
}
