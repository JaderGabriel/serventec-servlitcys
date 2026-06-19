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
        $this->assertGreaterThanOrEqual(5, count($status['phases']));
        $this->assertSame(route('dashboard.horizonte'), $status['map_url']);
    }
}
