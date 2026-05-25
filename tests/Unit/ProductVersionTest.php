<?php

namespace Tests\Unit;

use App\Support\Product\ProductVersion;
use Tests\TestCase;

final class ProductVersionTest extends TestCase
{
    public function test_badge_inclui_versao_tag_e_data(): void
    {
        config([
            'documentation.product' => [
                'version' => '3.0.0',
                'release_tag' => '20260525-Apollo',
                'revision_date' => '2026-05-25',
                'in_production' => true,
                'production_label' => 'Em produção',
            ],
        ]);

        $badge = ProductVersion::badge();

        $this->assertSame('3.0.0', $badge['version']);
        $this->assertStringContainsString('3.0.0', $badge['display_label']);
        $this->assertStringContainsString('Apollo', $badge['display_label']);
        $this->assertSame('production', $badge['tone']);
        $this->assertNotSame('', $badge['revision_label']);
    }
}
