<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Funding\TesouroFundebPublicacaoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TesouroFundebPublicacaoServiceTest extends TestCase
{
    #[Test]
    public function resolve_url_da_pagina_e_importa_total_uf(): void
    {
        Cache::flush();

        config([
            'ieducar.funding.transfers.extrato_sources.tesouro_publicacao.arquivo_ids' => ['2026' => '99999'],
            'ieducar.funding.transfers.extrato_sources.tesouro_publicacao.page_slugs' => ['2026' => '114'],
        ]);

        $xlsPath = base_path('tests/Fixtures/fundeb-publicacao-ba-snippet.xls');
        if (! is_readable($xlsPath)) {
            $this->markTestSkipped('Fixture fundeb-publicacao-ba-snippet.xls ausente.');
        }

        Http::fake([
            'thot-arquivos.tesouro.gov.br/*' => Http::response((string) file_get_contents($xlsPath), 200),
        ]);

        $city = new City([
            'name' => 'Salvador',
            'uf' => 'BA',
            'ibge_municipio' => '2927408',
        ]);

        $service = new TesouroFundebPublicacaoService;
        $url = $service->resolveDownloadUrl(2026, 10);
        $this->assertSame('https://thot-arquivos.tesouro.gov.br/publicacao/99999', $url);

        $result = $service->fetchForCityYear($city, 2026, 15);
        $this->assertSame('ok', $result['attempt']['status']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('tesouro_publicacao', $result['rows'][0]['fonte']);
        $this->assertSame('fundeb', $result['rows'][0]['programa_id']);
        $this->assertSame(300.0, $result['rows'][0]['valor']);
    }
}
