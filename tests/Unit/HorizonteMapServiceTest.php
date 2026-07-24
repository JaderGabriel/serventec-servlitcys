<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteMapService;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testes leves do núcleo do mapa Horizonte (cache hit + recorte por UF).
 */
final class HorizonteMapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'horizonte.enabled' => true,
            'horizonte.reference_year' => 2024,
            'horizonte.cache_seconds' => 900,
            'performance.home_map_cache_store' => 'array',
        ]);

        HorizonteMapCacheBuster::forgetFingerprint();
        Cache::flush();
        HorizonteMapCacheBuster::rememberFingerprint(static fn (): string => 'unit-fp');
    }

    #[Test]
    public function build_devolve_payload_em_cache_sem_remontar(): void
    {
        $payload = [
            'reference_year' => 2024,
            'generated_at' => '2026-07-24T12:00:00+00:00',
            'markers' => [
                ['ibge' => '2900100', 'name' => 'Abaíra', 'uf' => 'BA'],
            ],
            'summary' => ['total' => 1],
            'uf_rankings' => [],
            'top_prospects' => [],
            'colors' => ['prospect_high' => '#be123c'],
            'legend' => [],
        ];

        AdminHomeMapCache::repository()->put(
            'horizonte:map:v2:2024:unit-fp',
            $payload,
            900,
        );

        $result = app(HorizonteMapService::class)->build();

        $this->assertSame($payload, $result);
        $this->assertCount(1, $result['markers']);
    }

    #[Test]
    public function build_for_request_regional_usa_assemble_em_cache_e_filtra_uf(): void
    {
        $assembled = [
            'reference_year' => 2024,
            'generated_at' => '2026-07-24T12:00:00+00:00',
            'markers' => [
                [
                    'ibge' => '2900100',
                    'name' => 'Abaíra',
                    'uf' => 'BA',
                    'tier' => 'prospect_high',
                    'lat' => -13.25,
                    'lng' => -41.66,
                ],
                [
                    'ibge' => '3550308',
                    'name' => 'São Paulo',
                    'uf' => 'SP',
                    'tier' => 'consultoria_active',
                    'lat' => -23.55,
                    'lng' => -46.63,
                ],
            ],
            'summary' => ['total' => 2],
            'uf_rankings' => [],
            'top_prospects' => [],
            'colors' => [],
            'legend' => [],
            'current_year' => 2026,
        ];

        AdminHomeMapCache::repository()->put(
            'horizonte:map:regional:v3:2024:BA:unit-fp',
            $assembled,
            900,
        );

        $result = app(HorizonteMapService::class)->buildForRequest('regional', 'BA');

        $this->assertSame('regional', $result['mode'] ?? null);
        $this->assertSame('BA', $result['scope_uf'] ?? null);
        $this->assertCount(1, $result['markers'] ?? []);
        $this->assertSame('2900100', $result['markers'][0]['ibge'] ?? null);
        $this->assertSame('BA', $result['markers'][0]['uf'] ?? null);
    }

    #[Test]
    public function build_com_horizonte_desactivado_devolve_vazio(): void
    {
        config(['horizonte.enabled' => false]);

        $result = app(HorizonteMapService::class)->build();

        $this->assertSame([], $result['markers']);
        $this->assertSame(0, $result['summary']['total'] ?? null);
    }
}
