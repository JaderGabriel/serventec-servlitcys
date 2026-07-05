<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedPipelineTest extends TestCase
{
    /** @return array<string, bool> */
    private function baseSkips(): array
    {
        return [
            'skip_fundeb' => false,
            'skip_censo' => true,
            'skip_educacenso' => true,
            'skip_cadunico' => true,
            'skip_sidra' => true,
            'skip_repasses' => true,
            'skip_siconfi' => true,
            'skip_transparency' => true,
            'skip_saeb' => true,
            'skip_ibge' => false,
            'skip_ibge_municipal_geo' => true,
            'skip_sge' => true,
            'skip_alerts' => true,
            'skip_verify' => true,
        ];
    }

    #[Test]
    public function start_creates_running_pipeline_with_pending_phases(): void
    {
        Cache::flush();

        $state = HorizonteFortnightlyFeedPipeline::start($this->baseSkips());

        $this->assertSame('running', $state['status']);
        $this->assertSame(['fundeb_receita', 'ibge_catalog'], $state['phase_queue']);
        $this->assertCount(2, $state['phases']);
        $this->assertTrue(HorizonteFortnightlyFeedPipeline::isActive());
    }

    #[Test]
    public function record_phase_result_advances_pipeline(): void
    {
        Cache::flush();

        $options = $this->baseSkips();
        $options['skip_ibge'] = true;

        $state = HorizonteFortnightlyFeedPipeline::start($options);

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, [
            'key' => 'fundeb_receita',
            'success' => true,
            'message' => 'OK',
            'imported' => 10,
        ]);

        $this->assertSame('completed', $state['status']);
        $this->assertFalse(HorizonteFortnightlyFeedPipeline::isActive());
    }

    #[Test]
    public function partial_phase_does_not_advance_pipeline_index(): void
    {
        Cache::flush();

        $options = $this->baseSkips();
        $options['skip_fundeb'] = true;

        $state = HorizonteFortnightlyFeedPipeline::start($options);

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, [
            'key' => 'ibge_catalog',
            'success' => true,
            'partial' => true,
            'message' => '1/27',
            'ibge_done' => 1,
            'ibge_total' => 27,
        ]);

        $this->assertSame('running', $state['status']);
        $this->assertSame(0, $state['current_index']);
        $this->assertSame('ibge_catalog', $state['current_phase']);
        $this->assertSame('partial', $state['phases'][0]['status']);
    }
}
