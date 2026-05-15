<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use Illuminate\Support\Collection;

class FundebMunicipioReferenceRepository
{
    /**
     * @return Collection<int, FundebMunicipioReference>
     */
    public function listForCity(City $city): Collection
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);

        return FundebMunicipioReference::query()
            ->where(function ($q) use ($city, $ibge) {
                $q->where('city_id', $city->id);
                if ($ibge !== null) {
                    $q->orWhere('ibge_municipio', $ibge);
                }
            })
            ->orderByDesc('ano')
            ->get();
    }

    public function findForCityYear(City $city, int $ano): ?FundebMunicipioReference
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return FundebMunicipioReference::query()
                ->where('city_id', $city->id)
                ->where('ano', $ano)
                ->first();
        }

        return FundebMunicipioReference::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $ano)
            ->first();
    }

    /**
     * @param  array{vaaf: float, vaat?: ?float, complementacao_vaar?: ?float, fonte?: string, notas?: ?string}  $data
     */
    public function upsert(City $city, int $ano, array $data): FundebMunicipioReference
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            throw new \InvalidArgumentException(__('Cidade sem código IBGE de município (7 dígitos).'));
        }

        $keys = ['ibge_municipio' => $ibge, 'ano' => $ano];

        return FundebMunicipioReference::query()->updateOrCreate($keys, [
            'city_id' => $city->id,
            'vaaf' => (float) $data['vaaf'],
            'vaat' => isset($data['vaat']) ? (float) $data['vaat'] : null,
            'complementacao_vaar' => isset($data['complementacao_vaar']) ? (float) $data['complementacao_vaar'] : null,
            'fonte' => trim((string) ($data['fonte'] ?? 'api_fnde')) ?: 'api_fnde',
            'notas' => isset($data['notas']) ? trim((string) $data['notas']) : null,
            'imported_at' => now(),
        ]);
    }

    public static function normalizeIbge(mixed $raw): ?string
    {
        $ibge = preg_replace('/\D/', '', (string) $raw);

        return strlen($ibge) === 7 ? $ibge : null;
    }

    public function attachCityIdsFromIbge(): int
    {
        $updated = 0;
        $cities = City::query()
            ->whereNotNull('ibge_municipio')
            ->get(['id', 'ibge_municipio']);

        foreach ($cities as $city) {
            $ibge = self::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $count = FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->whereNull('city_id')
                ->update(['city_id' => $city->id]);
            $updated += $count;
        }

        return $updated;
    }
}
