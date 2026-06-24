<?php

namespace Tests\Unit;

use App\Support\Brazil\BrazilStateCapitals;
use App\Support\Brazil\BrazilUfCentroids;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrazilStateCapitalsTest extends TestCase
{
    #[Test]
    public function retorna_coordenadas_das_capitais(): void
    {
        [$lat, $lng] = BrazilStateCapitals::latLng('SP');

        $this->assertSame(-23.55, $lat);
        $this->assertSame(-46.633, $lng);
        $this->assertTrue(BrazilUfCentroids::isValidBrazilCoord($lat, $lng));
    }

    #[Test]
    public function uf_desconhecida_usa_fallback_nacional(): void
    {
        [$lat, $lng] = BrazilStateCapitals::latLng('XX');

        $this->assertTrue(BrazilUfCentroids::isValidBrazilCoord($lat, $lng));
    }
}
