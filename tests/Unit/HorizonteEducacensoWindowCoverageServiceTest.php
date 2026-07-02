<?php

namespace Tests\Unit;

use App\Models\InepCensoMunicipioMatricula;
use App\Services\Horizonte\HorizonteEducacensoWindowCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteEducacensoWindowCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite indisponível neste ambiente.');
        }

        parent::setUp();
    }

    #[Test]
    public function detecta_municipio_com_todos_os_anos_da_janela(): void
    {
        config(['horizonte.enrollment_series.years' => 3, 'horizonte.reference_year' => 2024]);

        foreach ([2022, 2023, 2024] as $year) {
            InepCensoMunicipioMatricula::query()->create([
                'ibge_municipio' => '2927408',
                'ano' => $year,
                'matriculas_total' => 1000 + $year,
            ]);
        }

        $result = app(HorizonteEducacensoWindowCoverageService::class)->auditRandomMunicipalities(1, 42);

        $this->assertSame(1, $result['complete_count']);
        $this->assertSame(0, $result['incomplete_count']);
        $this->assertTrue($result['municipalities'][0]['complete']);
    }

    #[Test]
    public function detecta_lacunas_historicas_na_amostra(): void
    {
        config(['horizonte.enrollment_series.years' => 3, 'horizonte.reference_year' => 2024]);

        foreach ([2022, 2024] as $year) {
            InepCensoMunicipioMatricula::query()->create([
                'ibge_municipio' => '2927408',
                'ano' => $year,
                'matriculas_total' => 5000,
            ]);
        }

        $result = app(HorizonteEducacensoWindowCoverageService::class)->auditRandomMunicipalities(1, 7);

        $this->assertSame(0, $result['complete_count']);
        $this->assertSame(1, $result['incomplete_count']);
        $this->assertSame([2023], $result['municipalities'][0]['missing_years']);
        $this->assertFalse($result['ok']);
    }

    #[Test]
    public function amostra_reproducivel_com_seed(): void
    {
        config(['horizonte.enrollment_series.years' => 2, 'horizonte.reference_year' => 2024]);

        foreach (['1111111', '2222222', '3333333'] as $ibge) {
            foreach ([2023, 2024] as $year) {
                InepCensoMunicipioMatricula::query()->create([
                    'ibge_municipio' => $ibge,
                    'ano' => $year,
                    'matriculas_total' => 100,
                ]);
            }
        }

        $service = app(HorizonteEducacensoWindowCoverageService::class);
        $a = $service->auditRandomMunicipalities(2, 99);
        $b = $service->auditRandomMunicipalities(2, 99);

        $this->assertSame(
            array_column($a['municipalities'], 'ibge'),
            array_column($b['municipalities'], 'ibge'),
        );
    }
}
