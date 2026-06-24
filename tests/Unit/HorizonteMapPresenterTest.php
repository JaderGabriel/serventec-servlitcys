<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteMapPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMapPresenterTest extends TestCase
{
    #[Test]
    public function refresh_meta_flags_empty_map(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(0, ['with_public_data' => 0]);

        $this->assertTrue($meta['needs_refresh']);
        $this->assertSame(0, $meta['marker_count']);
        $this->assertStringContainsString('horizonte:fortnightly-feed', $meta['refresh_command']);
        $this->assertNotNull($meta['message']);
    }

    #[Test]
    public function refresh_meta_ok_when_public_data_present(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(120, ['with_public_data' => 80]);

        $this->assertFalse($meta['needs_refresh']);
        $this->assertNull($meta['message']);
    }

    #[Test]
    public function refresh_meta_warns_catalog_only(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(5, ['with_public_data' => 0]);

        $this->assertTrue($meta['needs_refresh']);
        $this->assertStringContainsString('FUNDEB', (string) $meta['message']);
    }

    #[Test]
    public function display_policy_restricts_heavy_national_base(): void
    {
        config([
            'horizonte.map_display.heavy_threshold' => 800,
            'horizonte.map_display.max_render_markers' => 400,
        ]);

        $small = HorizonteMapPresenter::displayPolicy(120, []);
        $this->assertFalse($small['heavy_dataset']);
        $this->assertSame('high_pressure', $small['initial_tier']);
        $this->assertArrayHasKey('default_filter', $small);

        $heavy = HorizonteMapPresenter::displayPolicy(5200, [
            ['uf' => 'BA', 'high_prospect' => 12, 'high_pressure' => 18, 'without_consultoria' => 300, 'avg_benefit' => 55],
            ['uf' => 'SP', 'high_prospect' => 40, 'high_pressure' => 55, 'without_consultoria' => 500, 'avg_benefit' => 48],
        ]);

        $this->assertTrue($heavy['heavy_dataset']);
        $this->assertSame(5200, $heavy['marker_count_total']);
        $this->assertSame(400, $heavy['max_render_markers']);
        $this->assertSame('high_pressure', $heavy['initial_tier']);
        $this->assertSame('SP', $heavy['initial_uf']);
        $this->assertTrue($heavy['require_uf_selection']);
        $this->assertSame('overview', $heavy['initial_mode']);
        $this->assertNotNull($heavy['reason']);
    }

    #[Test]
    public function regional_display_policy_scales_render_limit_for_large_uf(): void
    {
        config([
            'horizonte.map_display.regional_medium_threshold' => 150,
            'horizonte.map_display.regional_heavy_threshold' => 300,
            'horizonte.map_display.regional_max_render_medium' => 180,
            'horizonte.map_display.regional_max_render_heavy' => 120,
            'horizonte.map_display.regional_heat_max' => 150,
        ]);

        $small = HorizonteMapPresenter::regionalDisplayPolicy(120);
        $this->assertSame(400, $small['max_render_markers']);
        $this->assertSame('heat', $small['prefer_map_view']);
        $this->assertFalse($small['heavy_regional']);
        $this->assertTrue($small['allow_show_all']);

        $medium = HorizonteMapPresenter::regionalDisplayPolicy(180);
        $this->assertSame(180, $medium['max_render_markers']);
        $this->assertFalse($medium['heavy_regional']);

        $heavy = HorizonteMapPresenter::regionalDisplayPolicy(645);
        $this->assertSame(120, $heavy['max_render_markers']);
        $this->assertSame('markers', $heavy['prefer_map_view']);
        $this->assertTrue($heavy['heavy_regional']);
        $this->assertFalse($heavy['allow_show_all']);
        $this->assertStringContainsString('Desenhar todos', (string) $heavy['reason']);
    }

    #[Test]
    public function default_view_filter_exposes_high_pressure_preset(): void
    {
        config(['horizonte.map_display.financial_pressure_min' => 60]);

        $filter = HorizonteMapPresenter::defaultViewFilter();

        $this->assertSame('high_pressure', $filter['preset']);
        $this->assertSame(60, $filter['pressure_min']);
        $this->assertTrue($filter['hide_consultoria']);
        $this->assertSame('heat', $filter['map_view']);
    }
}
