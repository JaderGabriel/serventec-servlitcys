<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSaebImportProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteSaebImportProgressTest extends TestCase
{
    #[Test]
    public function tracks_done_years_and_remaining(): void
    {
        Cache::flush();
        HorizonteSaebImportProgress::reset();

        $all = [2021, 2023, 2025];
        $this->assertSame($all, HorizonteSaebImportProgress::remainingYears($all));

        HorizonteSaebImportProgress::markDone(2021);
        HorizonteSaebImportProgress::markDone(2023);

        $this->assertSame([2021, 2023], HorizonteSaebImportProgress::doneYears());
        $this->assertSame([2025], HorizonteSaebImportProgress::remainingYears($all));
        $this->assertFalse(HorizonteSaebImportProgress::isComplete($all));
    }
}
