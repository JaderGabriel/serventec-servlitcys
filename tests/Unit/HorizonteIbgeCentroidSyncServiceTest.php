<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteIbgeCentroidSyncService;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteIbgeCentroidSyncProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteIbgeCentroidSyncServiceTest extends TestCase
{
    #[Test]
    public function syncs_centroids_for_single_uf_and_rebuilds_catalog(): void
    {
        Cache::flush();
        HorizonteIbgeCentroidSyncProgress::reset();

        Http::fake([
            'servicodados.ibge.gov.br/api/v1/localidades/estados/RR/municipios' => Http::response([
                [
                    'id' => 1400100,
                    'nome' => 'Boa Vista',
                    'microrregiao' => ['mesorregiao' => ['UF' => ['sigla' => 'RR']]],
                ],
            ], 200),
            'servicodados.ibge.gov.br/api/v2/malhas/14*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => [
                            'codarea' => '1400100',
                            'centroide' => [-60.6719, 2.8235],
                        ],
                        'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                    ],
                ],
            ], 200),
        ]);

        $repo = AdminHomeMapCache::repository();
        $repo->forget('ibge_municipality_catalog_uf:v3:geo:RR');
        $repo->forget('ibge_municipality_catalog_uf:v3:spread:RR');

        $result = app(HorizonteIbgeCentroidSyncService::class)->run([
            'uf' => 'RR',
            'delay_ms' => 0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['steps']);
        $this->assertSame(1, $result['steps'][0]['stats']['fetched'] ?? 0);
        $this->assertTrue($repo->has('ibge_municipality_centroid:1400100'));

        $catalog = $repo->get('ibge_municipality_catalog_uf:v3:geo:RR');
        $this->assertIsArray($catalog);
        $this->assertSame('ibge_cache', $catalog['1400100']['coord_source'] ?? null);
    }
}
