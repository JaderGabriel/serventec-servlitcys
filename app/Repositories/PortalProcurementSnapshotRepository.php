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

    /**
     * Mercado municipal por IBGE — licitações e contratos com município preenchido (HOR-08e/B4).
     * Contratos sem IBGE (só órgão federal) não entram aqui.
     *
     * @return array<string, array{
     *     licitacoes: int,
     *     licitacoes_software: int,
     *     valor_total: float,
     *     samples: list<array{
     *         numero: ?string,
     *         objeto: ?string,
     *         situacao: ?string,
     *         modalidade: ?string,
     *         valor: ?float,
     *         valor_final: ?float,
     *         data_publicacao: ?string,
     *         data_inicio_vigencia: ?string,
     *         data_fim_vigencia: ?string,
     *         orgao_sigla: ?string,
     *         fornecedor_cnpj: ?string,
     *         fornecedor_nome: ?string,
     *         vendor_matched: bool,
     *         vendor_label: ?string,
     *         itens_software: bool
     *     }>,
     *     imported_at: ?string
     * }>
     */
    public function licitacoesMarketByIbge(int $year, ?string $ibgePrefix = null, int $sampleLimit = 5): array
    {
        if ($year < 2000 || ! \Illuminate\Support\Facades\Schema::hasTable('portal_procurement_snapshots')) {
            return [];
        }

        $years = array_values(array_unique([$year, $year - 1]));
        $query = PortalProcurementSnapshot::query()
            ->whereIn('ano', $years)
            ->whereNotNull('ibge_municipio')
            ->where('ibge_municipio', '!=', '');

        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $query->where('ibge_municipio', 'like', $ibgePrefix.'%');
        }

        $rows = $query
            ->orderByDesc('vendor_matched')
            ->orderByDesc('itens_software')
            ->orderByDesc('data_publicacao')
            ->orderByDesc('valor')
            ->orderBy('external_id')
            ->get([
                'tipo',
                'ibge_municipio',
                'numero',
                'objeto',
                'situacao',
                'modalidade',
                'valor',
                'valor_final',
                'data_publicacao',
                'data_inicio_vigencia',
                'data_fim_vigencia',
                'orgao_sigla',
                'fornecedor_cnpj',
                'fornecedor_nome',
                'vendor_matched',
                'vendor_label',
                'itens_software',
                'imported_at',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            if (! isset($out[$ibge])) {
                $out[$ibge] = [
                    'licitacoes' => 0,
                    'licitacoes_software' => 0,
                    'valor_total' => 0.0,
                    'samples' => [],
                    'imported_at' => null,
                ];
            }

            $isLicitacao = ($row->tipo ?? '') === PortalProcurementSnapshot::TIPO_LICITACAO;
            if ($isLicitacao) {
                $out[$ibge]['licitacoes']++;
                if ($row->itens_software) {
                    $out[$ibge]['licitacoes_software']++;
                }
            } elseif ($row->itens_software || $row->vendor_matched) {
                // Contrato municipal com sinal de software conta no proxy de timing.
                $out[$ibge]['licitacoes_software']++;
            }

            if ($row->valor !== null) {
                $out[$ibge]['valor_total'] += (float) $row->valor;
            }
            $imported = $row->imported_at?->toIso8601String();
            if ($imported !== null && (
                $out[$ibge]['imported_at'] === null || strcmp($imported, (string) $out[$ibge]['imported_at']) > 0
            )) {
                $out[$ibge]['imported_at'] = $imported;
            }
            if (count($out[$ibge]['samples']) < $sampleLimit) {
                $out[$ibge]['samples'][] = [
                    'numero' => $row->numero,
                    'objeto' => $row->objeto !== null ? mb_substr((string) $row->objeto, 0, 160) : null,
                    'situacao' => $row->situacao,
                    'modalidade' => $row->modalidade,
                    'valor' => $row->valor !== null ? (float) $row->valor : null,
                    'valor_final' => $row->valor_final !== null ? (float) $row->valor_final : null,
                    'data_publicacao' => $row->data_publicacao,
                    'data_inicio_vigencia' => $row->data_inicio_vigencia,
                    'data_fim_vigencia' => $row->data_fim_vigencia,
                    'orgao_sigla' => $row->orgao_sigla,
                    'fornecedor_cnpj' => $row->fornecedor_cnpj,
                    'fornecedor_nome' => $row->fornecedor_nome,
                    'vendor_matched' => (bool) $row->vendor_matched,
                    'vendor_label' => $row->vendor_label,
                    'itens_software' => (bool) $row->itens_software,
                ];
            }
        }

        foreach ($out as &$agg) {
            $agg['valor_total'] = round($agg['valor_total'], 2);
        }
        unset($agg);

        return $out;
    }

    /**
     * Contratos com CNPJ curado / itens software — nível órgão (sem IBGE municipal).
     *
     * @return array{
     *     vendor_matched: int,
     *     itens_software: int,
     *     top_vendors: list<array{label: string, count: int, valor: float}>
     * }
     */
    public function nationalVendorMarketSummary(int $year, int $top = 5): array
    {
        if ($year < 2000 || ! \Illuminate\Support\Facades\Schema::hasTable('portal_procurement_snapshots')) {
            return [
                'vendor_matched' => 0,
                'itens_software' => 0,
                'top_vendors' => [],
            ];
        }

        $years = array_values(array_unique([$year, $year - 1]));
        $base = PortalProcurementSnapshot::query()
            ->contratos()
            ->whereIn('ano', $years);

        $vendorMatched = (clone $base)->where('vendor_matched', true)->count();
        $itensSoftware = (clone $base)->where('itens_software', true)->count();

        $vendorRows = (clone $base)
            ->where('vendor_matched', true)
            ->whereNotNull('vendor_label')
            ->where('vendor_label', '!=', '')
            ->selectRaw('vendor_label as label, count(*) as c, coalesce(sum(valor), 0) as v')
            ->groupBy('vendor_label')
            ->orderByDesc('c')
            ->limit(max(1, $top))
            ->get();

        $topVendors = [];
        foreach ($vendorRows as $row) {
            $topVendors[] = [
                'label' => (string) $row->label,
                'count' => (int) $row->c,
                'valor' => round((float) $row->v, 2),
            ];
        }

        return [
            'vendor_matched' => $vendorMatched,
            'itens_software' => $itensSoftware,
            'top_vendors' => $topVendors,
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
