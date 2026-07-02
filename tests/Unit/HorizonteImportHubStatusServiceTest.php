<?php

namespace Tests\Unit;

use App\Services\Admin\HorizonteImportHubStatusService;
use Tests\TestCase;

final class HorizonteImportHubStatusServiceTest extends TestCase
{
    public function test_build_returns_horizonte_hub_structure(): void
    {
        $status = app(HorizonteImportHubStatusService::class)->build();

        $this->assertArrayHasKey('coverage', $status);
        $this->assertArrayHasKey('phases', $status);
        $this->assertArrayHasKey('map_url', $status);
        $this->assertCount(10, $status['phases']);
        $this->assertSame(route('dashboard.horizonte'), $status['map_url']);

        $saeb = collect($status['phases'])->firstWhere('key', 'saeb_planilhas');
        $ibge = collect($status['phases'])->firstWhere('key', 'ibge_catalog');
        $ibgeGeo = collect($status['phases'])->firstWhere('key', 'ibge_municipal_geo');
        $this->assertSame('php artisan horizonte:fortnightly-feed --phase=saeb_planilhas', $saeb['cli'] ?? null);
        $this->assertSame('php artisan horizonte:fortnightly-feed --phase=ibge_catalog', $ibge['cli'] ?? null);
        $this->assertSame('php artisan horizonte:import-municipal-geo --all', $ibgeGeo['cli'] ?? null);
    }
}
