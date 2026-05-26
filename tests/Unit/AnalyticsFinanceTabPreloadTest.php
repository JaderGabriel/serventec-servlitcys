<?php

namespace Tests\Unit;

use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsFinanceTabPreloadTest extends TestCase
{
    #[Test]
    public function context_from_discrepancies_reutiliza_summary(): void
    {
        $ctx = AnalyticsFinanceTabPreload::contextFromDiscrepancies([
            'total_matriculas' => 500,
            'funding_reference' => ['vaaf_municipal' => 1200.0],
            'summary' => [
                'com_problema' => 30,
                'corrigiveis' => 20,
                'perda_estimada_anual' => 10_000.0,
                'ganho_potencial_anual' => 5_000.0,
                'escolas_afetadas' => 2,
            ],
        ]);

        $this->assertNotNull($ctx);
        $this->assertSame(30, $ctx['com_problema']);
        $this->assertSame(500, $ctx['total_matriculas']);
        $this->assertSame(-5_000.0, $ctx['saldo_liquido']);
    }

    #[Test]
    public function finance_tabs_reuse_inclui_discrepancies_e_fundeb(): void
    {
        config(['analytics.finance_tabs_reuse_funding_context' => true]);

        $this->assertTrue(AnalyticsFinanceTabPreload::shouldReuseFundingContext('discrepancies'));
        $this->assertTrue(AnalyticsFinanceTabPreload::shouldReuseFundingContext('fundeb'));
        $this->assertFalse(AnalyticsFinanceTabPreload::shouldReuseFundingContext('enrollment'));
    }

    #[Test]
    public function strip_context_somente_quando_flag_activa(): void
    {
        config([
            'analytics.finance_tabs_reuse_funding_context' => true,
            'analytics.finance_tabs_strip_funding_context' => false,
        ]);

        $this->assertFalse(AnalyticsFinanceTabPreload::shouldReuseFundingContext('other_funding'));
    }
}
