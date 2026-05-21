<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Support\Brazil\MunicipalityMapCoordinates;

/**
 * Marcadores para o mapa de municípios implementados no Início.
 */
final class AdminHomeMunicipalityMap
{
    public function __construct(
        private readonly MunicipalityMapCoordinates $coordinates,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     uf: string,
     *     lat: float,
     *     lng: float,
     *     coord_source: string,
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
        $cities = City::query()
            ->orderBy('uf')
            ->orderBy('name')
            ->get();

        $byUf = $cities->groupBy(fn (City $c): string => strtoupper(trim((string) $c->uf)));

        return $cities
            ->map(function (City $city) use ($byUf): array {
                $uf = strtoupper(trim((string) $city->uf));
                $inUf = $byUf->get($uf, collect());
                $index = $inUf->search(fn (City $c): bool => (int) $c->id === (int) $city->id);
                $index = $index === false ? 0 : (int) $index;
                $total = $inUf->count();

                [$lat, $lng, $coordSource] = $this->coordinates->forCity($city, $index, $total);

                $hasSetup = $city->hasDataSetup();
                $isActive = (bool) $city->is_active;

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
                    'uf' => $uf,
                    'lat' => $lat,
                    'lng' => $lng,
                    'coord_source' => $coordSource,
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

    /**
     * @return array{total: int, on_map: int, by_status: array<string, int>}
     */
    public function summary(): array
    {
        $markers = $this->markers();
        $byStatus = [];

        foreach ($markers as $m) {
            $st = (string) ($m['status'] ?? 'inactive');
            $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        }

        return [
            'total' => count($markers),
            'on_map' => count($markers),
            'by_status' => $byStatus,
        ];
    }
}
