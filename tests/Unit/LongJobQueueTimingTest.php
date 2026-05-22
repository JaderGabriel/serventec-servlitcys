<?php

namespace Tests\Unit;

use App\Support\Queue\LongJobQueueTiming;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LongJobQueueTimingTest extends TestCase
{
    #[Test]
    public function it_computes_retry_after_from_job_timeouts_when_env_unset(): void
    {
        config([
            'ieducar.admin_sync.job_timeout' => 3600,
            'analytics.pdf_report.job_timeout' => 900,
        ]);

        $this->assertSame(3720, LongJobQueueTiming::retryAfterSeconds());
    }
}
