<?php

namespace App\Repositories;

use App\Models\MunicipalDemographySnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Carbon;

final class MunicipalDemographySnapshotRepository
{
    /**
     * @param  list<array{
     *   ibge_municipio: string,
     *   ano_referencia: int,
     *   populacao_4_17: ?int,
     *   populacao_total: ?int,
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

            MunicipalDemographySnapshot::query()->updateOrCreate(
                [
                    'ibge_municipio' => $ibge,
                    'ano_referencia' => $ano,
                    'fonte' => (string) ($row['fonte'] ?? 'ibge_sidra'),
                ],
                [
                    'populacao_4_17' => isset($row['populacao_4_17']) ? (int) $row['populacao_4_17'] : null,
                    'populacao_total' => isset($row['populacao_total']) ? (int) $row['populacao_total'] : null,
                    'metadados' => $row['metadados'] ?? null,
                    'imported_at' => $now,
                ],
            );
            $count++;
        }

        return $count;
    }
}
