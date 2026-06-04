<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\Ieducar\FundebRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use App\Support\Ieducar\MatriculaVolumeCounts;
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

    public function test_usa_total_matriculas_do_snapshot_financeiro(): void
    {
        $city = new City(['id' => 3, 'name' => 'Cidade', 'uf' => 'MG']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $mat = FundebRepository::resolveMatriculasAtivasForFilter(
            $city,
            $filters,
            ['kpis' => ['matriculas' => null]],
            ['kpis' => ['matriculas' => null]],
            ['total_matriculas' => 178, 'summary' => []],
        );

        $this->assertSame(178, $mat);
    }

    public function test_volume_counts_aplica_base_fundeb_com_alunos_menores(): void
    {
        $city = new City(['id' => 4, 'name' => 'Base', 'uf' => 'RJ']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $volume = FundebRepository::resolveVolumeCountsForFilter(
            $city,
            $filters,
            ['kpis' => ['matriculas' => 100, 'alunos_distintos' => 88]],
            ['kpis' => ['matriculas' => 90]],
        );

        $this->assertSame(100, $volume['matriculas']);
        $this->assertTrue($volume['alunos_available']);
        $this->assertSame(88, $volume['alunos']);
        $this->assertSame(
            MatriculaVolumeCounts::fundebCalculationBase($volume),
            $volume['base_calculo'],
        );
        $this->assertSame(88, $volume['base_calculo']);
    }
}
