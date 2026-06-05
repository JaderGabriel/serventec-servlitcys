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
        $this->assertArrayHasKey('flow_steps', $diagram);
        $this->assertCount(4, $diagram['flow_steps']);
        $municipalZone = collect($diagram['zones'])->firstWhere('id', 'municipal');
        $this->assertSame(1, $municipalZone['step'] ?? null);
        $this->assertArrayHasKey('legend', $diagram);
        $this->assertGreaterThanOrEqual(4, count($diagram['legend']));
        $this->assertNotEmpty($diagram['legend'][0]['description'] ?? '');
        $legendTotal = array_sum(array_column($diagram['legend'], 'count'));
        $this->assertSame(
            count($diagram['nodes']) + count($diagram['edges']) + count($diagram['planned_nodes'] ?? []),
            $legendTotal,
        );
        $this->assertArrayHasKey('planned_nodes', $diagram);
        $this->assertGreaterThanOrEqual(4, count($diagram['planned_nodes']));
        $plannedLegend = collect($diagram['legend'])->firstWhere('status', 'planned');
        $this->assertNotNull($plannedLegend);
        $this->assertSame(count($diagram['planned_nodes']), $plannedLegend['count'] ?? null);
        $this->assertArrayHasKey('nodes', $diagram);
        $this->assertArrayHasKey('edges', $diagram);
        $this->assertArrayHasKey('outputs', $diagram);
        $this->assertCount(3, $diagram['outputs']);
        $this->assertGreaterThanOrEqual(6, count($diagram['nodes']));
        $hubOutEdge = collect($diagram['edges'])->firstWhere('from', 'servlitcys');
        $this->assertNotNull($hubOutEdge);
        $this->assertSame('platform', $hubOutEdge['channel'] ?? null);
        $cadunico = collect($diagram['nodes'])->firstWhere('id', 'cadunico');
        $this->assertNotNull($cadunico);
        $this->assertSame('social', $cadunico['category'] ?? null);
        $cadunicoEdge = collect($diagram['edges'])->firstWhere('from', 'cadunico');
        $this->assertNotNull($cadunicoEdge);
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
