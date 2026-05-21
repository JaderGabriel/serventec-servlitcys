<?php

namespace Tests\Unit;

use App\Services\CityDataConnection;
use App\Services\Dashboard\AdminSystemFlowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminSystemFlowStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function diagram_includes_zones_legend_and_summary(): void
    {
        $diagram = (new AdminSystemFlowStatus(app(CityDataConnection::class)))
            ->diagram(2, 3);

        $this->assertArrayHasKey('summary', $diagram);
        $this->assertArrayHasKey('zones', $diagram);
        $this->assertCount(3, $diagram['zones']);
        $this->assertArrayHasKey('legend', $diagram);
        $this->assertCount(3, $diagram['legend']);
        $this->assertNotEmpty($diagram['legend'][0]['description'] ?? '');
        $this->assertArrayHasKey('outputs', $diagram);
        $this->assertGreaterThanOrEqual(3, count($diagram['outputs']));
    }

    #[Test]
    public function edges_include_visible_labels(): void
    {
        $diagram = (new AdminSystemFlowStatus(app(CityDataConnection::class)))
            ->diagram(1, 1);

        $ieducarEdge = collect($diagram['edges'])->firstWhere('from', 'ieducar');
        $this->assertNotNull($ieducarEdge);
        $this->assertNotEmpty($ieducarEdge['label']);
        $this->assertTrue($ieducarEdge['bidirectional'] ?? false);
    }
}
