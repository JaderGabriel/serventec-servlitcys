<?php

namespace App\Services\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Horizonte\FndePnaeEntidadesSuspensasParser;
use App\Support\Horizonte\FndeVaarNaoHabilitadosCsvParser;
use App\Support\Horizonte\FndeVaatInabilitadosCsvParser;
use App\Support\Horizonte\FndeVaatInabilitadosParser;
use App\Support\Horizonte\HorizonteMunicipalAlertsCache;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Http\SafeOutboundUrl;
use App\Support\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sincroniza alertas oficiais MEC/FNDE (inabilitação VAAT, registo manual) para o Horizonte.
 * Falhas parciais nunca interrompem o mapa — regista aviso e mantém cache anterior quando existir.
 */
final class HorizonteMunicipalAlertsSyncService
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message: string,
     *     matched: int,
     *     skipped: bool,
     *     sources: list<string>,
     *     warnings: list<string>
     * }
     */
    public function sync(array $options = []): array
    {
        if (! filter_var(config('horizonte.municipal_alerts.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'message' => __('Alertas municipais desactivados (HORIZONTE_MUNICIPAL_ALERTS_ENABLED=false).'),
                'matched' => 0,
                'skipped' => true,
                'sources' => [],
                'warnings' => [],
            ];
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $skipFnde = (bool) ($options['skip_fnde'] ?? false);
        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $warnings = [];
        $index = [];
        $sources = [];

        if (! $skipFnde) {
            $fnde = $this->importFndeVaatInabilitados($options, $warnings);
            if ($fnde['entries'] !== []) {
                foreach ($fnde['entries'] as $ibge => $row) {
                    $index[$ibge] = $this->mergeEntry($index[$ibge] ?? null, $row);
                }
            }
            if ($fnde['source'] !== '') {
                $sources[] = $fnde['source'];
            }

            $vaar = $this->importFndeVaarNaoHabilitados($options, $warnings);
            if ($vaar['entries'] !== []) {
                foreach ($vaar['entries'] as $ibge => $row) {
                    $index[$ibge] = $this->mergeEntry($index[$ibge] ?? null, $row);
                }
            }
            if ($vaar['source'] !== '') {
                $sources[] = $vaar['source'];
            }

            $pnae = $this->importPnaeEntidadesSuspensas($options, $warnings);
            if ($pnae['entries'] !== []) {
                foreach ($pnae['entries'] as $ibge => $row) {
                    $index[$ibge] = $this->mergeEntry($index[$ibge] ?? null, $row);
                }
            }
            if ($pnae['source'] !== '') {
                $sources[] = $pnae['source'];
            }
        }

        $manual = $this->loadManualRegistry();
        if ($manual['entries'] !== []) {
            foreach ($manual['entries'] as $ibge => $row) {
                $index[$ibge] = $this->mergeEntry($index[$ibge] ?? null, $row);
            }
            $sources[] = $manual['source'];
        }

        $remote = $this->loadRemoteRegistry();
        if ($remote['entries'] !== []) {
            foreach ($remote['entries'] as $ibge => $row) {
                $index[$ibge] = $this->mergeEntry($index[$ibge] ?? null, $row);
            }
            if ($remote['source'] !== '') {
                $sources[] = $remote['source'];
            }
        }

        if ($scopedUf !== null) {
            $index = array_filter(
                $index,
                static fn (mixed $_row, string $ibge): bool => HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf),
                ARRAY_FILTER_USE_BOTH,
            );

            if (! $dryRun) {
                $cached = HorizonteMunicipalAlertsCache::getIndex();
                foreach (array_keys($cached) as $ibge) {
                    if (HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf)) {
                        unset($cached[$ibge]);
                    }
                }
                $index = array_merge($cached, $index);
            }
        }

        $sources = array_values(array_unique(array_filter($sources)));
        $meta = [
            'synced_at' => now()->toIso8601String(),
            'sources' => $sources,
            'warnings' => $warnings,
            'matched' => count($index),
        ];

        if ($dryRun) {
            return [
                'success' => true,
                'message' => __('[dry-run] Alertas MEC/FNDE: :n município(s) com pendência.', [
                    'n' => (string) count($index),
                ]),
                'matched' => count($index),
                'skipped' => true,
                'sources' => $sources,
                'warnings' => $warnings,
            ];
        }

        if ($sources === [] && $index === []) {
            return [
                'success' => true,
                'message' => __('Alertas MEC/FNDE: nenhuma fonte disponível — execute com PDF FNDE configurado ou registo manual.'),
                'matched' => 0,
                'skipped' => true,
                'sources' => [],
                'warnings' => $warnings,
            ];
        }

        $this->persistSnapshot($index, $meta);
        HorizonteMunicipalAlertsCache::put($index, $meta);

        return [
            'success' => true,
            'message' => $scopedUf !== null
                ? __('Alertas MEC/FNDE (UF :uf): :n município(s) com pendência.', [
                    'uf' => $scopedUf,
                    'n' => (string) count(array_filter(
                        $index,
                        static fn (mixed $_row, string $ibge): bool => HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf),
                        ARRAY_FILTER_USE_BOTH,
                    )),
                ])
                : __('Alertas MEC/FNDE: :n município(s) com pendência.', ['n' => (string) count($index)]),
            'matched' => count($index),
            'skipped' => false,
            'sources' => $sources,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function indexedFromCache(): array
    {
        $this->hydrateCacheFromSnapshotIfNeeded();

        return HorizonteMunicipalAlertsCache::getIndex();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metaFromCache(): ?array
    {
        $this->hydrateCacheFromSnapshotIfNeeded();

        return HorizonteMunicipalAlertsCache::getMeta();
    }

    /**
     * Repõe cache Laravel a partir do snapshot em disco (sobrevive a cache:clear no deploy).
     */
    private function hydrateCacheFromSnapshotIfNeeded(): void
    {
        if ($this->snapshotHydrationAttempted) {
            return;
        }
        $this->snapshotHydrationAttempted = true;

        if (HorizonteMunicipalAlertsCache::getMeta() !== null) {
            return;
        }

        if (! filter_var(config('horizonte.municipal_alerts.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $rel = trim((string) config('horizonte.municipal_alerts.snapshot_path', 'horizonte/municipal_alerts_snapshot.json'));
        if ($rel === '' || ! Storage::disk('local')->exists($rel)) {
            return;
        }

        try {
            $raw = Storage::disk('local')->get($rel);
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::debug('horizonte.municipal_alerts_snapshot_hydrate_failed', [
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! is_array($payload)) {
            return;
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : null;
        $syncedAt = trim((string) ($meta['synced_at'] ?? ''));
        if ($meta === null || $syncedAt === '') {
            return;
        }

        $index = [];
        $municipios = is_array($payload['municipios'] ?? null) ? $payload['municipios'] : [];
        foreach ($municipios as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            if ($ibge === null) {
                continue;
            }
            unset($row['ibge_municipio']);
            $index[$ibge] = $row;
        }

        HorizonteMunicipalAlertsCache::put($index, $meta);

        Log::info('horizonte.municipal_alerts_snapshot_hydrated', [
            'matched' => count($index),
            'synced_at' => $syncedAt,
        ]);
    }

    private bool $snapshotHydrationAttempted = false;

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $warnings
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function importFndeVaatInabilitados(array $options, array &$warnings): array
    {
        $sourceConfig = config('horizonte.municipal_alerts.sources.fnde_vaat_inabilitados', []);
        if (! filter_var($sourceConfig['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return ['entries' => [], 'source' => ''];
        }

        $exerciseYear = max(2007, (int) ($sourceConfig['exercise_year'] ?? (int) date('Y')));
        $detailUrl = trim((string) ($sourceConfig['detail_page_url'] ?? ''));
        if ($detailUrl === '') {
            $detailUrl = (string) config('horizonte.municipal_alerts.detail_urls.siconfi_vaat', '');
        }

        $csvUrl = trim((string) ($sourceConfig['csv_url'] ?? ''));
        if ($csvUrl !== '' && SafeOutboundUrl::isAllowedHttpUrl($csvUrl)) {
            $csvResult = $this->importFndeVaatFromCsv($csvUrl, $sourceConfig, $options, $exerciseYear, $detailUrl, $warnings);
            if ($csvResult['entries'] !== []) {
                return $csvResult;
            }
        }

        $pdfUrl = trim((string) ($sourceConfig['pdf_url'] ?? ''));
        if ($pdfUrl === '' || ! SafeOutboundUrl::isAllowedHttpUrl($pdfUrl)) {
            if ($csvUrl === '') {
                $warnings[] = __('Fonte FNDE VAAT inabilitados não configurada (CSV ou PDF).');
            }

            return ['entries' => [], 'source' => ''];
        }

        try {
            $response = $this->fndeHttp()->get($pdfUrl);
            if (! $response->successful()) {
                $warnings[] = __('FNDE VAAT inabilitados (PDF): HTTP :status.', ['status' => (string) $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $binary = $response->body();
            $storagePath = trim((string) ($sourceConfig['storage_path'] ?? 'horizonte/alerts/fnde_vaat_inabilitados.pdf'));
            if ($storagePath !== '' && ! ((bool) ($options['dry_run'] ?? false))) {
                Storage::disk('local')->put($storagePath, $binary);
            }

            $text = PdfTextExtractor::fromBinary($binary);
            if (trim($text) === '') {
                $warnings[] = __('FNDE VAAT inabilitados: não foi possível extrair texto do PDF (use CSV oficial ou instale pdftotext).');

                return ['entries' => [], 'source' => ''];
            }

            $parsed = FndeVaatInabilitadosParser::parse($text, $exerciseYear, $detailUrl);

            return [
                'entries' => $this->normalizeFndeEntries($parsed),
                'source' => 'fnde_vaat_inabilitados_pdf',
            ];
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_alerts_fnde_pdf_failed', ['message' => $e->getMessage()]);
            $warnings[] = __('FNDE VAAT inabilitados (PDF): :msg', ['msg' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * @param  array<string, mixed>  $sourceConfig
     * @param  array<string, mixed>  $options
     * @param  list<string>  $warnings
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function importFndeVaatFromCsv(
        string $csvUrl,
        array $sourceConfig,
        array $options,
        int $exerciseYear,
        string $detailUrl,
        array &$warnings,
    ): array {
        try {
            $response = $this->fndeHttp()->get($csvUrl);
            if (! $response->successful()) {
                $warnings[] = __('FNDE VAAT inabilitados (CSV): HTTP :status.', ['status' => (string) $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $body = $response->body();
            $storagePath = trim((string) ($sourceConfig['csv_storage_path'] ?? 'horizonte/alerts/fnde_vaat_inabilitados.csv'));
            if ($storagePath !== '' && ! ((bool) ($options['dry_run'] ?? false))) {
                Storage::disk('local')->put($storagePath, $body);
            }

            $parsed = FndeVaatInabilitadosCsvParser::parse($body, $exerciseYear, $detailUrl);
            if ($parsed === []) {
                $warnings[] = __('FNDE VAAT inabilitados (CSV): nenhum município inabilitado encontrado no ficheiro.');

                return ['entries' => [], 'source' => ''];
            }

            return [
                'entries' => $this->normalizeFndeEntries($parsed),
                'source' => 'fnde_vaat_inabilitados_csv',
            ];
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_alerts_fnde_csv_failed', ['message' => $e->getMessage()]);
            $warnings[] = __('FNDE VAAT inabilitados (CSV): :msg', ['msg' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * Lista FNDE de entes não habilitados à complementação VAAR (CSV oficial Fundeb).
     *
     * @param  array<string, mixed>  $options
     * @param  list<string>  $warnings
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function importFndeVaarNaoHabilitados(array $options, array &$warnings): array
    {
        $sourceConfig = config('horizonte.municipal_alerts.sources.fnde_vaar_nao_habilitados', []);
        if (! filter_var($sourceConfig['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return ['entries' => [], 'source' => ''];
        }

        $csvUrl = trim((string) ($sourceConfig['csv_url'] ?? ''));
        if ($csvUrl === '' || ! SafeOutboundUrl::isAllowedHttpUrl($csvUrl)) {
            return ['entries' => [], 'source' => ''];
        }

        $exerciseYear = max(2007, (int) ($sourceConfig['exercise_year'] ?? (int) date('Y')));
        $detailUrl = trim((string) ($sourceConfig['detail_page_url'] ?? ''));
        if ($detailUrl === '') {
            $detailUrl = (string) config('horizonte.municipal_alerts.detail_urls.fnde_vaar', '');
        }

        try {
            $response = $this->fndeHttp()->get($csvUrl);
            if (! $response->successful()) {
                $warnings[] = __('FNDE VAAR não habilitados (CSV): HTTP :status.', ['status' => (string) $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $body = $response->body();
            $storagePath = trim((string) ($sourceConfig['csv_storage_path'] ?? 'horizonte/alerts/fnde_vaar_nao_habilitados.csv'));
            if ($storagePath !== '' && ! ((bool) ($options['dry_run'] ?? false))) {
                Storage::disk('local')->put($storagePath, $body);
            }

            $parsed = FndeVaarNaoHabilitadosCsvParser::parse($body, $exerciseYear, $detailUrl);
            if ($parsed === []) {
                $warnings[] = __('FNDE VAAR não habilitados (CSV): nenhum ente com pendência encontrado.');

                return ['entries' => [], 'source' => ''];
            }

            return [
                'entries' => $this->normalizeFndeEntries($parsed),
                'source' => 'fnde_vaar_nao_habilitados',
            ];
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_alerts_vaar_failed', ['message' => $e->getMessage()]);
            $warnings[] = __('FNDE VAAR não habilitados (CSV): :msg', ['msg' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * Relação FNDE de Entidades Executoras com repasse PNAE suspenso (XLSX oficial).
     *
     * @param  array<string, mixed>  $options
     * @param  list<string>  $warnings
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function importPnaeEntidadesSuspensas(array $options, array &$warnings): array
    {
        $sourceConfig = config('horizonte.municipal_alerts.sources.pnae_entidades_suspensas', []);
        if (! filter_var($sourceConfig['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return ['entries' => [], 'source' => ''];
        }

        $xlsxUrl = trim((string) ($sourceConfig['xlsx_url'] ?? ''));
        if ($xlsxUrl === '' || ! SafeOutboundUrl::isAllowedHttpUrl($xlsxUrl)) {
            return ['entries' => [], 'source' => ''];
        }

        $exerciseYear = max(2007, (int) ($sourceConfig['exercise_year'] ?? (int) date('Y')));
        $detailUrl = trim((string) ($sourceConfig['detail_page_url'] ?? ''));
        if ($detailUrl === '') {
            $detailUrl = (string) config('horizonte.municipal_alerts.detail_urls.pnae_suspensas', '');
        }

        try {
            $response = $this->fndeHttp()->get($xlsxUrl);
            if (! $response->successful()) {
                $warnings[] = __('PNAE entidades suspensas (XLSX): HTTP :status.', ['status' => (string) $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $binary = $response->body();
            $storagePath = trim((string) ($sourceConfig['storage_path'] ?? 'horizonte/alerts/pnae_entidades_suspensas.xlsx'));
            if ($storagePath !== '' && ! ((bool) ($options['dry_run'] ?? false))) {
                Storage::disk('local')->put($storagePath, $binary);
            }

            $parsed = FndePnaeEntidadesSuspensasParser::parse($binary, $exerciseYear, $detailUrl);
            if (($parsed['unmatched'] ?? 0) > 0) {
                $warnings[] = __('PNAE entidades suspensas: :n ente(s) sem correspondência IBGE (ex.: secretarias estaduais).', [
                    'n' => (string) $parsed['unmatched'],
                ]);
            }

            if (($parsed['entries'] ?? []) === []) {
                return ['entries' => [], 'source' => ''];
            }

            return [
                'entries' => $parsed['entries'],
                'source' => 'pnae_entidades_suspensas',
            ];
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_alerts_pnae_failed', ['message' => $e->getMessage()]);
            $warnings[] = __('PNAE entidades suspensas (XLSX): :msg', ['msg' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $parsed
     * @return array<string, array<string, mixed>>
     */
    private function normalizeFndeEntries(array $parsed): array
    {
        $entries = [];
        foreach ($parsed as $ibge => $row) {
            $entries[$ibge] = [
                'items' => $row['items'] ?? [],
                'uf' => $row['uf'] ?? '',
                'name' => $row['name'] ?? '',
            ];
        }

        return $entries;
    }

    private function fndeHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = max(15, min(120, (int) config('horizonte.municipal_alerts.http_timeout', 45)));
        $userAgent = trim((string) config('horizonte.municipal_alerts.http_user_agent', 'Mozilla/5.0 (compatible; Servlitcys-Horizonte/1.0)'));

        return Http::timeout($timeout)->withHeaders([
            'User-Agent' => $userAgent !== '' ? $userAgent : 'Servlitcys-Horizonte/1.0',
            'Accept' => '*/*',
        ]);
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function loadManualRegistry(): array
    {
        $rel = trim((string) config('horizonte.municipal_alerts.registry_path', 'horizonte/municipal_alerts_registry.json'));
        if ($rel === '') {
            return ['entries' => [], 'source' => ''];
        }

        try {
            if (! Storage::disk('local')->exists($rel)) {
                return ['entries' => [], 'source' => ''];
            }

            $raw = Storage::disk('local')->get($rel);

            return [
                'entries' => $this->parseRegistryPayload($raw, 'manual_registry'),
                'source' => 'manual_registry',
            ];
        } catch (\Throwable $e) {
            Log::debug('horizonte.municipal_alerts_manual_failed', ['message' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function loadRemoteRegistry(): array
    {
        $url = trim((string) config('horizonte.municipal_alerts.registry_url', ''));
        if ($url === '' || ! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return ['entries' => [], 'source' => ''];
        }

        try {
            $timeout = max(5, min(60, (int) config('horizonte.municipal_alerts.http_timeout', 45)));
            $response = Http::timeout($timeout)->acceptJson()->get($url);
            if (! $response->successful()) {
                return ['entries' => [], 'source' => ''];
            }

            return [
                'entries' => $this->parseRegistryPayload($response->body(), 'remote_registry'),
                'source' => 'remote_registry',
            ];
        } catch (\Throwable $e) {
            Log::debug('horizonte.municipal_alerts_remote_failed', ['message' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parseRegistryPayload(string $raw, string $sourceLabel): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = isset($decoded['municipios']) && is_array($decoded['municipios'])
            ? $decoded['municipios']
            : $decoded;

        $index = [];
        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $ibgeRaw = $row['ibge_municipio'] ?? $row['ibge'] ?? (is_string($key) ? $key : null);
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $ibgeRaw);
            if ($ibge === null) {
                continue;
            }

            $items = [];
            if (isset($row['items']) && is_array($row['items'])) {
                foreach ($row['items'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $title = trim((string) ($item['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $items[] = [
                        'kind' => trim((string) ($item['kind'] ?? 'manual')),
                        'severity' => trim((string) ($item['severity'] ?? 'warning')),
                        'title' => $title,
                        'detail' => trim((string) ($item['detail'] ?? '')),
                        'exercise_year' => isset($item['exercise_year']) ? (int) $item['exercise_year'] : null,
                        'source' => trim((string) ($item['source'] ?? $sourceLabel)),
                        'detail_url' => trim((string) ($item['detail_url'] ?? '')),
                    ];
                }
            } else {
                $title = trim((string) ($row['title'] ?? $row['alerta'] ?? ''));
                if ($title !== '') {
                    $items[] = [
                        'kind' => trim((string) ($row['kind'] ?? 'manual')),
                        'severity' => trim((string) ($row['severity'] ?? 'warning')),
                        'title' => $title,
                        'detail' => trim((string) ($row['detail'] ?? $row['motivo'] ?? '')),
                        'exercise_year' => isset($row['exercise_year']) ? (int) $row['exercise_year'] : null,
                        'source' => $sourceLabel,
                        'detail_url' => trim((string) ($row['detail_url'] ?? '')),
                    ];
                }
            }

            if ($items === []) {
                continue;
            }

            $index[$ibge] = [
                'items' => $items,
                'uf' => trim((string) ($row['uf'] ?? '')),
                'name' => trim((string) ($row['name'] ?? $row['nome'] ?? '')),
            ];
        }

        return $index;
    }

    /**
     * @param  ?array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeEntry(?array $existing, array $incoming): array
    {
        $items = [];
        foreach ([$existing['items'] ?? [], $incoming['items'] ?? []] as $batch) {
            if (! is_array($batch)) {
                continue;
            }
            foreach ($batch as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = ($item['kind'] ?? '').'|'.($item['title'] ?? '').'|'.($item['detail'] ?? '');
                $items[$key] = $item;
            }
        }

        return [
            'items' => array_values($items),
            'uf' => trim((string) ($incoming['uf'] ?? $existing['uf'] ?? '')),
            'name' => trim((string) ($incoming['name'] ?? $existing['name'] ?? '')),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, mixed>  $meta
     */
    private function persistSnapshot(array $index, array $meta): void
    {
        $rel = trim((string) config('horizonte.municipal_alerts.snapshot_path', 'horizonte/municipal_alerts_snapshot.json'));
        if ($rel === '') {
            return;
        }

        $municipios = [];
        ksort($index, SORT_STRING);
        foreach ($index as $ibge => $row) {
            $municipios[] = array_merge(['ibge_municipio' => $ibge], $row);
        }

        Storage::disk('local')->put(
            $rel,
            json_encode([
                'updated_at' => now()->toIso8601String(),
                'meta' => $meta,
                'municipios' => $municipios,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
