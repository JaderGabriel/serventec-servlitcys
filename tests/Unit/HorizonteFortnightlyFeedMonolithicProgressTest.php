<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFortnightlyFeedMonolithicProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedMonolithicProgressTest extends TestCase
{
    #[Test]
    public function tracks_completed_phases_and_remaining(): void
    {
        Cache::flush();

        HorizonteFortnightlyFeedMonolithicProgress::start(
            ['fundeb_receita', 'ibge_catalog', 'sge_registry'],
            ['skip_fundeb' => false],
        );

        $this->assertTrue(HorizonteFortnightlyFeedMonolithicProgress::isRunning());
        $this->assertSame(
            ['fundeb_receita', 'ibge_catalog', 'sge_registry'],
            HorizonteFortnightlyFeedMonolithicProgress::remainingPhases(),
        );

        HorizonteFortnightlyFeedMonolithicProgress::markPhaseDone('fundeb_receita');

        $this->assertSame(['fundeb_receita'], HorizonteFortnightlyFeedMonolithicProgress::completedPhases());
        $this->assertSame(['ibge_catalog', 'sge_registry'], HorizonteFortnightlyFeedMonolithicProgress::remainingPhases());
    }

    #[Test]
    public function marks_completed_when_all_phases_done(): void
    {
        Cache::flush();

        HorizonteFortnightlyFeedMonolithicProgress::start(['sge_registry'], []);
        HorizonteFortnightlyFeedMonolithicProgress::markPhaseDone('sge_registry');

        $state = HorizonteFortnightlyFeedMonolithicProgress::get();
        $this->assertSame('completed', $state['status'] ?? null);
        $this->assertFalse(HorizonteFortnightlyFeedMonolithicProgress::isRunning());
    }
}
