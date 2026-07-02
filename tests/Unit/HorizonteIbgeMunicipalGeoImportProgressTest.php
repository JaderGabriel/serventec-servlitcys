<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteIbgeMunicipalGeoImportProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteIbgeMunicipalGeoImportProgressTest extends TestCase
{
    #[Test]
    public function remaining_ufs_exclui_malhas_em_cache(): void
    {
        Storage::fake('local');
        Cache::flush();

        Storage::disk('local')->put('horizonte/geo/municipal-BA.json', '{"type":"FeatureCollection","features":[]}');
        Storage::disk('local')->put('horizonte/geo/municipal-SP.json', '{"type":"FeatureCollection","features":[]}');

        $remaining = HorizonteIbgeMunicipalGeoImportProgress::remainingUfs();

        $this->assertNotContains('BA', $remaining);
        $this->assertNotContains('SP', $remaining);
        $this->assertContains('RJ', $remaining);
        $this->assertSame(2, HorizonteIbgeMunicipalGeoImportProgress::doneCount());
        $this->assertFalse(HorizonteIbgeMunicipalGeoImportProgress::isComplete());
    }

    #[Test]
    public function record_step_guarda_historico_recente(): void
    {
        Cache::flush();

        HorizonteIbgeMunicipalGeoImportProgress::recordStep([
            'uf' => 'BA',
            'imported' => 417,
            'features' => 417,
            'success' => true,
        ]);

        $recent = HorizonteIbgeMunicipalGeoImportProgress::recentSteps(5);

        $this->assertCount(1, $recent);
        $this->assertSame('BA', $recent[0]['uf']);
        $this->assertSame(417, $recent[0]['imported']);
    }
}
