<?php

namespace Tests\Unit;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteIbgeCentroidSyncProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteIbgeCentroidSyncProgressTest extends TestCase
{
    #[Test]
    public function initializes_with_ufs_ordered_by_municipality_count(): void
    {
        Cache::flush();
        HorizonteIbgeCentroidSyncProgress::reset();
        HorizonteIbgeCentroidSyncProgress::initialize();

        $order = HorizonteIbgeCentroidSyncProgress::ufOrder();
        $expected = IbgeMunicipalityCatalog::brazilianUfsByMunicipalityCountAsc();

        $this->assertSame($expected, $order);
        $this->assertSame('DF', $order[0]);
        $this->assertSame('MG', $order[array_key_last($order)]);
    }

    #[Test]
    public function tracks_done_ufs_and_remaining(): void
    {
        Cache::flush();
        HorizonteIbgeCentroidSyncProgress::reset();
        HorizonteIbgeCentroidSyncProgress::initialize();

        $this->assertFalse(HorizonteIbgeCentroidSyncProgress::isComplete());
        $this->assertContains('RR', HorizonteIbgeCentroidSyncProgress::remainingUfs());

        HorizonteIbgeCentroidSyncProgress::markDone('RR');
        HorizonteIbgeCentroidSyncProgress::markDone('AP');

        $this->assertSame(['RR', 'AP'], HorizonteIbgeCentroidSyncProgress::doneUfs());
        $this->assertNotContains('RR', HorizonteIbgeCentroidSyncProgress::remainingUfs());
    }
}
