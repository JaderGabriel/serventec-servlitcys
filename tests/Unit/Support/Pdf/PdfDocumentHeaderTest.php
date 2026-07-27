<?php

namespace Tests\Unit\Support\Pdf;

use App\Models\Clio\ClioCampaign;
use App\Support\Pdf\PdfDocumentHeader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PdfDocumentHeaderTest extends TestCase
{
    #[Test]
    public function resolve_monta_cabecalho_a_partir_da_campanha_clio(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Irecê',
            'uf' => 'BA',
            'ibge_municipio' => '291460',
            'reference_date' => '2026-05-31',
        ]);

        $meta = PdfDocumentHeader::resolve([
            'campaign' => $campaign,
            'coverage' => ['reference_date' => '2026-05-31'],
            'generated_at' => '27/07/2026 18:00',
        ]);

        $this->assertSame('Irecê-BA', $meta['city_uf']);
        $this->assertSame('291460', $meta['ibge']);
        $this->assertSame('31/05/2026', $meta['reference']);
        $this->assertSame('27/07/2026 18:00', $meta['emission']);
    }

    #[Test]
    public function resolve_usa_cover_analitico_com_ano_como_referencia(): void
    {
        $meta = PdfDocumentHeader::resolve([
            'cover' => [
                'municipality' => 'Salvador',
                'uf' => 'BA',
                'ibge' => '2927408',
                'year_value' => '2025',
            ],
            'generated_at' => '01/01/2026 10:00',
        ]);

        $this->assertSame('Salvador-BA', $meta['city_uf']);
        $this->assertSame('2927408', $meta['ibge']);
        $this->assertSame(__('Ano :y', ['y' => '2025']), $meta['reference']);
        $this->assertSame('01/01/2026 10:00', $meta['emission']);
    }

    #[Test]
    public function resolve_prioriza_override_pdf_header(): void
    {
        $meta = PdfDocumentHeader::resolve([
            'pdf_header' => [
                'city' => 'X',
                'uf' => 'SP',
                'ibge' => '3550308',
                'reference' => '01/02/2026',
                'emission' => '03/03/2026 12:00',
            ],
            'cover' => ['municipality' => 'Outro', 'uf' => 'BA'],
        ]);

        $this->assertSame('X-SP', $meta['city_uf']);
        $this->assertSame('3550308', $meta['ibge']);
        $this->assertSame('01/02/2026', $meta['reference']);
        $this->assertSame('03/03/2026 12:00', $meta['emission']);
    }
}
