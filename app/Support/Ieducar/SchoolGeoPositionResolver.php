<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use Illuminate\Support\Facades\Schema;

/**
 * Critérios alinhados ao mapa de Unidades escolares (i-Educar + cache school_unit_geos).
 */
final class SchoolGeoPositionResolver
{
    public static function coordsAreUsable(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }
        if (abs($lat) < 0.01 && abs($lng) < 0.01) {
            return false;
        }

        return abs($lat) <= 90.0 && abs($lng) <= 180.0;
    }

    public static function schoolUnitGeoHasUsable(SchoolUnitGeo $geo): bool
    {
        if (self::coordsAreUsable(
            is_numeric($geo->lat) ? (float) $geo->lat : null,
            is_numeric($geo->lng) ? (float) $geo->lng : null,
        )) {
            return true;
        }

        return self::coordsAreUsable(
            is_numeric($geo->official_lat) ? (float) $geo->official_lat : null,
            is_numeric($geo->official_lng) ? (float) $geo->official_lng : null,
        );
    }

    /**
     * Posição persistida utilizável no mapa (i-Educar e/ou cache local), sem consulta INEP em tempo real.
     */
    public static function hasStoredMapPosition(?float $ieducarLat, ?float $ieducarLng, ?SchoolUnitGeo $geo): bool
    {
        if (self::coordsAreUsable($ieducarLat, $ieducarLng)) {
            return true;
        }

        return $geo instanceof SchoolUnitGeo && self::schoolUnitGeoHasUsable($geo);
    }

    public static function countCacheUnits(City $city, ?int $escolaFilterId = null): int
    {
        if (! self::cacheTableUsable($city)) {
            return 0;
        }

        try {
            $q = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->where('escola_id', '>', 0);
            if ($escolaFilterId !== null && $escolaFilterId > 0) {
                $q->where('escola_id', $escolaFilterId);
            }

            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function cacheTableUsable(City $city): bool
    {
        try {
            if (! Schema::hasTable((new SchoolUnitGeo)->getTable())) {
                return false;
            }

            return SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->where('escola_id', '>', 0)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<int>  $escolaIds
     * @return array<int, SchoolUnitGeo>
     */
    public static function geoByEscolaIds(City $city, array $escolaIds): array
    {
        $escolaIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $escolaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($escolaIds === [] || ! self::cacheTableUsable($city)) {
            return [];
        }

        try {
            return SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->whereIn('escola_id', $escolaIds)
                ->get()
                ->keyBy('escola_id')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Unidades em school_unit_geos sem coordenadas utilizáveis no mapa (modo rede/cache).
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function escolasCacheSemPosicaoUtilizavel(City $city, ?int $escolaFilterId = null): array
    {
        if (! self::cacheTableUsable($city)) {
            return [];
        }

        try {
            $q = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->where('escola_id', '>', 0);
            if ($escolaFilterId !== null && $escolaFilterId > 0) {
                $q->where('escola_id', $escolaFilterId);
            }

            $out = [];
            foreach ($q->orderBy('escola_id')->limit(200)->get() as $geo) {
                if (self::schoolUnitGeoHasUsable($geo)) {
                    continue;
                }
                $eid = (int) $geo->escola_id;
                $meta = is_array($geo->meta) ? $geo->meta : [];
                $nome = trim((string) ($meta['nome'] ?? ''));
                if ($nome === '') {
                    $nome = __('Unidade #:id', ['id' => $eid]);
                }
                $out[] = [
                    'escola_id' => (string) $eid,
                    'escola' => $nome,
                    'total' => 1,
                ];
                if (count($out) >= 50) {
                    break;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
