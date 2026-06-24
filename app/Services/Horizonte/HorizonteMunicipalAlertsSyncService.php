<?php

namespace App\Services\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Horizonte\FndeVaatInabilitadosParser;
use App\Support\Horizonte\HorizonteMunicipalAlertsCache;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Http\SafeOutboundUrl;
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
        return HorizonteMunicipalAlertsCache::getIndex();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metaFromCache(): ?array
    {
        return HorizonteMunicipalAlertsCache::getMeta();
    }

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

        $url = trim((string) ($sourceConfig['pdf_url'] ?? ''));
        if ($url === '' || ! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            $warnings[] = __('PDF FNDE VAAT inabilitados não configurado ou URL inválida.');

            return ['entries' => [], 'source' => ''];
        }

        try {
            $timeout = max(15, min(120, (int) config('horizonte.municipal_alerts.http_timeout', 45)));
            $response = Http::timeout($timeout)->get($url);
            if (! $response->successful()) {
                $warnings[] = __('FNDE VAAT inabilitados: HTTP :status.', ['status' => (string) $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $binary = $response->body();
            $storagePath = trim((string) ($sourceConfig['storage_path'] ?? 'horizonte/alerts/fnde_vaat_inabilitados.pdf'));
            if ($storagePath !== '' && ! ((bool) ($options['dry_run'] ?? false))) {
                Storage::disk('local')->put($storagePath, $binary);
            }

            $text = $this->extractPdfText($binary);
            if (trim($text) === '') {
                $warnings[] = __('FNDE VAAT inabilitados: não foi possível extrair texto do PDF.');

                return ['entries' => [], 'source' => ''];
            }

            $exerciseYear = max(2007, (int) ($sourceConfig['exercise_year'] ?? (int) date('Y')));
            $detailUrl = trim((string) ($sourceConfig['detail_page_url'] ?? ''));
            if ($detailUrl === '') {
                $detailUrl = (string) config('horizonte.municipal_alerts.detail_urls.siconfi_vaat', '');
            }

            $parsed = FndeVaatInabilitadosParser::parse($text, $exerciseYear, $detailUrl);
            $entries = [];
            foreach ($parsed as $ibge => $row) {
                $entries[$ibge] = [
                    'items' => $row['items'] ?? [],
                    'uf' => $row['uf'] ?? '',
                    'name' => $row['name'] ?? '',
                ];
            }

            return ['entries' => $entries, 'source' => 'fnde_vaat_inabilitados'];
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_alerts_fnde_failed', ['message' => $e->getMessage()]);
            $warnings[] = __('FNDE VAAT inabilitados: :msg', ['msg' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    private function extractPdfText(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sl_pdf_');
        if ($tmp === false) {
            return $this->extractPdfTextFallback($binary);
        }

        try {
            file_put_contents($tmp, $binary);
            $pdftotext = trim((string) shell_exec('command -v pdftotext'));
            if ($pdftotext !== '') {
                $output = shell_exec('pdftotext '.escapeshellarg($tmp).' - 2>/dev/null');
                if (is_string($output) && trim($output) !== '') {
                    return $output;
                }
            }
        } finally {
            @unlink($tmp);
        }

        return $this->extractPdfTextFallback($binary);
    }

    private function extractPdfTextFallback(string $binary): string
    {
        if (preg_match_all('/\d{7}\s+Inobservância[\x09\x20-\x7E\xC0-\xFF]+/u', $binary, $matches)) {
            return implode("\n", $matches[0]);
        }

        return '';
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
