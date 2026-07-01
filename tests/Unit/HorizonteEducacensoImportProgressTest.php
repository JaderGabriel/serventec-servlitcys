<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteEducacensoImportProgressTest extends TestCase
{
    #[Test]
    public function tracks_done_years_and_remaining(): void
    {
        Cache::flush();
        HorizonteEducacensoImportProgress::reset();

        $all = [2020, 2022, 2024];
        $this->assertSame($all, HorizonteEducacensoImportProgress::remainingYears($all));

        HorizonteEducacensoImportProgress::markDone(2020);
        HorizonteEducacensoImportProgress::markDone(2022);

        $this->assertSame([2020, 2022], HorizonteEducacensoImportProgress::doneYears());
        $this->assertSame([2024], HorizonteEducacensoImportProgress::remainingYears($all));
        $this->assertFalse(HorizonteEducacensoImportProgress::isComplete($all));
    }

    #[Test]
    public function ordered_remaining_rotates_last_failed_year_to_end(): void
    {
        Cache::flush();
        HorizonteEducacensoImportProgress::reset();

        $all = [2020, 2022];
        HorizonteEducacensoImportProgress::markFailed(2020);

        $this->assertSame([2022, 2020], HorizonteEducacensoImportProgress::orderedRemainingYears($all));
    }

    #[Test]
    public function mark_done_clears_last_failed(): void
    {
        Cache::flush();
        HorizonteEducacensoImportProgress::markFailed(2020);
        HorizonteEducacensoImportProgress::markDone(2020);

        $this->assertNull(HorizonteEducacensoImportProgress::lastFailedYear());
    }
}
