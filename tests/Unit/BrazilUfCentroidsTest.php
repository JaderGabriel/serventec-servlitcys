<?php

namespace Tests\Unit;

use App\Support\Brazil\BrazilUfCentroids;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrazilUfCentroidsTest extends TestCase
{
    #[Test]
    public function lat_lng_for_index_spreads_multiple_municipalities(): void
    {
        $a = BrazilUfCentroids::latLngForIndex('SP', 0, 4, 1);
        $b = BrazilUfCentroids::latLngForIndex('SP', 1, 4, 2);
        $c = BrazilUfCentroids::latLngForIndex('SP', 2, 4, 3);

        $this->assertNotEquals($a, $b);
        $this->assertNotEquals($b, $c);
        $this->assertEqualsWithDelta(-22.19, $a[0], 1.5);
        $this->assertEqualsWithDelta(-48.79, $a[1], 1.5);
    }

    #[Test]
    public function lat_lng_for_index_stays_within_brazil_for_large_uf(): void
    {
        $coord = BrazilUfCentroids::latLngForIndex('BA', 400, 417, 2910800);

        $this->assertGreaterThanOrEqual(-34.0, $coord[0]);
        $this->assertLessThanOrEqual(5.5, $coord[0]);
        $this->assertGreaterThanOrEqual(-74.5, $coord[1]);
        $this->assertLessThanOrEqual(-32.0, $coord[1]);
    }
}
