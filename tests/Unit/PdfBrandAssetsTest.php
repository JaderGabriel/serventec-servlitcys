<?php

namespace Tests\Unit;

use App\Support\Analytics\PdfBrandAssets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PdfBrandAssetsTest extends TestCase
{
    #[Test]
    public function enrich_sets_default_serventec_url_and_icon(): void
    {
        $brand = PdfBrandAssets::enrich([
            'serventec_url' => '',
        ]);

        $this->assertSame('https://analise.serventecassessoria.com.br', $brand['serventec_url']);
        $this->assertSame('analise.serventecassessoria.com.br', $brand['serventec_display_url']);
        $this->assertNotNull($brand['icon_data_uri'] ?? null);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) ($brand['icon_data_uri'] ?? ''));
    }

    #[Test]
    public function display_host_strips_scheme(): void
    {
        $this->assertSame(
            'analise.serventecassessoria.com.br',
            PdfBrandAssets::displayHost('https://analise.serventecassessoria.com.br/path'),
        );
    }
}
