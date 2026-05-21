<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesRoutineMetrics;
use Tests\TestCase;

final class DiscrepanciesRoutineMetricsTest extends TestCase
{
    public function test_occurrence_totals_sums_matriculas_not_school_rows_only(): void
    {
        $eval = [
            'has_issue' => true,
            'rows' => [
                ['escola_id' => '1', 'escola' => 'A', 'total' => 10],
                ['escola_id' => '2', 'escola' => 'B', 'total' => 5],
            ],
        ];

        $totals = DiscrepanciesRoutineMetrics::occurrenceTotals($eval);

        $this->assertSame(2, $totals['schools_count']);
        $this->assertSame(15, $totals['occurrences_total']);
        $this->assertCount(2, $totals['escola_ids']);
    }

    public function test_summary_from_routines_aligns_with_dimensions(): void
    {
        $routines = [
            [
                'has_issue' => true,
                'analyzed' => true,
                'occurrences_total' => 12,
                'perda_estimada_anual' => 1000.0,
                'ganho_potencial_anual' => 1000.0,
                'escola_ids' => ['10', '20'],
            ],
            [
                'has_issue' => true,
                'analyzed' => true,
                'occurrences_total' => 3,
                'perda_estimada_anual' => 200.0,
                'ganho_potencial_anual' => 200.0,
                'escola_ids' => ['20'],
            ],
            [
                'has_issue' => false,
                'analyzed' => true,
            ],
        ];

        $summary = DiscrepanciesRoutineMetrics::summaryFromRoutines($routines, 500);

        $this->assertSame(15, $summary['com_problema']);
        $this->assertSame(2, $summary['escolas_afetadas']);
        $this->assertSame(1200.0, $summary['perda_estimada_anual']);
        $this->assertSame(2, $summary['rotinas_com_pendencia']);
    }

    public function test_dimension_from_eval_matches_occurrence_count(): void
    {
        $city = new City(['id' => 1, 'name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);
        $meta = [
            'id' => 'sem_raca',
            'title' => 'Sem raça',
            'severity' => 'warning',
        ];
        $eval = [
            'availability' => 'available',
            'has_issue' => true,
            'rows' => [
                ['escola_id' => '7', 'escola' => 'Escola', 'total' => 4],
            ],
        ];

        $dim = DiscrepanciesRoutineMetrics::dimensionFromEval('sem_raca', $meta, $eval, 100, $city, $filters);

        $this->assertSame(4, $dim['occurrences_total']);
        $this->assertSame(1, $dim['schools_count']);
        $this->assertSame(4.0, $dim['pct_rede']);
        $this->assertTrue($dim['has_issue']);
    }
}
