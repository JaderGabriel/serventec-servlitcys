<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteFortnightlyFeedService;
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
        $this->assertCount(5, $result['phases']);
        $keys = array_column($result['phases'], 'key');
        $this->assertSame(
            ['fundeb_receita', 'censo_matriculas', 'saeb_planilhas', 'ibge_catalog', 'official_check'],
            $keys,
        );
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
