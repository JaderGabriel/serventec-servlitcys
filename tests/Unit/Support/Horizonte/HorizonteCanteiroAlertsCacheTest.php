<?php

namespace Tests\Unit\Support\Horizonte;

use App\Support\Horizonte\HorizonteCanteiroAlertsCache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteCanteiroAlertsCacheTest extends TestCase
{
    #[Test]
    public function load_returns_empty_when_snapshot_missing(): void
    {
        Storage::fake('local');
        config(['horizonte.obras.alerts.snapshot_path' => 'horizonte/canteiro_alerts_snapshot.json']);

        $bundle = HorizonteCanteiroAlertsCache::load();

        $this->assertSame([], $bundle['by_ibge']);
        $this->assertNull($bundle['generated_at']);
        $this->assertNotSame('', $bundle['simec_painel_url']);
    }

    #[Test]
    public function load_indexes_snapshot_by_ibge(): void
    {
        Storage::fake('local');
        config([
            'horizonte.obras.alerts.snapshot_path' => 'horizonte/canteiro_alerts_snapshot.json',
            'horizonte.obras.alerts.simec_painel_url' => 'https://simec.mec.gov.br/painelObras/',
        ]);

        Storage::disk('local')->put('horizonte/canteiro_alerts_snapshot.json', json_encode([
            'generated_at' => '2026-07-21T10:00:00+00:00',
            'simec_painel_url' => 'https://simec.mec.gov.br/painelObras/',
            'snapshot' => [
                '2905701' => [
                    'ibge' => '2905701',
                    'city_name' => 'Camaçari',
                    'paralisadas' => 2,
                    'em_execucao' => 1,
                    'inacabadas' => 0,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $bundle = HorizonteCanteiroAlertsCache::load();

        $this->assertSame('2026-07-21T10:00:00+00:00', $bundle['generated_at']);
        $this->assertSame(2, $bundle['by_ibge']['2905701']['paralisadas']);
    }
}
