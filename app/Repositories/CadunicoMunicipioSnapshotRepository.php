<?php

namespace App\Repositories;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;

class CadunicoMunicipioSnapshotRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $ibge, int $ano, array $data): CadunicoMunicipioSnapshot
    {
        $keys = ['ibge_municipio' => $ibge, 'ano_referencia' => $ano];

        return CadunicoMunicipioSnapshot::query()->updateOrCreate($keys, array_merge($data, [
            'imported_at' => now(),
        ]));
    }

    public function findForCityYear(?City $city, int $year): ?CadunicoMunicipioSnapshot
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city?->ibge_municipio);
        if ($ibge === null) {
            return null;
        }

        $row = CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', $year)
            ->first();

        if ($row !== null) {
            return $row;
        }

        return CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', '<=', $year)
            ->orderByDesc('ano_referencia')
            ->first();
    }
}
