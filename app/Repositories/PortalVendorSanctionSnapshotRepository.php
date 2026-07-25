<?php

namespace App\Repositories;

use App\Models\PortalVendorSanctionSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PortalVendorSanctionSnapshotRepository
{
    private const UPSERT_CHUNK_SIZE = 100;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertBatch(array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === [] || ! Schema::hasTable('portal_vendor_sanction_snapshots')) {
            return 0;
        }

        $now = $importedAt ?? now();
        $payload = [];
        $allowed = [
            PortalVendorSanctionSnapshot::FONTE_CEIS,
            PortalVendorSanctionSnapshot::FONTE_CNEP,
            PortalVendorSanctionSnapshot::FONTE_CEPIM,
        ];

        foreach ($rows as $row) {
            $fonte = strtolower(trim((string) ($row['fonte'] ?? '')));
            $cnpj = preg_replace('/\D/', '', (string) ($row['cnpj'] ?? '')) ?: '';
            $externalId = trim((string) ($row['external_id'] ?? ''));
            if (! in_array($fonte, $allowed, true) || strlen($cnpj) !== 14 || $externalId === '') {
                continue;
            }

            $payload[] = [
                'fonte' => $fonte,
                'cnpj' => $cnpj,
                'external_id' => mb_substr($externalId, 0, 64),
                'nome' => self::nullableString($row['nome'] ?? null, 180),
                'categoria' => self::nullableString($row['categoria'] ?? null, 180),
                'data_inicio' => self::nullableString($row['data_inicio'] ?? null, 20),
                'data_fim' => self::nullableString($row['data_fim'] ?? null, 20),
                'orgao' => self::nullableString($row['orgao'] ?? null, 180),
                'vendor_label' => self::nullableString($row['vendor_label'] ?? null, 120),
                'payload' => isset($row['payload']) && is_array($row['payload'])
                    ? json_encode($row['payload'], JSON_UNESCAPED_UNICODE)
                    : null,
                'fonte_api' => mb_substr((string) ($row['fonte_api'] ?? 'portal_transparencia'), 0, 40),
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        $updateColumns = [
            'cnpj',
            'nome',
            'categoria',
            'data_inicio',
            'data_fim',
            'orgao',
            'vendor_label',
            'payload',
            'fonte_api',
            'imported_at',
            'updated_at',
        ];

        foreach (array_chunk($payload, self::UPSERT_CHUNK_SIZE) as $chunk) {
            PortalVendorSanctionSnapshot::query()->upsert($chunk, ['fonte', 'external_id'], $updateColumns);
        }

        return count($payload);
    }

    /**
     * @param  list<string>  $cnpjs
     * @return array{
     *     sanctioned_cnpjs: int,
     *     records: int,
     *     by_fonte: array{ceis: int, cnep: int, cepim: int},
     *     samples: list<array{cnpj: string, label: ?string, fonte: string, categoria: ?string, nome: ?string}>
     * }
     */
    public function summaryForCnpjs(array $cnpjs, int $sampleLimit = 5): array
    {
        $empty = [
            'sanctioned_cnpjs' => 0,
            'records' => 0,
            'by_fonte' => [
                'ceis' => 0,
                'cnep' => 0,
                'cepim' => 0,
            ],
            'samples' => [],
        ];

        if (! Schema::hasTable('portal_vendor_sanction_snapshots')) {
            return $empty;
        }

        $normalized = [];
        foreach ($cnpjs as $cnpj) {
            $digits = preg_replace('/\D/', '', (string) $cnpj) ?: '';
            if (strlen($digits) === 14) {
                $normalized[$digits] = true;
            }
        }
        $list = array_keys($normalized);
        if ($list === []) {
            return $empty;
        }

        $rows = PortalVendorSanctionSnapshot::query()
            ->whereIn('cnpj', $list)
            ->orderByDesc('imported_at')
            ->orderBy('fonte')
            ->get(['cnpj', 'fonte', 'categoria', 'nome', 'vendor_label']);

        $byCnpj = [];
        $byFonte = ['ceis' => 0, 'cnep' => 0, 'cepim' => 0];
        $samples = [];
        foreach ($rows as $row) {
            $byCnpj[$row->cnpj] = true;
            $fonte = (string) $row->fonte;
            if (isset($byFonte[$fonte])) {
                $byFonte[$fonte]++;
            }
            if (count($samples) < $sampleLimit) {
                $samples[] = [
                    'cnpj' => (string) $row->cnpj,
                    'label' => $row->vendor_label,
                    'fonte' => strtoupper($fonte),
                    'categoria' => $row->categoria,
                    'nome' => $row->nome,
                ];
            }
        }

        return [
            'sanctioned_cnpjs' => count($byCnpj),
            'records' => $rows->count(),
            'by_fonte' => $byFonte,
            'samples' => $samples,
        ];
    }

    private static function nullableString(mixed $raw, int $max): ?string
    {
        $str = trim((string) ($raw ?? ''));
        if ($str === '') {
            return null;
        }

        return mb_substr($str, 0, $max);
    }
}
