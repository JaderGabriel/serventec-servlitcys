<?php

namespace App\Repositories;

use App\Models\PortalProcurementSnapshot;
use Illuminate\Support\Carbon;

class PortalProcurementSnapshotRepository
{
    private const UPSERT_CHUNK_SIZE = 200;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertBatch(array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = $importedAt ?? now();
        $payload = [];

        foreach ($rows as $row) {
            $tipo = trim((string) ($row['tipo'] ?? ''));
            $ano = (int) ($row['ano'] ?? 0);
            $codigoOrgao = preg_replace('/\D/', '', (string) ($row['codigo_orgao'] ?? '')) ?: '';
            $externalId = trim((string) ($row['external_id'] ?? ''));
            if (
                ! in_array($tipo, [PortalProcurementSnapshot::TIPO_CONTRATO, PortalProcurementSnapshot::TIPO_LICITACAO], true)
                || $ano < 2000
                || $codigoOrgao === ''
                || $externalId === ''
            ) {
                continue;
            }

            $payload[] = [
                'tipo' => $tipo,
                'ano' => $ano,
                'codigo_orgao' => mb_substr($codigoOrgao, 0, 10),
                'orgao_sigla' => self::nullableString($row['orgao_sigla'] ?? null, 20),
                'orgao_nome' => self::nullableString($row['orgao_nome'] ?? null, 180),
                'external_id' => mb_substr($externalId, 0, 64),
                'numero' => self::nullableString($row['numero'] ?? null, 64),
                'objeto' => self::nullableText($row['objeto'] ?? null),
                'situacao' => self::nullableString($row['situacao'] ?? null, 120),
                'modalidade' => self::nullableString($row['modalidade'] ?? null, 120),
                'valor' => self::nullableFloat($row['valor'] ?? null),
                'valor_final' => self::nullableFloat($row['valor_final'] ?? null),
                'data_assinatura' => self::nullableString($row['data_assinatura'] ?? null, 20),
                'data_inicio_vigencia' => self::nullableString($row['data_inicio_vigencia'] ?? null, 20),
                'data_fim_vigencia' => self::nullableString($row['data_fim_vigencia'] ?? null, 20),
                'data_publicacao' => self::nullableString($row['data_publicacao'] ?? null, 20),
                'fornecedor_cnpj' => self::nullableCnpj($row['fornecedor_cnpj'] ?? null),
                'fornecedor_nome' => self::nullableString($row['fornecedor_nome'] ?? null, 180),
                'ibge_municipio' => self::nullableIbge($row['ibge_municipio'] ?? null),
                'municipio_nome' => self::nullableString($row['municipio_nome'] ?? null, 120),
                'uf' => self::nullableUf($row['uf'] ?? null),
                'ug_codigo' => self::nullableString($row['ug_codigo'] ?? null, 20),
                'ug_nome' => self::nullableString($row['ug_nome'] ?? null, 180),
                'vendor_matched' => (bool) ($row['vendor_matched'] ?? false),
                'vendor_label' => self::nullableString($row['vendor_label'] ?? null, 120),
                'itens_software' => (bool) ($row['itens_software'] ?? false),
                'itens' => isset($row['itens']) && is_array($row['itens'])
                    ? json_encode($row['itens'], JSON_UNESCAPED_UNICODE)
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

        $uniqueKeys = ['tipo', 'ano', 'codigo_orgao', 'external_id'];
        $updateColumns = [
            'orgao_sigla',
            'orgao_nome',
            'numero',
            'objeto',
            'situacao',
            'modalidade',
            'valor',
            'valor_final',
            'data_assinatura',
            'data_inicio_vigencia',
            'data_fim_vigencia',
            'data_publicacao',
            'fornecedor_cnpj',
            'fornecedor_nome',
            'ibge_municipio',
            'municipio_nome',
            'uf',
            'ug_codigo',
            'ug_nome',
            'vendor_matched',
            'vendor_label',
            'itens_software',
            'itens',
            'payload',
            'fonte',
            'imported_at',
            'updated_at',
        ];

        foreach (array_chunk($payload, self::UPSERT_CHUNK_SIZE) as $chunk) {
            PortalProcurementSnapshot::query()->upsert($chunk, $uniqueKeys, $updateColumns);
        }

        return count($payload);
    }

    /**
     * @return list<PortalProcurementSnapshot>
     */
    public function forOrgaoYear(string $codigoOrgao, int $year, ?string $tipo = null): array
    {
        $codigoOrgao = preg_replace('/\D/', '', $codigoOrgao) ?: '';
        if ($codigoOrgao === '' || $year < 2000) {
            return [];
        }

        $q = PortalProcurementSnapshot::query()
            ->where('codigo_orgao', $codigoOrgao)
            ->where('ano', $year)
            ->orderByDesc('valor')
            ->orderBy('external_id');

        if ($tipo !== null) {
            $q->where('tipo', $tipo);
        }

        return $q->get()->all();
    }

    public function countVendorMatched(int $year): int
    {
        if ($year < 2000) {
            return 0;
        }

        return PortalProcurementSnapshot::query()
            ->where('ano', $year)
            ->where('vendor_matched', true)
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

    private static function nullableText(mixed $raw): ?string
    {
        $str = trim((string) ($raw ?? ''));

        return $str === '' ? null : $str;
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

    private static function nullableCnpj(mixed $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($raw ?? '')) ?: '';
        if (strlen($digits) !== 14) {
            return null;
        }

        return $digits;
    }

    private static function nullableIbge(mixed $raw): ?string
    {
        return FundebMunicipioReferenceRepository::normalizeIbge((string) ($raw ?? ''));
    }

    private static function nullableUf(mixed $raw): ?string
    {
        $uf = strtoupper(trim((string) ($raw ?? '')));
        if (strlen($uf) !== 2 || ! ctype_alpha($uf)) {
            return null;
        }

        return $uf;
    }
}
