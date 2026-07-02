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
    public function retorna_nome_da_capital(): void
    {
        $this->assertSame('Salvador', BrazilStateCapitals::name('BA'));
        $this->assertSame('', BrazilStateCapitals::name('XX'));
    }

    #[Test]
    public function calcula_distancia_ate_capital(): void
    {
        // Aramari (BA) ~ centroide aproximado
        $km = BrazilStateCapitals::distanceKm(-12.12, -38.77, 'BA');

        $this->assertNotNull($km);
        $this->assertGreaterThan(50, $km);
        $this->assertLessThan(250, $km);
    }

    #[Test]
    public function uf_desconhecida_usa_fallback_nacional(): void
    {
        [$lat, $lng] = BrazilStateCapitals::latLng('XX');

        $this->assertTrue(BrazilUfCentroids::isValidBrazilCoord($lat, $lng));
    }
}
