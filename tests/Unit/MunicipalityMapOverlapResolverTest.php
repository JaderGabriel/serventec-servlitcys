<?php

namespace Tests\Unit;

use App\Support\Brazil\MunicipalityMapOverlapResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalityMapOverlapResolverTest extends TestCase
{
    #[Test]
    public function separa_marcadores_com_mesmas_coordenadas(): void
    {
        $resolver = new MunicipalityMapOverlapResolver();
        $markers = [
            ['lat' => -12.5, 'lng' => -38.5, 'coord_source' => 'ibge'],
            ['lat' => -12.5, 'lng' => -38.5, 'coord_source' => 'ibge'],
            ['lat' => -22.0, 'lng' => -43.0, 'coord_source' => 'school_geos'],
        ];

        $out = $resolver->separate($markers);

        $this->assertNotEquals(
            [$out[0]['lat'], $out[0]['lng']],
            [$out[1]['lat'], $out[1]['lng']],
        );
        $this->assertStringContainsString('offset', (string) $out[1]['coord_source']);
    }
}
