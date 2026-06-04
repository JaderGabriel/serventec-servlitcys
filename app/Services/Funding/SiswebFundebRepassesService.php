<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Repasses FUNDEB via SISWEB (Transferências Constitucionais) ou export configurável.
 *
 * O portal APEX é interactivo; por omissão usa o espelho CKAN municipal (mesma base STN).
 *
 * @see https://sisweb.tesouro.gov.br/apex/f?p=2600:1
 */
final class SiswebFundebRepassesService
{
    public function __construct(
        private TesouroTransferenciasCsvService $tesouroCsv,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}
     */
    public function fetchForCityYear(City $city, int $year, int $timeout): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.sisweb', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('skipped', __('Fonte SISWEB desactivada.')),
            ];
        }

        $exportUrl = trim((string) ($cfg['export_url'] ?? ''));
        if ($exportUrl !== '' && SafeOutboundUrl::isAllowedHttpUrl($exportUrl)) {
            return $this->fetchFromExportUrl($city, $year, $exportUrl, $timeout);
        }

        if (! (bool) ($cfg['use_ckan_mirror'] ?? true)) {
            return [
                'rows' => [],
                'attempt' => $this->attempt(
                    'skipped',
                    __('Configure IEDUCAR_SISWEB_FUNDEB_EXPORT_URL ou active o espelho CKAN (IEDUCAR_SISWEB_USE_CKAN_MIRROR).'),
                ),
            ];
        }

        $rows = [];
        foreach ($this->tesouroCsv->fetchRowsForCityYear($city, $year, $timeout) as $row) {
            if ((string) ($row['programa_id'] ?? '') !== 'fundeb') {
                continue;
            }
            $row['fonte'] = 'sisweb_ckan';
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $meta['sisweb_portal'] = (string) ($cfg['portal_url'] ?? 'https://sisweb.tesouro.gov.br/apex/f?p=2600:1');
            $meta['mirror'] = 'tesouro_ckan_fundeb';
            $row['meta'] = $meta;
            $rows[] = $row;
        }

        if ($rows === []) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('empty', __('Sem FUNDEB municipal no espelho CKAN para :ano.', ['ano' => $year])),
            ];
        }

        return [
            'rows' => $rows,
            'attempt' => $this->attempt('ok', __('FUNDEB municipal via espelho CKAN (SISWEB/REPASSES).'), count($rows)),
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}
     */
    private function fetchFromExportUrl(City $city, int $year, string $url, int $timeout): array
    {
        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('IBGE inválido.')),
            ];
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Servlitcys/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Falha ao descarregar export SISWEB: :msg', ['msg' => $e->getMessage()])),
            ];
        }

        if (! $response->successful()) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Export SISWEB respondeu HTTP :code.', ['code' => $response->status()])),
            ];
        }

        $valor = $this->parseExportBody((string) $response->body(), $city, $year);
        if ($valor === null || $valor <= 0) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('empty', __('Export SISWEB sem valor FUNDEB para o município.')),
            ];
        }

        return [
            'rows' => [[
                'ibge_municipio' => $ibge,
                'ano' => $year,
                'fonte' => 'sisweb_export',
                'programa_id' => 'fundeb',
                'programa_label' => 'FUNDEB (export SISWEB)',
                'valor' => round($valor, 2),
                'meta' => ['export_url' => $url],
            ]],
            'attempt' => $this->attempt('ok', __('FUNDEB via export SISWEB configurado.'), 1),
        ];
    }

    private function parseExportBody(string $body, City $city, int $year): ?float
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $nameNeedle = Str::ascii(mb_strtolower(trim((string) $city->name)));
        $yearStr = (string) $year;

        foreach ($lines as $line) {
            $norm = Str::ascii(mb_strtolower($line));
            if ($nameNeedle !== '' && ! str_contains($norm, $nameNeedle)) {
                continue;
            }
            if (! str_contains($norm, 'fundeb') && ! str_contains($norm, 'fnde')) {
                continue;
            }
            if (preg_match_all('/[\d]{1,3}(?:\.[\d]{3})*,[\d]{2}|[\d]+\.[\d]{2}/', $line, $m)) {
                $last = end($m[0]);
                if (is_string($last)) {
                    return $this->parseBrNumber($last);
                }
            }
        }

        return null;
    }

    private function parseBrNumber(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $normalized = str_replace(['.', ' '], ['', ''], $raw);
        $normalized = str_replace(',', '.', $normalized);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @return array{source: string, status: string, message: string, rows: int, url: string}
     */
    private function attempt(string $status, string $message, int $rows = 0): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.sisweb', []);

        return [
            'source' => 'sisweb',
            'status' => $status,
            'message' => $message,
            'rows' => $rows,
            'url' => (string) ($cfg['portal_url'] ?? 'https://sisweb.tesouro.gov.br/apex/f?p=2600:1'),
        ];
    }
}
