<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedPipelineTest extends TestCase
{
    #[Test]
    public function start_creates_running_pipeline_with_pending_phases(): void
    {
        Cache::flush();

        $state = HorizonteFortnightlyFeedPipeline::start([
            'skip_fundeb' => false,
            'skip_censo' => true,
            'skip_saeb' => true,
            'skip_ibge' => false,
            'skip_sge' => true,
            'skip_verify' => true,
        ]);

        $this->assertSame('running', $state['status']);
        $this->assertSame(['fundeb_receita', 'ibge_catalog'], $state['phase_queue']);
        $this->assertCount(2, $state['phases']);
        $this->assertTrue(HorizonteFortnightlyFeedPipeline::isActive());
    }

    #[Test]
    public function record_phase_result_advances_pipeline(): void
    {
        Cache::flush();

        $state = HorizonteFortnightlyFeedPipeline::start([
            'skip_fundeb' => false,
            'skip_censo' => true,
            'skip_saeb' => true,
            'skip_ibge' => true,
            'skip_sge' => true,
            'skip_verify' => true,
        ]);

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, [
            'key' => 'fundeb_receita',
            'success' => true,
            'message' => 'OK',
            'imported' => 10,
        ]);

        $this->assertSame('completed', $state['status']);
        $this->assertFalse(HorizonteFortnightlyFeedPipeline::isActive());
    }
}
