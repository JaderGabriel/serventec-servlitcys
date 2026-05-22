<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Fundeb\FundebMatrixCellPresentation;
use App\Support\Fundeb\FundebReferenceSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMunicipioReferenceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function yearly_matrix_lists_vaaf_vaat_e_tipo_por_ano(): void
    {
        $city = City::factory()->create([
            'name' => 'Itamari',
            'uf' => 'BA',
            'ibge_municipio' => '2910800',
            'is_active' => true,
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => $city->id,
            'ibge_municipio' => '2910800',
            'ano' => 2024,
            'vaaf' => 5100.50,
            'vaat' => 4800.00,
            'fonte' => 'api_ckan_fnde',
            'imported_at' => now(),
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => $city->id,
            'ibge_municipio' => '2910800',
            'ano' => 2023,
            'vaaf' => 5559.73,
            'vaat' => null,
            'fonte' => FundebReferenceSource::FONTE_NACIONAL,
            'imported_at' => now(),
        ]);

        $matrix = (new FundebMunicipioReferenceRepository)->yearlyMatrix(2023, 2024);

        $this->assertSame([2023, 2024], $matrix['years']);
        $this->assertCount(4, $matrix['legend']);

        $row = $matrix['rows'][0];
        $this->assertSame(FundebMatrixCellPresentation::KIND_CONSOLIDATED, $row['years'][2024]['display_kind']);
        $this->assertSame(FundebMatrixCellPresentation::KIND_NATIONAL, $row['years'][2023]['display_kind']);
        $this->assertFalse($row['years'][2022]['has_reference'] ?? true);
    }

    #[Test]
    public function default_matrix_year_range_usa_tres_anos_ate_vigente(): void
    {
        $range = FundebMunicipioReferenceRepository::defaultMatrixYearRange();

        $this->assertSame($range['anchor'], $range['to']);
        $this->assertSame($range['anchor'] - 2, $range['from']);
    }
}
