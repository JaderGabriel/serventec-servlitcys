<?php

namespace App\Repositories;

use App\Models\MunicipalAreaSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Carbon;

final class MunicipalAreaSnapshotRepository
{
    /**
     * @param  list<array{
     *   ibge_municipio: string,
     *   ano_referencia: int,
     *   area_km2: ?float,
     *   fonte?: string,
     *   metadados?: ?array<string, mixed>
     * }>  $rows
     */
    public function upsertBatch(array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = $importedAt ?? now();
        $count = 0;

        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            if ($ibge === null) {
                continue;
            }

            $ano = (int) ($row['ano_referencia'] ?? 0);
            if ($ano < 2000) {
                continue;
            }

            $area = isset($row['area_km2']) ? (float) $row['area_km2'] : null;
            if ($area !== null && $area <= 0) {
                $area = null;
            }

            MunicipalAreaSnapshot::query()->updateOrCreate(
                [
                    'ibge_municipio' => $ibge,
                    'ano_referencia' => $ano,
                    'fonte' => (string) ($row['fonte'] ?? 'ibge_malha'),
                ],
                [
                    'area_km2' => $area,
                    'metadados' => $row['metadados'] ?? null,
                    'imported_at' => $now,
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, array{area_km2: ?float, ano: int}>
     */
    public function latestByIbge(?string $ibgePrefix = null): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('municipal_area_snapshots')) {
            return [];
        }

        $query = MunicipalAreaSnapshot::query()
            ->orderByDesc('ano_referencia')
            ->orderByDesc('imported_at');
        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $query->where('ibge_municipio', 'like', $ibgePrefix.'%');
        }

        $out = [];
        foreach ($query->get(['ibge_municipio', 'ano_referencia', 'area_km2']) as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null || isset($out[$ibge])) {
                continue;
            }
            $out[$ibge] = [
                'area_km2' => $row->area_km2 !== null ? (float) $row->area_km2 : null,
                'ano' => (int) $row->ano_referencia,
            ];
        }

        return $out;
    }
}
