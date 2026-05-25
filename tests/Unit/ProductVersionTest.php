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
                'version' => '2.4.0',
                'release_tag' => '20260524-Ceres',
                'revision_date' => '2026-05-24',
                'in_production' => true,
                'production_label' => 'Em produção',
            ],
        ]);

        $badge = ProductVersion::badge();

        $this->assertSame('2.4.0', $badge['version']);
        $this->assertStringContainsString('2.4.0', $badge['display_label']);
        $this->assertStringContainsString('Ceres', $badge['display_label']);
        $this->assertSame('production', $badge['tone']);
        $this->assertNotSame('', $badge['revision_label']);
    }
}
