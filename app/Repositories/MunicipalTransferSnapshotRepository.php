<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use Illuminate\Support\Carbon;

class MunicipalTransferSnapshotRepository
{
    /**
     * @param  list<array{
     *   ibge_municipio: string,
     *   ano: int,
     *   fonte: string,
     *   programa_id: string,
     *   programa_label?: ?string,
     *   valor: float,
     *   meta?: ?array<string, mixed>
     * }>  $rows
     */
    public function upsertBatch(?City $city, array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = $importedAt ?? now();
        $payload = [];
        foreach ($rows as $row) {
            $ibge = self::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            if ($ibge === null) {
                continue;
            }
            $ano = (int) ($row['ano'] ?? 0);
            if ($ano < 2000) {
                continue;
            }
            $payload[] = [
                'city_id' => $city?->id,
                'ibge_municipio' => $ibge,
                'ano' => $ano,
                'fonte' => (string) ($row['fonte'] ?? 'unknown'),
                'programa_id' => mb_substr((string) ($row['programa_id'] ?? 'geral'), 0, 64),
                'programa_label' => isset($row['programa_label']) ? mb_substr((string) $row['programa_label'], 0, 180) : null,
                'valor' => round((float) ($row['valor'] ?? 0), 2),
                'moeda' => 'BRL',
                'meta' => isset($row['meta']) && is_array($row['meta']) ? json_encode($row['meta']) : null,
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        MunicipalTransferSnapshot::query()->upsert(
            $payload,
            ['ibge_municipio', 'ano', 'fonte', 'programa_id'],
            ['city_id', 'programa_label', 'valor', 'meta', 'imported_at', 'updated_at']
        );

        return count($payload);
    }

    /**
     * @return list<MunicipalTransferSnapshot>
     */
    public function forCityYear(City $city, int $year, ?string $fonte = null): array
    {
        $ibge = self::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [];
        }

        $q = MunicipalTransferSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $year)
            ->orderBy('programa_id');

        if ($fonte !== null && $fonte !== '') {
            $q->where('fonte', $fonte);
        }

        return $q->get()->all();
    }

    /**
     * @return list<array{ano: int, valor: float, programa_id: string, programa_label: ?string, fonte: string}>
     */
    public function seriesByProgram(City $city, string $programaId, ?int $fromYear = null): array
    {
        $ibge = self::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [];
        }

        $q = MunicipalTransferSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('programa_id', $programaId)
            ->orderBy('ano');

        if ($fromYear !== null && $fromYear >= 2000) {
            $q->where('ano', '>=', $fromYear);
        }

        return $q->get()->map(static fn (MunicipalTransferSnapshot $r): array => [
            'ano' => (int) $r->ano,
            'valor' => (float) $r->valor,
            'programa_id' => (string) $r->programa_id,
            'programa_label' => $r->programa_label,
            'fonte' => (string) $r->fonte,
        ])->all();
    }

    public static function normalizeIbge(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === null || strlen($digits) < 6) {
            return null;
        }

        return str_pad($digits, 7, '0', STR_PAD_LEFT);
    }
}
