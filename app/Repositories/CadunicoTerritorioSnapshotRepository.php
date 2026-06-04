<?php

namespace App\Repositories;

use App\Models\CadunicoTerritorioSnapshot;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Collection;

class CadunicoTerritorioSnapshotRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $ibge, int $ano, string $codigo, array $data): CadunicoTerritorioSnapshot
    {
        return CadunicoTerritorioSnapshot::query()->updateOrCreate(
            [
                'ibge_municipio' => $ibge,
                'ano_referencia' => $ano,
                'territorio_codigo' => $codigo,
            ],
            array_merge($data, ['imported_at' => now()]),
        );
    }

    /**
     * @return Collection<int, CadunicoTerritorioSnapshot>
     */
    public function forCityYear(City $city, int $year): Collection
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return collect();
        }

        return CadunicoTerritorioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', $year)
            ->orderByDesc('criancas_4_17')
            ->get();
    }

    public function countForCityYear(City $city, int $year): int
    {
        return $this->forCityYear($city, $year)->count();
    }
}
