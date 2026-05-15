<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesRoutineStatus;
use Tests\TestCase;

final class DiscrepanciesRoutineStatusTest extends TestCase
{
    public function test_ok_when_available_without_issue_and_matriculas(): void
    {
        $city = new City(['id' => 1, 'name' => 'Teste']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $resolved = DiscrepanciesRoutineStatus::resolve('sem_raca', [
            'availability' => 'available',
            'has_issue' => false,
            'rows' => [],
        ], 120, $city, $filters);

        $this->assertSame(DiscrepanciesRoutineStatus::OK, $resolved['status']);
    }

    public function test_no_data_when_matriculas_zero_for_matricula_scoped_check(): void
    {
        $city = new City(['id' => 1, 'name' => 'Teste']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $resolved = DiscrepanciesRoutineStatus::resolve('sem_raca', [
            'availability' => 'available',
            'has_issue' => false,
            'rows' => [],
        ], 0, $city, $filters);

        $this->assertSame(DiscrepanciesRoutineStatus::NO_DATA, $resolved['status']);
        $this->assertSame('no_data', $resolved['availability']);
    }

    public function test_unavailable_when_probe_fails(): void
    {
        $city = new City(['id' => 1, 'name' => 'Teste']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $resolved = DiscrepanciesRoutineStatus::resolve('sem_raca', [
            'availability' => 'unavailable',
            'has_issue' => false,
            'rows' => [],
            'unavailable_reason' => 'Tabela ausente',
        ], 50, $city, $filters);

        $this->assertSame(DiscrepanciesRoutineStatus::UNAVAILABLE, $resolved['status']);
    }
}
