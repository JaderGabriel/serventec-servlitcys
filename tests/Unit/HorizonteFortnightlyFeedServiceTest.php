<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use App\Support\Horizonte\HorizonteFortnightlyFeedMonolithicProgress;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedServiceTest extends TestCase
{
    #[Test]
    public function dry_run_lists_all_phases_without_side_effects(): void
    {
        config(['horizonte.enabled' => true]);

        $result = app(HorizonteFortnightlyFeedService::class)->run(['dry_run' => true]);

        $this->assertTrue($result['success']);
        $this->assertCount(9, $result['phases']);
        $keys = array_column($result['phases'], 'key');
        $this->assertSame(
            [
                'fundeb_receita', 'censo_matriculas', 'cadunico_sync', 'sidra_demography',
                'repasses_tesouro', 'saeb_planilhas', 'ibge_catalog', 'sge_registry', 'official_check',
            ],
            $keys,
        );
    }

    #[Test]
    public function staged_dry_run_executes_single_phase_and_keeps_pipeline_running(): void
    {
        config(['horizonte.enabled' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $service = app(HorizonteFortnightlyFeedService::class);
        $result = $service->runStaged([
            'dry_run' => true,
            'reset' => true,
            'skip_censo' => true,
            'skip_cadunico' => true,
            'skip_sidra' => true,
            'skip_repasses' => true,
            'skip_saeb' => true,
            'skip_ibge' => false,
            'skip_sge' => true,
            'skip_verify' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['phases']);
        $this->assertSame('fundeb_receita', $result['phases'][0]['key']);
        $this->assertSame('running', $result['pipeline']['status'] ?? null);
        $this->assertSame('ibge_catalog', $result['pipeline']['current_phase'] ?? null);
    }

    #[Test]
    public function dry_run_all_stores_cacheable_options_without_debug_callback(): void
    {
        config(['horizonte.enabled' => true]);
        Cache::flush();

        $result = app(HorizonteFortnightlyFeedService::class)->run([
            'dry_run' => true,
            'verbose' => true,
            'debug' => static function (): void {},
        ]);

        $this->assertTrue($result['success']);
        $cached = HorizonteFortnightlyFeedMonolithicProgress::get();
        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('debug', $cached['options'] ?? []);
    }

    #[Test]
    public function returns_failure_when_horizonte_disabled(): void
    {
        config(['horizonte.enabled' => false]);

        $result = app(HorizonteFortnightlyFeedService::class)->run();

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['phases']);
    }
}
