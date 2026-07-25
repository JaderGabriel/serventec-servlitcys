<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\MunicipalEmendaSnapshot;
use Illuminate\Support\Carbon;

class MunicipalEmendaSnapshotRepository
{
    private const UPSERT_CHUNK_SIZE = 200;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertBatch(?City $city, array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = $importedAt ?? now();
        $payload = [];

        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            $codigo = trim((string) ($row['codigo_emenda'] ?? ''));
            $ano = (int) ($row['ano'] ?? 0);
            if ($ibge === null || $codigo === '' || $ano < 2000) {
                continue;
            }

            $payload[] = [
                'city_id' => $city?->id ?? ($row['city_id'] ?? null),
                'ibge_municipio' => $ibge,
                'ano' => $ano,
                'codigo_emenda' => mb_substr($codigo, 0, 32),
                'numero_emenda' => self::nullableString($row['numero_emenda'] ?? null, 32),
                'tipo_emenda' => self::nullableString($row['tipo_emenda'] ?? null, 180),
                'autor' => self::nullableString($row['autor'] ?? null, 180),
                'localidade_do_gasto' => self::nullableString($row['localidade_do_gasto'] ?? null, 180),
                'funcao' => self::nullableString($row['funcao'] ?? null, 120),
                'subfuncao' => self::nullableString($row['subfuncao'] ?? null, 120),
                'valor_empenhado' => self::nullableFloat($row['valor_empenhado'] ?? null),
                'valor_liquidado' => self::nullableFloat($row['valor_liquidado'] ?? null),
                'valor_pago' => self::nullableFloat($row['valor_pago'] ?? null),
                'valor_resto_inscrito' => self::nullableFloat($row['valor_resto_inscrito'] ?? null),
                'valor_resto_cancelado' => self::nullableFloat($row['valor_resto_cancelado'] ?? null),
                'valor_resto_pago' => self::nullableFloat($row['valor_resto_pago'] ?? null),
                'documentos' => isset($row['documentos']) && is_array($row['documentos'])
                    ? json_encode($row['documentos'], JSON_UNESCAPED_UNICODE)
                    : null,
                'payload' => isset($row['payload']) && is_array($row['payload'])
                    ? json_encode($row['payload'], JSON_UNESCAPED_UNICODE)
                    : null,
                'fonte' => mb_substr((string) ($row['fonte'] ?? 'portal_transparencia'), 0, 40),
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        $uniqueKeys = ['ibge_municipio', 'ano', 'codigo_emenda'];
        $updateColumns = [
            'city_id',
            'numero_emenda',
            'tipo_emenda',
            'autor',
            'localidade_do_gasto',
            'funcao',
            'subfuncao',
            'valor_empenhado',
            'valor_liquidado',
            'valor_pago',
            'valor_resto_inscrito',
            'valor_resto_cancelado',
            'valor_resto_pago',
            'documentos',
            'payload',
            'fonte',
            'imported_at',
            'updated_at',
        ];

        foreach (array_chunk($payload, self::UPSERT_CHUNK_SIZE) as $chunk) {
            MunicipalEmendaSnapshot::query()->upsert($chunk, $uniqueKeys, $updateColumns);
        }

        return count($payload);
    }

    /**
     * @return list<MunicipalEmendaSnapshot>
     */
    public function forCityYear(City $city, int $year): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null || $year < 2000) {
            return [];
        }

        return MunicipalEmendaSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $year)
            ->orderByDesc('valor_pago')
            ->orderByDesc('valor_empenhado')
            ->orderBy('codigo_emenda')
            ->get()
            ->all();
    }

    public function countForCityYear(City $city, int $year): int
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null || $year < 2000) {
            return 0;
        }

        return MunicipalEmendaSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $year)
            ->count();
    }

    private static function nullableString(mixed $raw, int $max): ?string
    {
        $str = trim((string) ($raw ?? ''));
        if ($str === '') {
            return null;
        }

        return mb_substr($str, 0, $max);
    }

    private static function nullableFloat(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }
}
