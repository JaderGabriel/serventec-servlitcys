<?php

namespace Tests\Unit;

use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Support\Analytics\AnalyticsReportAtmCatalog;
use App\Support\Analytics\AnalyticsReportBibliography;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportBibliographyTest extends TestCase
{
    #[Test]
    public function formato_public_id_tem_prefixo_srv(): void
    {
        $id = 'SRV-'.strtoupper(\Illuminate\Support\Str::random(12));

        $this->assertStringStartsWith('SRV-', $id);
        $this->assertGreaterThanOrEqual(16, strlen($id));
        $this->assertMatchesRegularExpression('/^SRV-[A-Z0-9]{12}$/', $id);
    }

    #[Test]
    public function catalogo_tem_secao_publicacao_e_sumario(): void
    {
        $sections = AnalyticsReportAtmCatalog::sections();
        $ids = array_column($sections, 'id');

        $this->assertContains('publicacao_digital', $ids);
        $this->assertContains('indicadores_educacionais', $ids);
        $this->assertGreaterThanOrEqual(10, count(AnalyticsReportAtmCatalog::tableOfContents()));
    }

    #[Test]
    public function for_export_monta_citacao_com_municipio(): void
    {
        $city = City::factory()->make(['name' => 'Ipirá', 'uf' => 'BA', 'ibge_municipio' => '2914000']);
        $export = new AnalyticsReportExport([
            'public_id' => 'SRV-TESTPUBLIC01',
            'filters' => ['ano_letivo' => '2025', 'city_id' => 1],
            'completed_at' => now(),
        ]);

        $bib = AnalyticsReportBibliography::forExport($export, $city);

        $this->assertSame('SRV-TESTPUBLIC01', $bib['public_id']);
        $this->assertStringContainsString('Ipirá', $bib['citation']);
        $this->assertStringContainsString('SRV-TESTPUBLIC01', $bib['citation']);
    }
}
