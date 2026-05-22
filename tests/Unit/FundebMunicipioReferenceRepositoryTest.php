<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMunicipioReferenceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function yearly_matrix_lists_vaaf_and_vaat_per_city_and_year(): void
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
            'fonte' => 'api_fnde',
            'imported_at' => now(),
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => $city->id,
            'ibge_municipio' => '2910800',
            'ano' => 2023,
            'vaaf' => 4900.00,
            'vaat' => null,
            'fonte' => 'csv_fnde',
            'imported_at' => now(),
        ]);

        $repo = new FundebMunicipioReferenceRepository;
        $matrix = $repo->yearlyMatrix(2022, 2026);

        $this->assertSame([2022, 2023, 2024, 2025, 2026], $matrix['years']);
        $this->assertCount(1, $matrix['rows']);

        $row = $matrix['rows'][0];
        $this->assertSame('Itamari', $row['name']);
        $this->assertSame('2910800', $row['ibge']);
        $this->assertTrue($row['years'][2024]['has_reference']);
        $this->assertSame(5100.50, $row['years'][2024]['vaaf']);
        $this->assertSame(4800.00, $row['years'][2024]['vaat']);
        $this->assertFalse($row['years'][2022]['has_reference']);
        $this->assertTrue($row['years'][2023]['has_reference']);
        $this->assertNull($row['years'][2023]['vaat']);
    }

    #[Test]
    public function yearly_matrix_resolves_reference_by_ibge_when_city_id_missing(): void
    {
        $city = City::factory()->create([
            'ibge_municipio' => '3550308',
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => null,
            'ibge_municipio' => '3550308',
            'ano' => 2025,
            'vaaf' => 6000.00,
            'vaat' => 5500.00,
            'fonte' => 'api_fnde',
            'imported_at' => now(),
        ]);

        $matrix = (new FundebMunicipioReferenceRepository)->yearlyMatrix(2025, 2025);
        $row = collect($matrix['rows'])->firstWhere('city_id', $city->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['years'][2025]['has_reference']);
        $this->assertSame(6000.00, $row['years'][2025]['vaaf']);
    }
}
