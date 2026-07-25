<?php

namespace Tests\Unit\Services\Horizonte;

use App\Services\Horizonte\HorizonteMunicipalObrasSyncService;
use App\Services\Obrasgov\ObrasgovClient;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use Tests\TestCase;

class HorizonteMunicipalObrasSyncServiceTest extends TestCase
{
    public function test_sync_batch_handles_disabled_config(): void
    {
        config(['horizonte.obras.enabled' => false]);

        $client = new ObrasgovClient();
        $catalog = $this->app->make(IbgeMunicipalityCatalog::class);
        $service = new HorizonteMunicipalObrasSyncService($client, $catalog);

        $result = $service->syncBatch();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped'] ?? false);
        $this->assertStringContainsString('desactivado', $result['message']);
    }

    public function test_sync_batch_respects_uf_option(): void
    {
        config(['horizonte.obras.enabled' => true]);
        config(['horizonte.obras.base_url' => 'http://localhost/blocked']);

        $client = new ObrasgovClient();
        $catalog = $this->app->make(IbgeMunicipalityCatalog::class);
        $service = new HorizonteMunicipalObrasSyncService($client, $catalog);

        $result = $service->syncBatch(['uf' => 'BA', 'dry_run' => false]);

        // Since we're using localhost (blocked by SafeOutboundUrl), sync will succeed but import 0
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported'] ?? 0);
    }

    public function test_sync_batch_dry_run_mode(): void
    {
        config(['horizonte.obras.enabled' => true]);

        $client = new ObrasgovClient();
        $catalog = $this->app->make(IbgeMunicipalityCatalog::class);
        $service = new HorizonteMunicipalObrasSyncService($client, $catalog);

        $result = $service->syncBatch(['uf' => 'BA', 'dry_run' => true]);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']);
        $this->assertStringContainsString('dry-run', $result['message']);
    }
}
