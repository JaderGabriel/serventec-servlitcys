<?php

namespace Tests\Unit;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IbgeMunicipalityMalhaCentroidTest extends TestCase
{
    #[Test]
    public function sync_centroids_for_uf_from_malha_geojson(): void
    {
        Http::fake([
            'servicodados.ibge.gov.br/api/v2/malhas/16*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => [
                            'codarea' => '1600303',
                            'centroide' => [-50.6918, 0.5628],
                        ],
                        'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                    ],
                ],
            ], 200),
        ]);

        $repo = AdminHomeMapCache::repository();
        $repo->forget('ibge_municipality_centroid:1600303');

        $result = app(IbgeMunicipalityCatalog::class)->syncCentroidsForUfFromMalha('AP');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['fetched']);
        $this->assertArrayHasKey('1600303', $result['centroids']);
        $this->assertTrue($repo->has('ibge_municipality_centroid:1600303'));
    }

    #[Test]
    public function fetch_raw_centroid_uses_malha_metadados_when_localidades_has_no_centroide(): void
    {
        Http::fake([
            'servicodados.ibge.gov.br/api/v4/malhas/municipios/1600303/metadados' => Http::response([
                [
                    'id' => '1600303',
                    'nome' => 'Macapá',
                    'centroide' => [
                        'longitude' => -50.6918,
                        'latitude' => 0.5628,
                    ],
                ],
            ], 200),
            'servicodados.ibge.gov.br/api/v1/localidades/municipios/1600303' => Http::response([
                'id' => 1600303,
                'nome' => 'Macapá',
            ], 200),
        ]);

        $repo = AdminHomeMapCache::repository();
        $repo->forget('ibge_municipality_centroid:1600303');

        $result = app(IbgeMunicipalityCatalog::class)->syncCentroidForIbge('1600303');

        $this->assertSame('fetched', $result['status']);
        $this->assertEqualsWithDelta(0.5628, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-50.6918, $result['lng'], 0.0001);
    }
}
