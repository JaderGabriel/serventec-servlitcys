<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\FundebVaafProfileBuilder;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class FundebDockMeterTest extends TestCase
{
    #[Test]
    public function build_dock_meter_sem_ano_retorna_vazio(): void
    {
        $builder = app(FundebVaafProfileBuilder::class);
        $city = new City(['name' => 'Test', 'uf' => 'SP', 'ibge_municipio' => '3550308']);
        $filters = new IeducarFilterState(null, null, null, null);

        $meter = $builder->buildDockMeter($city, $filters);

        $this->assertFalse($meter['available']);
        $this->assertSame('neutral', $meter['status']);
        $this->assertFalse($meter['projection_blocked']);
        $this->assertSame(__('Indisponível'), $meter['status_label']);
    }

    #[Test]
    public function evaluate_dock_blocking_marca_discrepancias(): void
    {
        $result = $this->invokeEvaluateDockBlocking(
            2025,
            ['matriculas' => ['usado' => 100], 'db_reference' => null, 'receita' => ['disponivel' => true, 'total' => 1_000_000]],
            [],
            ['com_problema' => 8, 'corrigiveis' => 2],
        );

        $this->assertTrue($result['blocked']);
        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('Inconsistências', $result['reasons'][0]['message']);
    }

    #[Test]
    public function dock_consolidated_figure_prioriza_receita_fnde(): void
    {
        $figure = $this->invokeDockConsolidatedFigure([
            'ano' => 2025,
            'receita' => ['disponivel' => true, 'total' => 2_500_000.0],
            'previsao_recursos' => ['base_anual' => 1_000_000.0],
        ]);

        $this->assertSame(2_500_000.0, $figure['amount']);
        $this->assertStringContainsString('2025', $figure['label']);
        $this->assertStringContainsString('mi', $figure['display']);
    }

    /**
     * @param  array<string, mixed>  $currentBlock
     * @param  list<array<string, mixed>>  $alerts
     * @param  array<string, int>|null  $discrepanciesSummary
     * @return array{blocked: bool, status: string, reasons: list<array{severity: string, message: string}>}
     */
    private function invokeEvaluateDockBlocking(
        int $anchor,
        array $currentBlock,
        array $alerts,
        ?array $discrepanciesSummary,
    ): array {
        $method = new ReflectionMethod(FundebVaafProfileBuilder::class, 'evaluateDockBlocking');
        $method->setAccessible(true);

        /** @var array{blocked: bool, status: string, reasons: list<array{severity: string, message: string}>} $result */
        $result = $method->invoke(null, $anchor, $currentBlock, $alerts, $discrepanciesSummary);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{amount: ?float, display: string, label: string}
     */
    private function invokeDockConsolidatedFigure(array $block): array
    {
        $method = new ReflectionMethod(FundebVaafProfileBuilder::class, 'dockConsolidatedFigure');
        $method->setAccessible(true);

        /** @var array{amount: ?float, display: string, label: string} $result */
        $result = $method->invoke(null, $block);

        return $result;
    }
}
