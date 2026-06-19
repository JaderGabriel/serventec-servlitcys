<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedPipelineSaebPartialTest extends TestCase
{
    #[Test]
    public function partial_saeb_phase_does_not_advance_pipeline_index(): void
    {
        Cache::flush();

        $state = HorizonteFortnightlyFeedPipeline::start([
            'skip_fundeb' => true,
            'skip_censo' => true,
            'skip_cadunico' => true,
            'skip_sidra' => true,
            'skip_repasses' => true,
            'skip_saeb' => false,
            'skip_ibge' => true,
            'skip_sge' => true,
            'skip_verify' => true,
        ]);

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, [
            'key' => 'saeb_planilhas',
            'success' => true,
            'partial' => true,
            'message' => '1/3 anos',
            'saeb_done' => 1,
            'saeb_total' => 3,
        ]);

        $this->assertSame('running', $state['status']);
        $this->assertSame(0, $state['current_index']);
        $this->assertSame('saeb_planilhas', $state['current_phase']);
    }
}
