<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebFndeEstadoVaafService;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebFndeEstadoVaafServiceTest extends TestCase
{
    #[Test]
    public function parseia_linhas_uf_do_pdf_fnde(): void
    {
        $sample = <<<'TXT'
SP           10.131,14           9.477,52          8.170,28           7.516,65           9.804,33            9.150,7           7.516,65            6.863,0         9.804,33          6.536,22          7.189,84         9.935,06          8.170,28         6.536,22     9.150,7           9.150,7           8.823,90     65.768.537.577,91                     0,00      65.768.537.577,91
MG           10.888,89          10.186,38          8.781,36           8.078,85          10.537,63           9.835,13           8.078,85            7.376,3        10.537,63          7.025,09          7.727,60        10.678,14          8.781,36         7.025,09    9.835,13          9.835,13           9.483,87     30.311.985.998,48                     0,00      30.311.985.998,48
TXT;

        $service = new FundebFndeEstadoVaafService();
        $index = $service->parsePdfText($sample, 'https://example.test/vaaf2026.pdf', 2026);

        $this->assertArrayHasKey('SP', $index);
        $this->assertEqualsWithDelta(8823.90, $index['SP']['vaaf'], 0.01);
        $this->assertEqualsWithDelta(65_768_537_577.91, $index['SP']['total_receita_vaaf'], 0.01);

        $this->assertArrayHasKey('MG', $index);
        $this->assertEqualsWithDelta(9483.87, $index['MG']['vaaf'], 0.01);
    }

    #[Test]
    public function discover_csv_2026_aceita_url_com_hifen(): void
    {
        $html = '<a href="https://www.gov.br/fnde/pt-br/.../1-receita-total-do-fundeb-por-ente-federado.csv">CSV</a>';

        $service = new FundebFndeReceitaCsvService();
        $method = new \ReflectionMethod($service, 'extractReceitaCsvUrlFromHtml');
        $url = $method->invoke($service, $html);

        $this->assertNotNull($url);
        $this->assertStringContainsString('receita-total-do-fundeb-por-ente-federado.csv', $url);
    }
}
