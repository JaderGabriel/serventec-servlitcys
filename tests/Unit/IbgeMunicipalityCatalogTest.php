<?php

namespace Tests\Unit;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IbgeMunicipalityCatalogTest extends TestCase
{
    #[Test]
    public function municipalities_for_uf_works_without_centroide_in_api_response(): void
    {
        Http::fake([
            'servicodados.ibge.gov.br/api/v1/localidades/estados/BA/municipios' => Http::response([
                [
                    'id' => 2927408,
                    'nome' => 'Salvador',
                    'microrregiao' => [
                        'mesorregiao' => [
                            'UF' => ['sigla' => 'BA'],
                        ],
                    ],
                ],
                [
                    'id' => 2903200,
                    'nome' => 'Barreiras',
                    'regiao-imediata' => [
                        'regiao-intermediaria' => [
                            'UF' => ['sigla' => 'BA'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $repo = AdminHomeMapCache::repository();
        $repo->forget('ibge_municipality_catalog_uf:BA');

        $catalog = app(IbgeMunicipalityCatalog::class)->municipalitiesForUf('BA');

        $this->assertCount(2, $catalog);
        $this->assertArrayHasKey('2927408', $catalog);
        $this->assertSame('Salvador', $catalog['2927408']['name']);
        $this->assertSame('BA', $catalog['2927408']['uf']);
        $this->assertIsFloat($catalog['2927408']['lat']);
        $this->assertIsFloat($catalog['2927408']['lng']);
    }

    #[Test]
    public function empty_catalog_is_not_cached(): void
    {
        Http::fake([
            'servicodados.ibge.gov.br/api/v1/localidades/estados/XX/municipios' => Http::response([], 404),
        ]);

        $repo = AdminHomeMapCache::repository();
        $repo->forget('ibge_municipality_catalog_uf:XX');

        $catalog = app(IbgeMunicipalityCatalog::class)->municipalitiesForUf('XX');

        $this->assertSame([], $catalog);
        $this->assertNull($repo->get('ibge_municipality_catalog_uf:XX'));
    }
}
