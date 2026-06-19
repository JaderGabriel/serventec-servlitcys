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
        $this->assertSame('all', $small['initial_tier']);

        $heavy = HorizonteMapPresenter::displayPolicy(5200, [
            ['uf' => 'BA', 'high_prospect' => 12, 'without_consultoria' => 300, 'avg_benefit' => 55],
            ['uf' => 'SP', 'high_prospect' => 40, 'without_consultoria' => 500, 'avg_benefit' => 48],
        ]);

        $this->assertTrue($heavy['heavy_dataset']);
        $this->assertSame(5200, $heavy['marker_count_total']);
        $this->assertSame(400, $heavy['max_render_markers']);
        $this->assertSame('prospects', $heavy['initial_tier']);
        $this->assertSame('SP', $heavy['initial_uf']);
        $this->assertTrue($heavy['require_uf_selection']);
        $this->assertSame('overview', $heavy['initial_mode']);
        $this->assertNotNull($heavy['reason']);
    }
}
