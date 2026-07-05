<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoImportProgressSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteEducacensoImportProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'horizonte.fortnightly_feed.educacenso_progress_snapshot_path' => 'horizonte/educacenso_import_progress.json',
            'horizonte.fortnightly_feed.educacenso_infer_progress_from_db' => false,
        ]);
        Cache::flush();
        HorizonteEducacensoImportProgress::reset();
    }

    #[Test]
    public function tracks_done_years_and_remaining(): void
    {
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

    #[Test]
    public function snapshot_sobrevive_a_cache_clear(): void
    {
        HorizonteEducacensoImportProgress::markStepDone(2024, 'BA');

        $this->assertTrue(Storage::disk('local')->exists(HorizonteEducacensoImportProgressSnapshot::relativePath()));

        Cache::flush();

        $this->assertContains('2024:BA', HorizonteEducacensoImportProgress::doneSteps());
    }

    #[Test]
    public function reset_apaga_snapshot(): void
    {
        HorizonteEducacensoImportProgress::markStepDone(2023, 'SP');
        $this->assertTrue(Storage::disk('local')->exists(HorizonteEducacensoImportProgressSnapshot::relativePath()));

        HorizonteEducacensoImportProgress::reset();

        $this->assertFalse(Storage::disk('local')->exists(HorizonteEducacensoImportProgressSnapshot::relativePath()));
    }
}

