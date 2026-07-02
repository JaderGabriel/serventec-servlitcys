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

        $this->assertSame([2020, 2022], HorizonteEducacensoImportProgress::doneYears($all));
        $this->assertSame([2024], HorizonteEducacensoImportProgress::remainingYears($all));
        $this->assertFalse(HorizonteEducacensoImportProgress::isComplete($all));
    }

    #[Test]
    public function ordered_remaining_puts_failed_step_at_end(): void
    {
        Cache::flush();
        HorizonteEducacensoImportProgress::reset();

        $all = [2020, 2022];
        HorizonteEducacensoImportProgress::markStepFailed(2020, 'SP');

        $remaining = HorizonteEducacensoImportProgress::orderedRemainingSteps($all);
        $last = $remaining[array_key_last($remaining)];

        $this->assertSame(2020, $last['year']);
        $this->assertSame('SP', $last['uf']);
    }

    #[Test]
    public function mark_done_clears_last_failed(): void
    {
        Cache::flush();
        HorizonteEducacensoImportProgress::reset();

        HorizonteEducacensoImportProgress::markStepFailed(2020, 'BA');
        HorizonteEducacensoImportProgress::markDone(2020);

        $this->assertNull(HorizonteEducacensoImportProgress::lastFailedStep());
    }

    #[Test]
    public function step_key_round_trip(): void
    {
        $this->assertSame('2024:BA', HorizonteEducacensoImportProgress::stepKey(2024, 'ba'));
        $parsed = HorizonteEducacensoImportProgress::parseStepKey('2024:BA');
        $this->assertSame(['year' => 2024, 'uf' => 'BA'], $parsed);
    }

    #[Test]
    public function total_steps_is_years_times_ufs(): void
    {
        $years = [2020, 2024];
        $expected = count($years) * count(HorizonteEducacensoImportProgress::allUfs());
        $this->assertSame($expected, HorizonteEducacensoImportProgress::totalSteps($years));
    }
}
