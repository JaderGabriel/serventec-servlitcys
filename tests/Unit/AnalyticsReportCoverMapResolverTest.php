<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Analytics\AnalyticsReportCoverMapResolver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportCoverMapResolverTest extends TestCase
{
    #[Test]
    public function resolve_uses_ibge_centroid_when_configured(): void
    {
        // PNG 1×1 válido (~68 B); o resolver ignora corpos com menos de 80 B.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==').str_repeat("\0", 20);
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($png) {
            $url = $request->url();
            if (str_contains($url, 'servicodados.ibge.gov.br')) {
                return Http::response([
                    'centroide' => [
                        'type' => 'Point',
                        'coordinates' => [-49.25, -16.68],
                    ],
                ], 200);
            }
            if (str_contains($url, 'staticmap.openstreetmap.de')) {
                return Http::response($png, 200, ['Content-Type' => 'image/png']);
            }

            return Http::response('', 404);
        });

        $city = new City([
            'name' => 'Goiânia',
            'uf' => 'GO',
            'ibge_municipio' => '5208707',
        ]);
        $city->id = 0;

        $result = app(AnalyticsReportCoverMapResolver::class)->resolve($city);

        $this->assertSame('ibge_api', $result['coords']['source'] ?? null);
        $this->assertNotNull($result['municipal']['data_uri'] ?? null);
        $this->assertStringStartsWith('data:image/png;base64,', (string) ($result['municipal']['data_uri'] ?? ''));
    }

    #[Test]
    public function resolve_coordinates_from_nominatim_when_ibge_missing(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-23.5505', 'lon' => '-46.6333'],
            ], 200),
        ]);

        $city = new City([
            'name' => 'São Paulo',
            'uf' => 'SP',
            'ibge_municipio' => null,
        ]);
        $city->id = 0;

        $coords = app(AnalyticsReportCoverMapResolver::class)->resolveCoordinates($city);

        $this->assertSame('nominatim', $coords['source'] ?? null);
        $this->assertEqualsWithDelta(-23.5505, $coords['lat'], 0.001);
    }
}
