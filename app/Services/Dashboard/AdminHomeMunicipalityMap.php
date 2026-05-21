<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Support\Brazil\BrazilUfCentroids;

/**
 * Marcadores para o mapa de municípios implementados no Início.
 */
final class AdminHomeMunicipalityMap
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     uf: string,
     *     lat: float,
     *     lng: float,
     *     status: string,
     *     status_label: string,
     *     driver: string,
     *     ibge: ?string,
     *     summary: string,
     *     analytics_url: string
     * }>
     */
    public function markers(): array
    {
        return City::query()
            ->active()
            ->orderBy('uf')
            ->orderBy('name')
            ->get()
            ->map(function (City $city): array {
                $ready = $city->hasDataSetup();
                [$lat, $lng] = BrazilUfCentroids::latLng(
                    (string) $city->uf,
                    (int) $city->id,
                );

                $driver = $city->effectiveIeducarDriver() === City::DRIVER_PGSQL
                    ? 'PostgreSQL'
                    : 'MySQL';

                $status = $ready ? 'ready' : 'incomplete';
                $statusLabel = $ready
                    ? __('Base configurada')
                    : __('Credenciais incompletas');

                return [
                    'id' => (int) $city->id,
                    'name' => $city->name,
                    'uf' => (string) $city->uf,
                    'lat' => $lat,
                    'lng' => $lng,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'driver' => $driver,
                    'ibge' => filled($city->ibge_municipio) ? (string) $city->ibge_municipio : null,
                    'summary' => implode(' · ', array_filter([
                        $city->name.' / '.$city->uf,
                        $driver,
                        $statusLabel,
                        filled($city->ibge_municipio) ? 'IBGE '.$city->ibge_municipio : null,
                    ])),
                    'analytics_url' => route('dashboard.analytics', ['city_id' => $city->id]),
                ];
            })
            ->values()
            ->all();
    }
}
