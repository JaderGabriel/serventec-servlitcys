<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\CityDataConnection;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Support\Analytics\AnalyticsReportComparatives;
use App\Support\Dashboard\IeducarFilterState;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportComparativesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function build_retorna_estrutura_com_aviso_legal(): void
    {
        $city = new City(['id' => 1, 'name' => 'Itaparica', 'uf' => 'BA', 'ibge_municipio' => '2916104']);

        $refs = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $refs->shouldReceive('listForCity')->andReturn(collect());
        $refs->shouldReceive('findForCityYear')->andReturn(null);

        $fnde = Mockery::mock(FundebFndeReceitaCsvService::class);
        $fnde->shouldReceive('rowForIbge')->andReturn(null);
        $fnde->shouldReceive('loadYearIndex')->andReturn([]);

        $cityData = Mockery::mock(CityDataConnection::class);
        $cityData->shouldReceive('run')->andReturn(0);

        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $result = (new AnalyticsReportComparatives($refs, $fnde, $cityData))->build($city, $filters, [], []);

        $this->assertArrayHasKey('legal_notice', $result);
        $this->assertArrayHasKey('fundeb_years', $result);
        $this->assertArrayHasKey('state_participation', $result);
        $this->assertArrayHasKey('year_comparison_enriched', $result);
        $this->assertArrayHasKey('municipal_vs_state_enriched', $result);
    }
}
