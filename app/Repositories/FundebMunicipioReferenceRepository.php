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

    /**
     * Matriz município × ano com VAAF e VAAT gravados em fundeb_municipio_references.
     *
     * @return array{
     *     year_from: int,
     *     year_to: int,
     *     years: list<int>,
     *     rows: list<array{
     *         city_id: int,
     *         name: string,
     *         uf: ?string,
     *         ibge: ?string,
     *         has_ibge: bool,
     *         is_active: bool,
     *         years: array<int, array{has_reference: bool, vaaf: ?float, vaat: ?float, fonte: ?string}>
     *     }>
     * }
     */
    public function yearlyMatrix(int $yearFrom, int $yearTo): array
    {
        $yearFrom = min($yearFrom, $yearTo);
        $yearTo = max($yearFrom, $yearTo);
        $years = range($yearFrom, $yearTo);

        $cities = City::query()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio', 'is_active']);

        $refsByCity = [];
        $refsByIbge = [];

        foreach (FundebMunicipioReference::query()
            ->whereBetween('ano', [$yearFrom, $yearTo])
            ->get(['city_id', 'ibge_municipio', 'ano', 'vaaf', 'vaat', 'fonte']) as $ref) {
            $ano = (int) $ref->ano;
            $payload = [
                'has_reference' => true,
                'vaaf' => (float) $ref->vaaf,
                'vaat' => $ref->vaat !== null ? (float) $ref->vaat : null,
                'fonte' => $ref->fonte !== null && trim((string) $ref->fonte) !== '' ? trim((string) $ref->fonte) : null,
            ];
            if ($ref->city_id) {
                $refsByCity[(int) $ref->city_id][$ano] = $payload;
            }
            if ($ref->ibge_municipio) {
                $refsByIbge[(string) $ref->ibge_municipio][$ano] = $payload;
            }
        }

        $rows = [];
        foreach ($cities as $city) {
            $ibge = self::normalizeIbge($city->ibge_municipio);
            $yearCells = [];
            foreach ($years as $ano) {
                $cell = $refsByCity[(int) $city->id][$ano] ?? ($ibge !== null ? ($refsByIbge[$ibge][$ano] ?? null) : null);
                $yearCells[$ano] = $cell ?? [
                    'has_reference' => false,
                    'vaaf' => null,
                    'vaat' => null,
                    'fonte' => null,
                ];
            }

            $rows[] = [
                'city_id' => (int) $city->id,
                'name' => $city->name,
                'uf' => $city->uf,
                'ibge' => $ibge,
                'has_ibge' => $ibge !== null,
                'is_active' => (bool) $city->is_active,
                'years' => $yearCells,
            ];
        }

        return [
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'years' => $years,
            'rows' => $rows,
        ];
    }
}
