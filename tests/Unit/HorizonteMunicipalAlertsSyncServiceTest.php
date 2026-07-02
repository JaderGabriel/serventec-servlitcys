<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteMunicipalAlertsSyncService;
use App\Support\Horizonte\HorizonteMunicipalAlertsCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMunicipalAlertsSyncServiceTest extends TestCase
{
    private HorizonteMunicipalAlertsSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        HorizonteMunicipalAlertsCache::forget();
        Storage::fake('local');
        config([
            'horizonte.municipal_alerts.enabled' => true,
            'horizonte.municipal_alerts.snapshot_path' => 'horizonte/municipal_alerts_snapshot.json',
        ]);
        $this->service = app(HorizonteMunicipalAlertsSyncService::class);
    }

    #[Test]
    public function hydrates_cache_from_snapshot_when_meta_missing(): void
    {
        $syncedAt = now()->toIso8601String();
        Storage::disk('local')->put('horizonte/municipal_alerts_snapshot.json', json_encode([
            'updated_at' => $syncedAt,
            'meta' => [
                'synced_at' => $syncedAt,
                'sources' => ['fnde_vaat_csv'],
                'warnings' => [],
                'matched' => 1,
            ],
            'municipios' => [[
                'ibge_municipio' => '2927408',
                'items' => [[
                    'kind' => 'vaat_inabilitado',
                    'severity' => 'danger',
                    'title' => 'Inabilitado VAAT',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertNull(HorizonteMunicipalAlertsCache::getMeta());

        $meta = $this->service->metaFromCache();
        $index = $this->service->indexedFromCache();

        $this->assertIsArray($meta);
        $this->assertSame($syncedAt, $meta['synced_at']);
        $this->assertArrayHasKey('2927408', $index);
        $this->assertSame('Inabilitado VAAT', $index['2927408']['items'][0]['title']);
    }

    #[Test]
    public function does_not_hydrate_when_cache_already_has_meta(): void
    {
        HorizonteMunicipalAlertsCache::put(
            ['3550308' => ['items' => []]],
            ['synced_at' => '2026-01-01T00:00:00+00:00', 'sources' => ['manual']],
        );

        Storage::disk('local')->put('horizonte/municipal_alerts_snapshot.json', json_encode([
            'meta' => ['synced_at' => '2099-01-01T00:00:00+00:00', 'sources' => ['fnde']],
            'municipios' => [['ibge_municipio' => '2927408', 'items' => []]],
        ], JSON_THROW_ON_ERROR));

        $meta = $this->service->metaFromCache();

        $this->assertSame('2026-01-01T00:00:00+00:00', $meta['synced_at']);
        $this->assertArrayHasKey('3550308', $this->service->indexedFromCache());
        $this->assertArrayNotHasKey('2927408', $this->service->indexedFromCache());
    }

    #[Test]
    public function leaves_cache_empty_when_snapshot_absent(): void
    {
        $this->assertNull($this->service->metaFromCache());
        $this->assertSame([], $this->service->indexedFromCache());
    }
}
