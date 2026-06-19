<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteIbgeWarmProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteIbgeWarmProgressTest extends TestCase
{
    #[Test]
    public function tracks_done_ufs_and_remaining(): void
    {
        Cache::flush();
        HorizonteIbgeWarmProgress::reset();

        $this->assertFalse(HorizonteIbgeWarmProgress::isComplete());
        $this->assertContains('SP', HorizonteIbgeWarmProgress::remainingUfs());

        HorizonteIbgeWarmProgress::markDone(['SP', 'RJ']);
        $this->assertSame(['SP', 'RJ'], HorizonteIbgeWarmProgress::doneUfs());
        $this->assertNotContains('SP', HorizonteIbgeWarmProgress::remainingUfs());
    }
}
