<?php

namespace App\Repositories;

use App\Models\MunicipalBenefitSnapshot;

class MunicipalBenefitSnapshotRepository
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $uniqueKeys = ['ibge_municipio', 'programa', 'mes_ano'];
        $updateColumns = [
            'city_id',
            'quantidade_beneficiados',
            'valor',
            'data_referencia',
            'tipo_descricao',
            'payload',
            'fonte',
            'imported_at',
            'updated_at',
        ];

        $now = now();
        $prepared = [];
        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (is_array($payload)) {
                $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            }
            $prepared[] = array_merge($row, [
                'payload' => $payload,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        $upserted = 0;
        foreach (array_chunk($prepared, 100) as $chunk) {
            MunicipalBenefitSnapshot::query()->upsert($chunk, $uniqueKeys, $updateColumns);
            $upserted += count($chunk);
        }

        return $upserted;
    }

    /**
     * Último mês disponível por programa para o IBGE.
     *
     * @return array<string, MunicipalBenefitSnapshot>
     */
    public function latestByPrograma(string $ibge): array
    {
        $ibge = preg_replace('/\D/', '', $ibge) ?: '';
        if (strlen($ibge) !== 7) {
            return [];
        }

        $out = [];
        foreach (MunicipalBenefitSnapshot::PROGRAMAS as $programa) {
            $row = MunicipalBenefitSnapshot::query()
                ->forIbge($ibge)
                ->where('programa', $programa)
                ->orderByDesc('mes_ano')
                ->first();
            if ($row !== null) {
                $out[$programa] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<MunicipalBenefitSnapshot>
     */
    public function seriesForPrograma(string $ibge, string $programa, int $limit = 12): array
    {
        $ibge = preg_replace('/\D/', '', $ibge) ?: '';
        if (strlen($ibge) !== 7) {
            return [];
        }

        return MunicipalBenefitSnapshot::query()
            ->forIbge($ibge)
            ->where('programa', $programa)
            ->orderByDesc('mes_ano')
            ->limit(max(1, min(36, $limit)))
            ->get()
            ->all();
    }
}
