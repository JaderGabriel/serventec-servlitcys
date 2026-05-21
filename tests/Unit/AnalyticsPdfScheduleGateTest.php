<?php

namespace Tests\Unit;

use App\Support\Scheduling\AnalyticsPdfScheduleGate;
use PHPUnit\Framework\TestCase;

class AnalyticsPdfScheduleGateTest extends TestCase
{
    public function test_gate_exposes_pending_work_checks(): void
    {
        $this->assertTrue(method_exists(AnalyticsPdfScheduleGate::class, 'hasPendingWork'));
        $this->assertTrue(method_exists(AnalyticsPdfScheduleGate::class, 'hasPendingExports'));
        $this->assertTrue(method_exists(AnalyticsPdfScheduleGate::class, 'queuedJobCount'));
    }
}
