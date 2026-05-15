<?php

namespace Tests\Unit;

use App\Models\SchoolUnitGeo;
use App\Support\Ieducar\SchoolGeoPositionResolver;
use Tests\TestCase;

final class SchoolGeoPositionResolverTest extends TestCase
{
    public function test_coords_are_usable_rejects_null_and_origin(): void
    {
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(null, -38.5));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(-12.9, null));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(0.0, 0.0));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(0.005, 0.005));
    }

    public function test_coords_are_usable_accepts_valid_brazil_coords(): void
    {
        $this->assertTrue(SchoolGeoPositionResolver::coordsAreUsable(-12.9714, -38.5014));
    }

    public function test_has_stored_map_position_uses_ieducar_or_cache(): void
    {
        $geo = new SchoolUnitGeo([
            'lat' => null,
            'lng' => null,
            'official_lat' => null,
            'official_lng' => null,
        ]);

        $this->assertFalse(SchoolGeoPositionResolver::hasStoredMapPosition(null, null, $geo));
        $this->assertTrue(SchoolGeoPositionResolver::hasStoredMapPosition(-12.9, -38.5, $geo));

        $geoOfficial = new SchoolUnitGeo([
            'lat' => null,
            'lng' => null,
            'official_lat' => -12.9,
            'official_lng' => -38.5,
        ]);
        $this->assertTrue(SchoolGeoPositionResolver::hasStoredMapPosition(null, null, $geoOfficial));
    }
}
