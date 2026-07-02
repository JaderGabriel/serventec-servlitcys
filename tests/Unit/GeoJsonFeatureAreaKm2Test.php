<?php

namespace Tests\Unit;

use App\Support\Geo\GeoJsonFeatureAreaKm2;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GeoJsonFeatureAreaKm2Test extends TestCase
{
    #[Test]
    public function calcula_area_de_poligono_simples(): void
    {
        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-38.5, -12.0],
                    [-38.4, -12.0],
                    [-38.4, -12.1],
                    [-38.5, -12.1],
                    [-38.5, -12.0],
                ]],
            ],
        ];

        $area = GeoJsonFeatureAreaKm2::fromFeature($feature);

        $this->assertNotNull($area);
        $this->assertGreaterThan(50, $area);
        $this->assertLessThan(200, $area);
    }
}
