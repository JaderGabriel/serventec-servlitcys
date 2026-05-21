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
     *     is_active: bool,
     *     implemented_at: ?string,
     *     implemented_at_label: ?string,
     *     school_years_url: string,
     *     analytics_url: string
     * }>
     */
    public function markers(): array
    {
        return City::query()
            ->orderBy('uf')
            ->orderBy('name')
            ->get()
            ->map(function (City $city): array {
                $hasSetup = $city->hasDataSetup();
                $isActive = (bool) $city->is_active;
                [$lat, $lng] = BrazilUfCentroids::latLng(
                    (string) $city->uf,
                    (int) $city->id,
                );

                $driver = $city->effectiveIeducarDriver() === City::DRIVER_PGSQL
                    ? 'PostgreSQL'
                    : 'MySQL';

                $status = match (true) {
                    $isActive && $hasSetup => 'ready',
                    $isActive && ! $hasSetup => 'incomplete',
                    ! $isActive && $hasSetup => 'inactive_setup',
                    default => 'inactive',
                };

                $statusLabel = match ($status) {
                    'ready' => __('Activo · base configurada'),
                    'incomplete' => __('Activo · credenciais incompletas'),
                    'inactive_setup' => __('Inactivo · base configurada'),
                    default => __('Inactivo'),
                };

                $implementedAt = $city->created_at;

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
                    'is_active' => $isActive,
                    'implemented_at' => $implementedAt?->toIso8601String(),
                    'implemented_at_label' => $implementedAt?->format('d/m/Y'),
                    'school_years_url' => route('dashboard.municipality-map.school-years', $city),
                    'analytics_url' => route('dashboard.analytics', ['city_id' => $city->id]),
                ];
            })
            ->values()
            ->all();
    }
}
