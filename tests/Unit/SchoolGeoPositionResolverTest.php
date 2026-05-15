<?php

namespace Tests\Unit;

use App\Support\Ieducar\SchoolGeoPositionResolver;
use PHPUnit\Framework\TestCase;

class SchoolGeoPositionResolverTest extends TestCase
{
    public function test_coords_are_usable_rejects_null_and_origin(): void
    {
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(null, -48.0));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(-23.5, null));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(0.0, 0.0));
    }

    public function test_coords_are_usable_accepts_valid_brazil_coords(): void
    {
        $this->assertTrue(SchoolGeoPositionResolver::coordsAreUsable(-23.5505, -46.6333));
    }

    public function test_coords_are_usable_rejects_out_of_range(): void
    {
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(95.0, 0.0));
        $this->assertFalse(SchoolGeoPositionResolver::coordsAreUsable(0.0, 200.0));
    }
}
