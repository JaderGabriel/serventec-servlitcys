<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Services\Horizonte\HorizonteMunicipioEnrollmentSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMunicipioEnrollmentSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private HorizonteMunicipioEnrollmentSeriesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HorizonteMunicipioEnrollmentSeriesService::class);
    }

    #[Test]
    public function rejeita_ibge_invalido(): void
    {
        $result = $this->service->forIbge('abc');

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);
    }

    #[Test]
    public function bloqueia_municipio_com_consultoria_activa(): void
    {
        City::factory()->create([
            'ibge_municipio' => '2927408',
            'is_active' => true,
        ]);

        InepCensoMunicipioMatricula::query()->create([
            'ibge_municipio' => '2927408',
            'ano' => 2023,
            'matriculas_total' => 12000,
        ]);

        $result = $this->service->forIbge('2927408');

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);
    }

    #[Test]
    public function devolve_serie_com_segmentos_para_municipio_sem_consultoria(): void
    {
        foreach ([2020, 2021, 2022] as $year) {
            InepCensoMunicipioMatricula::query()->create([
                'ibge_municipio' => '2927408',
                'ano' => $year,
                'matriculas_total' => 10000 + ($year - 2020) * 500,
                'matriculas_regular' => 8000,
                'matriculas_eja' => 1200,
                'matriculas_especial' => 500,
                'matriculas_complementar' => 300,
            ]);
        }

        $result = $this->service->forIbge('2927408', 3);

        $this->assertTrue($result['ok']);
        $this->assertSame('2927408', $result['ibge']);
        $this->assertTrue($result['has_segments']);
        $this->assertSame('line', $result['chart']['type']);
        $this->assertSame(['2020', '2021', '2022'], $result['chart']['labels']);
        $this->assertCount(5, $result['chart']['datasets']);
    }

    #[Test]
    public function devolve_apenas_total_quando_segmentos_ausentes(): void
    {
        InepCensoMunicipioMatricula::query()->create([
            'ibge_municipio' => '2927408',
            'ano' => 2023,
            'matriculas_total' => 9500,
        ]);

        $result = $this->service->forIbge('2927408');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['has_segments']);
        $this->assertCount(1, $result['chart']['datasets']);
    }

    #[Test]
    public function retorna_404_sem_dados_indexados(): void
    {
        $result = $this->service->forIbge('2927408');

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['status']);
    }
}
