<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteMunicipalSgeRegistryService;
use App\Support\Horizonte\HorizonteMunicipalSgeCache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMunicipalSgeRegistryUpsertTest extends TestCase
{
    #[Test]
    public function upsert_local_entry_persists_json_and_cache(): void
    {
        Storage::fake('local');
        config([
            'horizonte.sge.registry_path' => 'horizonte/sge_registry.json',
        ]);
        HorizonteMunicipalSgeCache::forget();

        $service = app(HorizonteMunicipalSgeRegistryService::class);
        $result = $service->upsertLocalEntry('3550308', [
            'system' => 'GDAE',
            'vendor' => 'Prefeitura SP',
            'notes' => 'Portal municipal',
            'app_url' => 'https://portal.exemplo.sp.gov.br',
        ], 1);

        $this->assertSame('3550308', $result['ibge']);
        $this->assertSame('GDAE', $result['entry']['system']);
        Storage::disk('local')->assertExists('horizonte/sge_registry.json');

        $cached = HorizonteMunicipalSgeCache::get();
        $this->assertSame('GDAE', $cached['3550308']['system'] ?? null);

        $this->assertTrue($service->removeLocalEntry('3550308'));
        $this->assertNull($service->localEntry('3550308'));
    }
}
