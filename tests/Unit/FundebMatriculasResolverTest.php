<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\Ieducar\FundebRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use Tests\TestCase;

final class FundebMatriculasResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        IeducarAnalyticsMetricsScope::forget();
        parent::tearDown();
    }

    public function test_usa_maior_valor_entre_scope_overview_e_enrollment(): void
    {
        $city = new City(['id' => 1, 'name' => 'Teste', 'uf' => 'BA']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $mat = FundebRepository::resolveMatriculasAtivasForFilter(
            $city,
            $filters,
            ['kpis' => ['matriculas' => 100]],
            ['kpis' => ['matriculas' => 4614]],
        );

        $this->assertSame(4614, $mat);
    }

    public function test_prioriza_enrollment_quando_overview_sem_matriculas(): void
    {
        $city = new City(['id' => 2, 'name' => 'Município', 'uf' => 'SP']);
        $filters = new IeducarFilterState('2025', null, null, null);

        $mat = FundebRepository::resolveMatriculasAtivasForFilter(
            $city,
            $filters,
            ['kpis' => ['matriculas' => null, 'escolas' => 10, 'turmas' => 20]],
            ['kpis' => ['matriculas' => 4614, 'turmas_distintas' => 80]],
        );

        $this->assertSame(4614, $mat);
    }
}
