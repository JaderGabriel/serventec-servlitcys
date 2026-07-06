<?php

namespace App\Services\Horizonte;

use App\Models\MunicipalFiscalSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\IbgeUfFromCode;
use App\Support\Horizonte\HorizonteSiconfiSyncProgress;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Horizonte\SiconfiApiClient;
use App\Support\Horizonte\SiconfiRreoParser;
use Illuminate\Support\Facades\Log;

/** Importa indicadores fiscais municipais via API SICONFI (RREO). */
final class HorizonteSiconfiMunicipalSyncService
{
    public function __construct(
        private readonly SiconfiApiClient $client,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message: string,
     *     imported?: int,
     *     partial?: bool,
     *     complete?: bool,
     *     skipped?: bool,
     *     pending?: int,
     *     total?: int,
     *     items?: list<array<string, mixed>>,
     *     failed?: list<array<string, mixed>>,
     *     debug_lines?: list<string>,
     *     imported_lines?: list<string>,
     *     failed_lines?: list<string>,
     *     ufs?: list<string>,
     *     remaining_ufs?: list<string>,
     *     siconfi_done?: int,
     *     siconfi_total?: int
     * }
     */
    public function syncBatch(array $options = []): array
    {
        if (! filter_var(config('horizonte.siconfi.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SICONFI desactivado (HORIZONTE_SICONFI_ENABLED=false).'),
            ];
        }

        $year = (int) ($options['year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        $period = max(1, min(6, (int) ($options['period'] ?? config('horizonte.siconfi.period', 6))));
        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $perStep = max(1, min(50, (int) ($options['municipios_per_step'] ?? config('horizonte.siconfi.municipios_per_step', 8))));
        $ibgeCodes = $this->normalizeIbgeCodes($options['ibge_codes'] ?? null);
        $nationalScope = $scopedUf === null && $ibgeCodes === [];
        $reset = (bool) ($options['reset'] ?? false);
        $continue = (bool) ($options['continue'] ?? false);
        $refresh = (bool) ($options['refresh'] ?? false);
        $byUf = (bool) ($options['by_uf'] ?? false);

        if ($byUf) {
            return $this->syncByUf($options, $year, $period, $scopedUf, $reset, $refresh);
        }

        if ($reset) {
            $deleted = $this->handleReset($year, $period, $scopedUf, $nationalScope);
            $refresh = true;
            Log::info('horizonte.siconfi_sync_reset', [
                'year' => $year,
                'period' => $period,
                'uf' => $scopedUf,
                'national' => $nationalScope,
                'snapshots_deleted' => $deleted,
            ]);
        }

        if ($continue && $nationalScope && ! HorizonteSiconfiSyncProgress::isActive($year, $period)) {
            return [
                'success' => true,
                'skipped' => true,
                'complete' => HorizonteSiconfiSyncProgress::isComplete($year, $period),
                'message' => HorizonteSiconfiSyncProgress::isComplete($year, $period)
                    ? __('SICONFI: sincronização nacional já concluída para :ano (período :periodo).', [
                        'ano' => (string) $year,
                        'periodo' => (string) $period,
                    ])
                    : __('SICONFI: nenhuma sincronização nacional activa — use --reset para iniciar.'),
            ];
        }

        if ($nationalScope && $reset) {
            HorizonteSiconfiSyncProgress::start($year, $period);
        }

        $batch = $this->resolveIbgeBatch($scopedUf, $perStep, $year, $period, $refresh, $ibgeCodes);
        $codes = $batch['codes'];
        $pendingAfter = $batch['pending_after'];
        $total = $batch['total'];

        if ($codes === []) {
            if ($nationalScope && $pendingAfter === 0) {
                HorizonteSiconfiSyncProgress::markComplete($year, $period);
            }

            return [
                'success' => true,
                'message' => __('SICONFI: nenhum município pendente para o lote.'),
                'imported' => 0,
                'partial' => false,
                'complete' => $nationalScope && $pendingAfter === 0,
                'pending' => $pendingAfter,
                'total' => $total,
            ];
        }

        $imported = 0;
        $importedItems = [];
        $failedItems = [];

        foreach ($codes as $ibge) {
            $label = $this->municipalityLabel($ibge);
            $fetch = $this->fetchAndParse((int) $ibge, $year, $period);
            if ($fetch['parsed'] === null) {
                $reason = (string) ($fetch['reason'] ?? 'empty_api');
                $failedItems[] = array_merge($label, [
                    'ibge' => $ibge,
                    'reason' => $reason,
                ]);
                Log::debug('horizonte.siconfi_municipio_skipped', [
                    'ibge' => $ibge,
                    'name' => $label['name'],
                    'uf' => $label['uf'],
                    'year' => $year,
                    'period' => $period,
                    'reason' => $reason,
                ]);

                continue;
            }

            $parsed = $fetch['parsed'];

            MunicipalFiscalSnapshot::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $year],
                array_merge($parsed, [
                    'periodo' => $period,
                    'fonte' => 'siconfi_rreo',
                    'imported_at' => now(),
                ]),
            );
            $imported++;

            $item = array_merge($label, [
                'ibge' => $ibge,
                'receita_corrente_liquida' => $parsed['receita_corrente_liquida'] ?? null,
                'despesa_educacao_liquidada' => $parsed['despesa_educacao_liquidada'] ?? null,
                'pct_educacao_receita_corrente' => $parsed['pct_educacao_receita_corrente'] ?? null,
                'pct_minimo_constitucional' => $parsed['pct_minimo_constitucional'] ?? null,
                'fiscal_capacity_score' => $parsed['fiscal_capacity_score'] ?? null,
                'liquidity_ratio' => $parsed['liquidity_ratio'] ?? null,
            ]);
            $importedItems[] = $item;

            Log::info('horizonte.siconfi_municipio_imported', [
                'ibge' => $ibge,
                'name' => $label['name'],
                'uf' => $label['uf'],
                'year' => $year,
                'period' => $period,
                'receita_corrente_liquida' => $item['receita_corrente_liquida'],
                'pct_educacao_receita_corrente' => $item['pct_educacao_receita_corrente'],
                'fiscal_capacity_score' => $item['fiscal_capacity_score'],
            ]);
        }

        $partial = $pendingAfter > 0;
        $complete = $nationalScope && ! $partial;
        $importedLines = array_map(fn (array $item): string => $this->formatImportedLine($item), $importedItems);
        $failedLines = array_map(fn (array $item): string => $this->formatFailedLine($item), $failedItems);
        $debugLines = array_merge($importedLines, $failedLines);

        if ($complete) {
            HorizonteSiconfiSyncProgress::markComplete($year, $period);
        }

        Log::info('horizonte.siconfi_sync', [
            'year' => $year,
            'period' => $period,
            'uf' => $scopedUf,
            'imported' => $imported,
            'failed' => count($failedItems),
            'attempted' => count($codes),
            'partial' => $partial,
            'complete' => $complete,
            'pending' => $pendingAfter,
            'total' => $total,
            'items' => $importedItems,
            'failed_items' => $failedItems,
        ]);

        return [
            'success' => $imported > 0 || ! $partial,
            'message' => __('SICONFI: :n município(s) atualizados (ano :ano, período :periodo).', [
                'n' => (string) $imported,
                'ano' => (string) $year,
                'periodo' => (string) $period,
            ]),
            'imported' => $imported,
            'partial' => $partial,
            'complete' => $complete,
            'pending' => $pendingAfter,
            'total' => $total,
            'items' => $importedItems,
            'failed' => $failedItems,
            'debug_lines' => $debugLines,
            'imported_lines' => $importedLines,
            'failed_lines' => $failedLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function syncByUf(
        array $options,
        int $year,
        int $period,
        ?string $scopedUf,
        bool $reset,
        bool $refresh,
    ): array {
        $ufsPerStep = max(1, min(3, (int) ($options['ufs_per_step'] ?? config('horizonte.siconfi_sync.ufs_per_step', 1))));
        $totalUfs = count(IbgeMunicipalityCatalog::brazilianUfs());

        if ($reset) {
            if ($scopedUf !== null) {
                HorizonteSiconfiSyncProgress::unmarkUfs([$scopedUf], $year, $period);
                if ((bool) ($options['purge_snapshots'] ?? false)) {
                    $this->handleReset($year, $period, $scopedUf, false);
                }
            } else {
                HorizonteSiconfiSyncProgress::resetUfs($year, $period);
                HorizonteSiconfiSyncProgress::start($year, $period);
                if ((bool) ($options['purge_snapshots'] ?? false)) {
                    $this->handleReset($year, $period, null, true);
                }
            }
            Log::info('horizonte.siconfi_sync_reset', [
                'year' => $year,
                'period' => $period,
                'uf' => $scopedUf,
                'mode' => 'by_uf',
                'purge_snapshots' => (bool) ($options['purge_snapshots'] ?? false),
            ]);
        }

        if ($scopedUf === null && ! HorizonteSiconfiSyncProgress::isComplete($year, $period) && ! HorizonteSiconfiSyncProgress::isActive($year, $period)) {
            HorizonteSiconfiSyncProgress::start($year, $period);
        }

        $targetUfs = $scopedUf !== null
            ? [$scopedUf]
            : array_slice(HorizonteSiconfiSyncProgress::remainingUfs($year, $period), 0, $ufsPerStep);

        if ($targetUfs === []) {
            return [
                'success' => true,
                'message' => __('SICONFI: todas as UFs já sincronizadas para :ano (período :periodo).', [
                    'ano' => (string) $year,
                    'periodo' => (string) $period,
                ]),
                'imported' => 0,
                'partial' => false,
                'complete' => true,
                'siconfi_done' => $totalUfs,
                'siconfi_total' => $totalUfs,
                'remaining_ufs' => [],
            ];
        }

        $imported = 0;
        $importedItems = [];
        $failedItems = [];
        $processedUfs = [];

        foreach ($targetUfs as $uf) {
            $codes = $this->resolvePendingCodesForUf($uf, $year, $period, $refresh);
            $batchResult = $this->processIbgeCodes($codes, $year, $period);
            $imported += (int) ($batchResult['imported'] ?? 0);
            $importedItems = array_merge($importedItems, $batchResult['items'] ?? []);
            $failedItems = array_merge($failedItems, $batchResult['failed'] ?? []);
            $processedUfs[] = $uf;

            if ($scopedUf === null) {
                HorizonteSiconfiSyncProgress::markUfsDone([$uf], $year, $period);
            }
        }

        $remainingUfs = $scopedUf !== null ? [] : HorizonteSiconfiSyncProgress::remainingUfs($year, $period);
        $doneUfs = $totalUfs - count($remainingUfs);
        $partial = $scopedUf === null && $remainingUfs !== [];
        $complete = ! $partial;
        $importedLines = array_map(fn (array $item): string => $this->formatImportedLine($item), $importedItems);
        $failedLines = array_map(fn (array $item): string => $this->formatFailedLine($item), $failedItems);

        if ($complete && $scopedUf === null) {
            HorizonteSiconfiSyncProgress::markComplete($year, $period);
        }

        $ufLabel = implode(', ', $processedUfs);
        $message = __('SICONFI: :ufs — :n município(s) atualizados (:falhas sem dados). Progresso: :done/:total UFs.', [
            'ufs' => $ufLabel,
            'n' => (string) $imported,
            'falhas' => (string) count($failedItems),
            'done' => (string) $doneUfs,
            'total' => (string) $totalUfs,
        ]);

        Log::info('horizonte.siconfi_sync', [
            'mode' => 'by_uf',
            'year' => $year,
            'period' => $period,
            'ufs' => $processedUfs,
            'imported' => $imported,
            'failed' => count($failedItems),
            'partial' => $partial,
            'complete' => $complete,
            'siconfi_done' => $doneUfs,
            'siconfi_total' => $totalUfs,
            'remaining_ufs' => $remainingUfs,
            'items' => $importedItems,
            'failed_items' => $failedItems,
        ]);

        return [
            'success' => $imported > 0 || $processedUfs !== [],
            'message' => $message,
            'imported' => $imported,
            'partial' => $partial,
            'complete' => $complete,
            'items' => $importedItems,
            'failed' => $failedItems,
            'debug_lines' => array_merge($importedLines, $failedLines),
            'imported_lines' => $importedLines,
            'failed_lines' => $failedLines,
            'ufs' => $processedUfs,
            'remaining_ufs' => $remainingUfs,
            'siconfi_done' => $doneUfs,
            'siconfi_total' => $totalUfs,
            'key' => 'siconfi_sync',
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return array{
     *     imported: int,
     *     items: list<array<string, mixed>>,
     *     failed: list<array<string, mixed>>
     * }
     */
    private function processIbgeCodes(array $codes, int $year, int $period): array
    {
        $imported = 0;
        $importedItems = [];
        $failedItems = [];

        foreach ($codes as $ibge) {
            $label = $this->municipalityLabel($ibge);
            $fetch = $this->fetchAndParse((int) $ibge, $year, $period);
            if ($fetch['parsed'] === null) {
                $reason = (string) ($fetch['reason'] ?? 'empty_api');
                $failedItems[] = array_merge($label, [
                    'ibge' => $ibge,
                    'reason' => $reason,
                ]);
                Log::debug('horizonte.siconfi_municipio_skipped', [
                    'ibge' => $ibge,
                    'name' => $label['name'],
                    'uf' => $label['uf'],
                    'year' => $year,
                    'period' => $period,
                    'reason' => $reason,
                ]);

                continue;
            }

            $parsed = $fetch['parsed'];

            MunicipalFiscalSnapshot::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $year],
                array_merge($parsed, [
                    'periodo' => $period,
                    'fonte' => 'siconfi_rreo',
                    'imported_at' => now(),
                ]),
            );
            $imported++;

            $item = array_merge($label, [
                'ibge' => $ibge,
                'receita_corrente_liquida' => $parsed['receita_corrente_liquida'] ?? null,
                'despesa_educacao_liquidada' => $parsed['despesa_educacao_liquidada'] ?? null,
                'pct_educacao_receita_corrente' => $parsed['pct_educacao_receita_corrente'] ?? null,
                'pct_minimo_constitucional' => $parsed['pct_minimo_constitucional'] ?? null,
                'fiscal_capacity_score' => $parsed['fiscal_capacity_score'] ?? null,
                'liquidity_ratio' => $parsed['liquidity_ratio'] ?? null,
            ]);
            $importedItems[] = $item;

            Log::info('horizonte.siconfi_municipio_imported', [
                'ibge' => $ibge,
                'name' => $label['name'],
                'uf' => $label['uf'],
                'year' => $year,
                'period' => $period,
                'receita_corrente_liquida' => $item['receita_corrente_liquida'],
                'pct_educacao_receita_corrente' => $item['pct_educacao_receita_corrente'],
                'fiscal_capacity_score' => $item['fiscal_capacity_score'],
            ]);
        }

        return [
            'imported' => $imported,
            'items' => $importedItems,
            'failed' => $failedItems,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePendingCodesForUf(string $uf, int $year, int $period, bool $refresh): array
    {
        $all = HorizonteUfScope::ibgeCodesForUf($uf, $this->ibgeCatalog) ?? [];
        if ($all === []) {
            return [];
        }

        if ($refresh) {
            $satisfied = MunicipalFiscalSnapshot::query()
                ->where('ano', $year)
                ->where('periodo', '>=', $period)
                ->whereIn('ibge_municipio', $all)
                ->pluck('ibge_municipio')
                ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
                ->filter()
                ->all();
        } else {
            $satisfied = MunicipalFiscalSnapshot::query()
                ->where('ano', $year)
                ->whereIn('ibge_municipio', $all)
                ->pluck('ibge_municipio')
                ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
                ->filter()
                ->all();
        }

        $satisfiedSet = array_fill_keys($satisfied, true);

        return array_values(array_filter($all, static fn (string $ibge): bool => ! isset($satisfiedSet[$ibge])));
    }

    /**
     * @return array{name: string, uf: string}
     */
    private function municipalityLabel(string $ibge): array
    {
        $meta = $this->ibgeCatalog->metaByIbge($ibge);

        return [
            'name' => trim((string) ($meta['name'] ?? '')) ?: $ibge,
            'uf' => strtoupper(trim((string) ($meta['uf'] ?? IbgeUfFromCode::ufFromIbge($ibge) ?? ''))),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function formatImportedLine(array $item): string
    {
        $name = (string) ($item['name'] ?? $item['ibge'] ?? '');
        $uf = (string) ($item['uf'] ?? '');
        $ibge = (string) ($item['ibge'] ?? '');

        return __('  · :ibge — :nome/:uf · RCL :rcl · educ :pct% · score :score', [
            'ibge' => $ibge,
            'nome' => $name,
            'uf' => $uf !== '' ? $uf : '—',
            'rcl' => $this->formatMoney($item['receita_corrente_liquida'] ?? null),
            'pct' => $this->formatPct($item['pct_educacao_receita_corrente'] ?? null),
            'score' => isset($item['fiscal_capacity_score']) ? (string) $item['fiscal_capacity_score'] : '—',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function formatFailedLine(array $item): string
    {
        $name = (string) ($item['name'] ?? $item['ibge'] ?? '');
        $uf = (string) ($item['uf'] ?? '');
        $ibge = (string) ($item['ibge'] ?? '');

        return __('  · :ibge — :nome/:uf · :motivo', [
            'ibge' => $ibge,
            'nome' => $name,
            'uf' => $uf !== '' ? $uf : '—',
            'motivo' => match ((string) ($item['reason'] ?? 'empty_api')) {
                'fetch_error' => __('erro na API SICONFI'),
                default => __('sem dados RREO na API'),
            },
        ]);
    }

    private function formatMoney(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $amount = (float) $value;
        if (abs($amount) >= 1_000_000_000) {
            return number_format($amount / 1_000_000_000, 2, ',', '.').' bi';
        }
        if (abs($amount) >= 1_000_000) {
            return number_format($amount / 1_000_000, 2, ',', '.').' mi';
        }

        return number_format($amount, 0, ',', '.');
    }

    private function formatPct(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1, ',', '.') : '—';
    }

    private function handleReset(int $year, int $period, ?string $scopedUf, bool $nationalScope): int
    {
        if ($nationalScope) {
            $deleted = MunicipalFiscalSnapshot::query()->where('ano', $year)->delete();
            HorizonteSiconfiSyncProgress::reset($year, $period);

            return (int) $deleted;
        }

        if ($scopedUf !== null) {
            $codes = HorizonteUfScope::ibgeCodesForUf($scopedUf, $this->ibgeCatalog) ?? [];
            $deleted = 0;
            if ($codes !== []) {
                $deleted = MunicipalFiscalSnapshot::query()
                    ->where('ano', $year)
                    ->whereIn('ibge_municipio', $codes)
                    ->delete();
            }
            HorizonteSiconfiSyncProgress::reset($year, $period);

            return (int) $deleted;
        }

        return 0;
    }

    /**
     * @return array{parsed: array<string, mixed>|null, reason: string|null}
     */
    private function fetchAndParse(int $ibge, int $year, int $period): array
    {
        try {
            $a01 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 01');
            $a02 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 02');
            $a06 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 06');
            $a14 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 14');
        } catch (\Throwable $e) {
            Log::warning('horizonte.siconfi_fetch_failed', [
                'ibge' => $ibge,
                'year' => $year,
                'period' => $period,
                'message' => $e->getMessage(),
            ]);

            return ['parsed' => null, 'reason' => 'fetch_error'];
        }

        if ($a01 === [] && $a02 === [] && $a06 === [] && $a14 === []) {
            return ['parsed' => null, 'reason' => 'empty_api'];
        }

        $parsed = SiconfiRreoParser::parse($a01, $a02, $a06, $a14);
        $parsed['metadados'] = [
            'annex_counts' => [
                '01' => count($a01),
                '02' => count($a02),
                '06' => count($a06),
                '14' => count($a14),
            ],
        ];

        return ['parsed' => $parsed, 'reason' => null];
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeIbgeCodes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $codes = [];
        foreach ($raw as $code) {
            $norm = FundebMunicipioReferenceRepository::normalizeIbge((string) $code);
            if ($norm !== null) {
                $codes[] = $norm;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $explicitIbgeCodes
     * @return array{codes: list<string>, pending_after: int, total: int}
     */
    private function resolveIbgeBatch(
        ?string $scopedUf,
        int $perStep,
        int $year,
        int $period,
        bool $refresh,
        array $explicitIbgeCodes,
    ): array {
        if ($explicitIbgeCodes !== []) {
            $codes = array_slice($explicitIbgeCodes, 0, $perStep);

            return ['codes' => $codes, 'pending_after' => 0, 'total' => count($explicitIbgeCodes)];
        }

        $all = HorizonteUfScope::ibgeCodesForUf($scopedUf, $this->ibgeCatalog)
            ?? HorizonteUfScope::nationalIbgeCodes($this->ibgeCatalog);

        if ($refresh) {
            $satisfied = MunicipalFiscalSnapshot::query()
                ->where('ano', $year)
                ->where('periodo', '>=', $period)
                ->pluck('ibge_municipio')
                ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
                ->filter()
                ->all();
        } else {
            $satisfied = MunicipalFiscalSnapshot::query()
                ->where('ano', $year)
                ->pluck('ibge_municipio')
                ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
                ->filter()
                ->all();
        }

        $satisfiedSet = array_fill_keys($satisfied, true);
        $pending = array_values(array_filter($all, static fn (string $ibge): bool => ! isset($satisfiedSet[$ibge])));
        $codes = array_slice($pending, 0, $perStep);

        return [
            'codes' => $codes,
            'pending_after' => max(0, count($pending) - count($codes)),
            'total' => count($all),
        ];
    }
}
