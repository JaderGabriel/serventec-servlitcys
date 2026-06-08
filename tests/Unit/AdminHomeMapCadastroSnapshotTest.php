<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Dashboard\AdminHomeMapCadastroSnapshot;
use App\Support\Rx\RxCityMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class AdminHomeMapCadastroSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_for_map_returns_empty_when_defer_enabled(): void
    {
        config(['performance.home_defer_map_rx_snapshot' => true]);
        City::factory()->create([
            'is_active' => true,
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);

        $collector = Mockery::mock(RxCityMetricsCollector::class);
        $collector->shouldNotReceive('collect');

        $snapshot = new AdminHomeMapCadastroSnapshot($collector);
        $payload = $snapshot->forMap();

        $this->assertSame([], $payload['by_city_id']);
    }

    public function test_for_map_ajax_still_builds_when_defer_enabled(): void
    {
        config(['performance.home_defer_map_rx_snapshot' => true]);

        $collector = Mockery::mock(RxCityMetricsCollector::class);
        $collector->shouldReceive('collect')->never();

        $snapshot = new AdminHomeMapCadastroSnapshot($collector);
        $payload = $snapshot->forMapAjax();

        $this->assertIsArray($payload['by_city_id']);
        $this->assertArrayHasKey('generated_at', $payload);
    }
}
