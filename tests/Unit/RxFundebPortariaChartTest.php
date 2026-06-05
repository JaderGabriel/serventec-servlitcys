<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Support\Rx\RxFundebPortariaChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RxFundebPortariaChartTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolve_fundeb_exercicio_usa_vigente_quando_config_zero(): void
    {
        config(['rx.fundeb_portaria_exercicio' => 0]);

        $this->assertSame(2026, RxFundebPortariaChart::resolveFundebExercicio(2026));
    }

    #[Test]
    public function resolve_fundeb_exercicio_respeita_override(): void
    {
        config(['rx.fundeb_portaria_exercicio' => 2025]);

        $this->assertSame(2025, RxFundebPortariaChart::resolveFundebExercicio(2026));
    }

    #[Test]
    public function build_monta_grafico_empilhado_em_milhoes(): void
    {
        config(['rx.fundeb_portaria_exercicio' => 0, 'rx.vigente_year' => 2026]);

        $city = City::factory()->create([
            'name' => 'Salvador',
            'ibge_municipio' => '2927408',
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => $city->id,
            'ibge_municipio' => '2927408',
            'ano' => 2026,
            'complementacao_vaaf' => 2_500_000,
            'complementacao_vaat' => 1_000_000,
            'complementacao_vaar' => 500_000,
            'fonte' => 'test',
        ]);

        $result = RxFundebPortariaChart::buildForCities(Collection::make([$city]), 2026);

        $this->assertTrue($result['available']);
        $this->assertSame(2026, $result['exercicio']);
        $this->assertSame(1, $result['municipios_com_dados']);

        $chart = $result['chart'];
        $this->assertIsArray($chart);
        $this->assertSame('bar', $chart['type']);
        $this->assertSame(__('Complementações previstas por município'), $chart['title']);
        $this->assertSame('y', $chart['options']['indexAxis'] ?? null);
        $this->assertTrue($chart['options']['scales']['x']['stacked'] ?? false);
        $this->assertTrue($chart['options']['scales']['y']['stacked'] ?? false);
        $this->assertSame(__('Milhões de R$'), $chart['options']['scales']['x']['title']['text'] ?? null);
        $this->assertCount(3, $chart['datasets']);
        $this->assertEqualsWithDelta(2.5, $chart['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(1.0, $chart['datasets'][1]['data'][0], 0.01);
        $this->assertEqualsWithDelta(0.5, $chart['datasets'][2]['data'][0], 0.01);
        $this->assertSame('brl_millions', $chart['options']['valueFormat'] ?? null);
        $this->assertSame('stack_total_compact', $chart['options']['datalabelsMode'] ?? null);
        $this->assertIsArray($result['ibge_table'] ?? null);
        $this->assertCount(1, $result['ibge_table']);
        $this->assertIsArray($result['national'] ?? null);
    }

    #[Test]
    public function build_retorna_indisponivel_sem_complementacoes(): void
    {
        $city = City::factory()->create(['ibge_municipio' => '2910800']);

        $result = RxFundebPortariaChart::buildForCities(Collection::make([$city]), 2026);

        $this->assertFalse($result['available']);
        $this->assertNull($result['chart']);
    }
}
