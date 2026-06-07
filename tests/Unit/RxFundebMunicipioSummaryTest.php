<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Rx\RxFundebMunicipioSummary;
use Mockery;
use Tests\TestCase;

final class RxFundebMunicipioSummaryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_vazio_sem_matriculas(): void
    {
        config(['rx.fundeb_municipio_summary' => true]);

        $city = City::factory()->make(['id' => 1, 'ibge_municipio' => '2913309']);
        $repo = Mockery::mock(DiscrepanciesRepository::class);
        $repo->shouldNotReceive('fundingImpactSnapshot');

        $out = RxFundebMunicipioSummary::build(
            $city,
            new IeducarFilterState('2026', null, null, null),
            0,
            null,
            $repo,
        );

        $this->assertFalse($out['available']);
    }

    public function test_monta_previsao_com_exercicio_seguinte(): void
    {
        config(['rx.fundeb_municipio_summary' => true]);

        $city = City::factory()->make([
            'id' => 9,
            'name' => 'Testópolis',
            'uf' => 'BA',
            'ibge_municipio' => '2913309',
        ]);

        $repo = Mockery::mock(DiscrepanciesRepository::class);
        $repo->shouldReceive('fundingImpactSnapshot')
            ->once()
            ->andReturn([
                'summary' => ['perda_estimada_anual' => 0.0],
                'funding_reference' => [
                    'vaa' => 5000.0,
                    'fonte_label' => 'teste',
                    'fonte' => 'config',
                ],
            ]);

        $out = RxFundebMunicipioSummary::build(
            $city,
            new IeducarFilterState('2026', null, null, null),
            100,
            95,
            $repo,
        );

        $this->assertTrue($out['available']);
        $this->assertSame(2026, $out['matriculas_ano']);
        $this->assertSame(2027, $out['exercicio_fundeb_ano']);
        $this->assertGreaterThan(0.0, (float) $out['previsao_anual']);
        $this->assertNotEmpty($out['previsao_anual_fmt']);
        $this->assertStringContainsString('fundeb', (string) $out['analytics_url']);
    }
}
