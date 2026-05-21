<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use Tests\TestCase;

class IeducarWorkActivityQueriesTest extends TestCase
{
    public function test_year_closure_insight_marca_consolidado_sem_cadastro_recente(): void
    {
        $insight = IeducarWorkActivityQueries::yearClosureInsight(
            new IeducarFilterState('2025', null, null, null),
            [
                'summary' => [
                    'total_escolas' => 10,
                    'exportadas' => 6,
                    'fechadas' => 4,
                    'pendentes' => 0,
                ],
            ],
            ['day' => 0, 'week' => 0, 'fortnight' => 0],
            null,
        );

        $this->assertNotNull($insight);
        $this->assertTrue($insight['consolidated']);
    }

    public function test_observed_cadastro_pace_prefers_fortnight_blend(): void
    {
        $observed = IeducarWorkActivityQueries::observedCadastroPace([
            'day' => 2,
            'week' => 14,
            'fortnight' => 45,
        ]);

        $this->assertSame('quinzena_semana', $observed['fonte']);
        $this->assertGreaterThan(0, $observed['pace']);
        $this->assertSame(45, $observed['cadastros_quinzena']);
    }

    public function test_build_estimate_uses_observed_pace_not_static_minutes(): void
    {
        $baseline = ['turmas' => 100, 'matriculas' => 1000, 'enturmacoes' => 1000, 'ano' => 2024];
        $periods = ['day' => 5, 'week' => 20, 'fortnight' => 60];
        $byUser = [['total' => 30], ['total' => 30]];

        $est = IeducarWorkActivityQueries::buildEstimate(
            $baseline,
            $periods,
            80,
            700,
            700,
            $byUser,
        );

        $this->assertTrue($est['usa_ritmo_observado']);
        $this->assertTrue($est['minutos_derivados_do_ritmo']);
        $this->assertNotSame(
            (float) config('ieducar.work_tracking.minutes_per_matricula', 3.5),
            (float) $est['minutos_por_matricula']
        );
        $this->assertGreaterThan(0, $est['ritmo_por_dia']);
        $this->assertGreaterThan($est['ritmo_por_dia'], $est['ritmo_equipe_por_dia']);
        $this->assertNotNull($est['dias_para_concluir_ritmo_atual']);
    }

    public function test_build_estimate_falls_back_when_no_recent_cadastro(): void
    {
        $baseline = ['turmas' => 50, 'matriculas' => 500, 'enturmacoes' => 500, 'ano' => 2024];
        $periods = ['day' => 0, 'week' => 0, 'fortnight' => 0];

        $est = IeducarWorkActivityQueries::buildEstimate($baseline, $periods, 10, 100, 100);

        $this->assertFalse($est['usa_ritmo_observado']);
        $this->assertFalse($est['minutos_derivados_do_ritmo']);
        $this->assertSame('sem_cadastro_recente', $est['ritmo_fonte']);
        $this->assertSame(
            (float) config('ieducar.work_tracking.minutes_per_matricula', 3.5),
            (float) $est['minutos_por_matricula']
        );
    }
}
