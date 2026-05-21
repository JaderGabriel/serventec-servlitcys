<?php

namespace Tests\Unit;

use App\Support\Ieducar\IeducarWorkActivityQueries;
use Tests\TestCase;

class IeducarWorkActivityQueriesTest extends TestCase
{
    public function test_pace_per_day_divides_fortnight_count(): void
    {
        $this->assertSame(2.0, IeducarWorkActivityQueries::pacePerDay(30));
    }

    public function test_build_estimate_uses_remaining_from_baseline(): void
    {
        $est = IeducarWorkActivityQueries::buildEstimate(
            ['turmas' => 50, 'matriculas' => 1000, 'ano' => 2024],
            ['day' => 5, 'week' => 20, 'fortnight' => 30],
            800
        );

        $this->assertSame(1000, $est['meta_matriculas_ano_anterior']);
        $this->assertSame(200, $est['registros_restantes_estimados']);
        $this->assertSame(800, $est['matriculas_ativas_filtro']);
        $this->assertTrue($est['usa_ritmo_observado']);
        $this->assertSame(100, $est['dias_para_concluir_ritmo_atual']);
    }

    public function test_build_estimate_without_pace_uses_default_minutes(): void
    {
        $est = IeducarWorkActivityQueries::buildEstimate(
            ['turmas' => 10, 'matriculas' => 100, 'ano' => 2023],
            ['day' => 0, 'week' => 0, 'fortnight' => 0],
            50
        );

        $this->assertFalse($est['usa_ritmo_observado']);
        $this->assertSame(50, $est['registros_restantes_estimados']);
        $this->assertNull($est['dias_para_concluir_ritmo_atual']);
    }
}
