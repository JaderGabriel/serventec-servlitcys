<?php

namespace Tests\Unit;

use App\Support\Ieducar\DiscrepanciesModuleCatalog;
use App\Support\Ieducar\DiscrepanciesRoutineStatus;
use Tests\TestCase;

final class DiscrepanciesModuleCatalogTest extends TestCase
{
    public function test_build_panel_includes_territorio_with_geo_routine(): void
    {
        $dimensions = [
            [
                'id' => 'escola_sem_geo',
                'title' => 'Sem geo',
                'status' => 'warning',
                'has_issue' => true,
                'analyzed' => true,
                'schools_count' => 3,
                'occurrences_total' => 120,
                'total' => 120,
                'perda_estimada_anual' => 1500.0,
                'ganho_potencial_anual' => 1500.0,
                'escola_ids' => ['1', '2', '3'],
            ],
            [
                'id' => 'sem_raca',
                'title' => 'Sem raça',
                'status' => DiscrepanciesRoutineStatus::OK,
                'has_issue' => false,
                'analyzed' => true,
                'occurrences_total' => 0,
                'total' => 0,
            ],
        ];

        $modules = DiscrepanciesModuleCatalog::buildPanel($dimensions, []);

        $territorio = collect($modules)->firstWhere('id', 'territorio');
        $this->assertNotNull($territorio);
        $this->assertSame('warning', $territorio['status']);
        $this->assertSame(1500.0, $territorio['perda_estimada_anual']);
        $this->assertSame('school_units', $territorio['correction_tab']);
        $this->assertCount(1, $territorio['routines']);
        $this->assertSame('escola_sem_geo', $territorio['routines'][0]['id']);
    }

    public function test_routine_metric_summary_geo_uses_schools_and_matriculas(): void
    {
        $text = DiscrepanciesModuleCatalog::routineMetricSummary([
            'id' => 'escola_sem_geo',
            'has_issue' => true,
            'schools_count' => 2,
            'occurrences_total' => 85,
        ]);

        $this->assertStringContainsString('2', $text);
        $this->assertStringContainsString('85', $text);
    }
}
