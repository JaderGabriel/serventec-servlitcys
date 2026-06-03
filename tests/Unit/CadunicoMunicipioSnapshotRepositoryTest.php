<?php

namespace Tests\Unit;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CadunicoMunicipioSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_matrix_lists_snapshot_per_city_and_year(): void
    {
        $city = City::factory()->create([
            'ibge_municipio' => '2927408',
            'is_active' => true,
        ]);

        CadunicoMunicipioSnapshot::query()->create([
            'ibge_municipio' => '2927408',
            'ano_referencia' => 2024,
            'pessoas_cadastradas' => 1000,
            'familias_cadastradas' => 400,
            'criancas_4_5' => 50,
            'criancas_6_10' => 120,
            'criancas_11_14' => 80,
            'criancas_15_17' => 40,
            'populacao_escolar_estimada' => 290,
            'fonte' => 'cecad_csv',
            'imported_at' => now(),
        ]);

        $matrix = (new CadunicoMunicipioSnapshotRepository)->yearlyMatrix(2024, 2024);
        $row = collect($matrix['rows'])->firstWhere('city_id', $city->id);

        $this->assertNotNull($row);
        $cell = $row['years'][2024];
        $this->assertTrue($cell['has_snapshot']);
        $this->assertSame(290, $cell['pop_escolar']);
    }

    public function test_list_for_city_returns_snapshots_ordered_by_year(): void
    {
        $city = City::factory()->create(['ibge_municipio' => '2927408']);

        CadunicoMunicipioSnapshot::query()->create([
            'ibge_municipio' => '2927408',
            'ano_referencia' => 2023,
            'imported_at' => now(),
        ]);
        CadunicoMunicipioSnapshot::query()->create([
            'ibge_municipio' => '2927408',
            'ano_referencia' => 2024,
            'imported_at' => now(),
        ]);

        $list = (new CadunicoMunicipioSnapshotRepository)->listForCity($city);

        $this->assertCount(2, $list);
        $this->assertSame(2024, (int) $list->first()->ano_referencia);
    }
}
