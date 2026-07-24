<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteIbgeMalhaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteIbgeMalhaFilterTest extends TestCase
{
    #[Test]
    public function filtra_features_municipais_por_ibge(): void
    {
        $service = new class extends HorizonteIbgeMalhaService
        {
            public function stateMunicipalGeoJson(string $uf, bool $forceRefresh = false): array
            {
                return [
                    'type' => 'FeatureCollection',
                    'features' => [
                        [
                            'type' => 'Feature',
                            'properties' => ['codarea' => '2927408'],
                            'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                        ],
                        [
                            'type' => 'Feature',
                            'properties' => ['codarea' => '2900700'],
                            'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                        ],
                    ],
                ];
            }
        };

        $filtered = $service->stateMunicipalGeoJsonFiltered('BA', ['2927408']);

        $this->assertSame('FeatureCollection', $filtered['type']);
        $this->assertCount(1, $filtered['features']);
        $this->assertSame('2927408', $filtered['features'][0]['properties']['codarea']);
    }
}
