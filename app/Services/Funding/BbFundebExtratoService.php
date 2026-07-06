<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use Illuminate\Support\Str;

/**
 * Créditos FUNDEB/FNDE a partir de extrato BB (download automático de CSV, arquivo em storage ou Open Finance futuro).
 *
 * @see https://demonstrativos.apps.bb.com.br/extrato
 * @see docs/BB_EXTRATO_OPEN_FINANCE.md
 */
final class BbFundebExtratoService
{
    public function __construct(
        private BbExtratoCsvFetcher $csvFetcher,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}
     */
    public function fetchForCityYear(City $city, int $year, int $timeout): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.bb_extrato', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('skipped', __('Extrato BB desactivado.')),
            ];
        }

        $csv = $this->csvFetcher->ensureForCityYear($city, $year);
        if (! ($csv['ok'] ?? false) || ! is_string($csv['path'] ?? null)) {
            $openFinanceNote = $this->openFinanceHint();

            return [
                'rows' => [],
                'attempt' => $this->attempt(
                    'skipped',
                    trim((string) ($csv['message'] ?? __('Extrato BB indisponível.')).' '.$openFinanceNote),
                ),
            ];
        }

        $body = @file_get_contents($csv['path']);
        if (! is_string($body) || strlen($body) < 32) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Arquivo de extrato BB ilegível.')),
            ];
        }

        $keywords = is_array($cfg['keywords'] ?? null) ? $cfg['keywords'] : ['fundeb', 'fnde', 'salario educacao', 'salário-educação'];
        $parsed = $this->parseMatchingCredits($body, $year, $keywords);
        $total = $parsed['total'] ?? null;
        $lancamentos = $parsed['lancamentos'] ?? [];

        if ($total === null || $total <= 0) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('empty', __('Nenhum crédito FUNDEB/FNDE no extrato para :ano.', ['ano' => $year])),
            ];
        }

        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);

        return [
            'rows' => [[
                'ibge_municipio' => $ibge,
                'ano' => $year,
                'fonte' => 'bb_extrato',
                'programa_id' => 'fundeb',
                'programa_label' => 'FUNDEB (extrato BB)',
                'valor' => round($total, 2),
                'meta' => [
                    'storage_path' => $csv['path'],
                    'downloaded' => (bool) ($csv['downloaded'] ?? false),
                    'source_url' => $csv['source_url'] ?? null,
                    'portal_url' => (string) ($cfg['portal_url'] ?? 'https://demonstrativos.apps.bb.com.br/extrato'),
                    'lancamentos' => $lancamentos,
                ],
            ]],
            'attempt' => $this->attempt(
                'ok',
                (bool) ($csv['downloaded'] ?? false)
                    ? __('Extrato BB descarregado e importado.')
                    : __('Extrato BB lido do cache/storage.'),
                1,
            ),
        ];
    }

    /**
     * @param  list<string>  $keywords
     * @return array{total: ?float, lancamentos: list<array{data: string, valor: float, historico: string}>}
     */
    private function parseMatchingCredits(string $body, int $year, array $keywords): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $total = 0.0;
        $matched = false;
        $yearStr = (string) $year;
        $lancamentos = [];

        foreach ($lines as $line) {
            if ($line === '' || (! str_contains($line, ';') && ! str_contains($line, ','))) {
                continue;
            }
            $norm = Str::ascii(mb_strtolower($line));
            $hit = false;
            foreach ($keywords as $kw) {
                $k = Str::ascii(mb_strtolower(trim((string) $kw)));
                if ($k !== '' && str_contains($norm, $k)) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                continue;
            }
            if (! str_contains($line, $yearStr) && ! preg_match('/\b'.$yearStr.'\b/', $norm)) {
                if (! preg_match('/\b\d{2}\/\d{2}\/'.$yearStr.'\b/', $line)) {
                    continue;
                }
            }

            $date = null;
            if (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $line, $dm)) {
                $date = $dm[1];
            }

            if (preg_match_all('/[\d]{1,3}(?:\.[\d]{3})*,[\d]{2}|[\d]+\.[\d]{2}/', $line, $m)) {
                $last = end($m[0]);
                if (is_string($last)) {
                    $val = $this->parseBrNumber($last);
                    if ($val !== null && $val > 0) {
                        if ($date === null) {
                            continue;
                        }
                        $total += $val;
                        $matched = true;
                        $historico = trim(preg_replace('/\b\d{2}\/\d{2}\/\d{4}\b/', '', $line) ?? $line);
                        $historico = trim(preg_replace('/[\d]{1,3}(?:\.[\d]{3})*,[\d]{2}/', '', $historico) ?? $historico);
                        $historico = trim(str_replace([';', ','], ' ', $historico));
                        if ($historico === '') {
                            $historico = __('Crédito FUNDEB/FNDE');
                        }
                        $lancamentos[] = [
                            'data' => $date,
                            'valor' => round($val, 2),
                            'historico' => mb_substr($historico, 0, 120),
                        ];
                    }
                }
            }
        }

        return [
            'total' => $matched ? round($total, 2) : null,
            'lancamentos' => $lancamentos,
        ];
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

    private function openFinanceHint(): string
    {
        $enabled = filter_var(config('ieducar.finance_realtime.bb_enabled', false), FILTER_VALIDATE_BOOL);
        if ($enabled) {
            return __('Open Finance: credenciais detectadas; consulta automática ainda não implementada — ver docs/BB_EXTRATO_OPEN_FINANCE.md.');
        }

        return '';
    }

    /**
     * @return array{source: string, status: string, message: string, rows: int, url: string}
     */
    private function attempt(string $status, string $message, int $rows = 0): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.bb_extrato', []);

        return [
            'source' => 'bb_extrato',
            'status' => $status,
            'message' => $message,
            'rows' => $rows,
            'url' => (string) ($cfg['portal_url'] ?? 'https://demonstrativos.apps.bb.com.br/extrato'),
        ];
    }
}
