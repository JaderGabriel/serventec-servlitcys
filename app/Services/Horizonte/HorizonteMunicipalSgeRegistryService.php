<?php

namespace App\Services\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Horizonte\HorizonteMunicipalSgeCache;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Carrega registo opcional IBGE → SGE (JSON local ou URL) para o Horizonte.
 * Falhas nunca interrompem o abastecimento bimestral.
 */
final class HorizonteMunicipalSgeRegistryService
{
    /**
     * @return array{success: bool, message: string, matched: int, skipped: bool, sources: list<string>}
     */
    public function sync(?string $ufScope = null): array
    {
        $scopedUf = HorizonteUfScope::normalize($ufScope);
        if (! filter_var(config('horizonte.sge.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'message' => __('Registo SGE desactivado (HORIZONTE_SGE_ENABLED=false).'),
                'matched' => 0,
                'skipped' => true,
                'sources' => [],
            ];
        }

        $index = [];
        $sources = [];

        $local = $this->loadLocalRegistry();
        if ($local['entries'] !== []) {
            $index = array_merge($index, $local['entries']);
            $sources[] = $local['source'];
        }

        $remote = $this->loadRemoteRegistry();
        if ($remote['entries'] !== []) {
            foreach ($remote['entries'] as $ibge => $row) {
                if (! isset($index[$ibge])) {
                    $index[$ibge] = $row;
                }
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
        }

        $catalogCount = $this->countCatalogSge($scopedUf);

        if ($index === [] && $sources === []) {
            HorizonteMunicipalSgeCache::put([]);

            return [
                'success' => true,
                'message' => __('SGE: registo externo não encontrado — mapa usa apenas catálogo ServLITCYS (:n município(s)).', [
                    'n' => (string) $catalogCount,
                ]),
                'matched' => 0,
                'skipped' => true,
                'sources' => ['servlitcys_catalog'],
            ];
        }

        if ($scopedUf !== null) {
            $cached = HorizonteMunicipalSgeCache::get();
            foreach (array_keys($cached) as $ibge) {
                if (HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf)) {
                    unset($cached[$ibge]);
                }
            }
            $index = array_merge($cached, $index);
        }

        HorizonteMunicipalSgeCache::put($index);

        return [
            'success' => true,
            'message' => $scopedUf !== null
                ? __('SGE (UF :uf): :n município(s) no registo externo (+ catálogo local).', [
                    'uf' => $scopedUf,
                    'n' => (string) count(array_filter(
                        $index,
                        static fn (mixed $_row, string $ibge): bool => HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf),
                        ARRAY_FILTER_USE_BOTH,
                    )),
                ])
                : __('SGE: :n município(s) no registo externo (+ catálogo local).', [
                    'n' => (string) count($index),
                ]),
            'matched' => $scopedUf !== null
                ? count(array_filter(
                    $index,
                    static fn (mixed $_row, string $ibge): bool => HorizonteUfScope::ibgeBelongsToScope($ibge, $scopedUf),
                    ARRAY_FILTER_USE_BOTH,
                ))
                : count($index),
            'skipped' => false,
            'sources' => array_values(array_unique(array_merge(['servlitcys_catalog'], $sources))),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function indexedFromCache(): array
    {
        return HorizonteMunicipalSgeCache::get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function localEntry(string $ibge): ?array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibge === null) {
            return null;
        }

        $entries = $this->loadLocalRegistry()['entries'];

        return $entries[$ibge] ?? null;
    }

    /**
     * @param  array{system: string, vendor?: string, notes?: string, app_url?: string}  $fields
     * @return array{ibge: string, entry: array<string, mixed>}
     */
    public function upsertLocalEntry(string $ibge, array $fields, ?int $userId = null): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibge === null) {
            throw new \InvalidArgumentException(__('Código IBGE inválido.'));
        }

        $system = trim((string) ($fields['system'] ?? ''));
        if ($system === '') {
            throw new \InvalidArgumentException(__('Informe o nome do sistema de gestão (SGE).'));
        }

        $entry = [
            'system' => $system,
            'vendor' => trim((string) ($fields['vendor'] ?? '')),
            'notes' => trim((string) ($fields['notes'] ?? '')),
            'app_url' => trim((string) ($fields['app_url'] ?? '')),
            'source' => 'manual_admin',
            'updated_at' => now()->toIso8601String(),
        ];
        if ($userId !== null) {
            $entry['updated_by'] = $userId;
        }

        $local = $this->loadLocalRegistry()['entries'];
        $local[$ibge] = $entry;
        $this->persistLocalRegistry($local);
        $this->mergeEntryIntoRuntimeCache($ibge, $entry);

        return ['ibge' => $ibge, 'entry' => $entry];
    }

    public function removeLocalEntry(string $ibge): bool
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibge === null) {
            return false;
        }

        $local = $this->loadLocalRegistry()['entries'];
        if (! isset($local[$ibge])) {
            return false;
        }

        unset($local[$ibge]);
        $this->persistLocalRegistry($local);

        $cached = HorizonteMunicipalSgeCache::get();
        unset($cached[$ibge]);
        HorizonteMunicipalSgeCache::put($cached);

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     */
    private function persistLocalRegistry(array $index): void
    {
        $rel = trim((string) config('horizonte.sge.registry_path', 'horizonte/sge_registry.json'));
        if ($rel === '') {
            throw new \RuntimeException(__('Caminho do registo SGE não configurado.'));
        }

        ksort($index, SORT_STRING);
        $municipios = [];
        foreach ($index as $ibgeCode => $row) {
            $municipios[] = array_merge(
                ['ibge_municipio' => $ibgeCode],
                $row,
            );
        }

        $payload = [
            'updated_at' => now()->toIso8601String(),
            'municipios' => $municipios,
        ];

        Storage::disk('local')->put(
            $rel,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function mergeEntryIntoRuntimeCache(string $ibge, array $entry): void
    {
        $cached = HorizonteMunicipalSgeCache::get();
        $cached[$ibge] = $entry;
        HorizonteMunicipalSgeCache::put($cached);
    }

    private function countCatalogSge(?string $ufScope = null): int
    {
        $query = \App\Models\City::query()->whereNotNull('ibge_municipio');
        if ($ufScope !== null) {
            $query->where('uf', $ufScope);
        }

        return (int) $query->count();
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function loadLocalRegistry(): array
    {
        $rel = trim((string) config('horizonte.sge.registry_path', 'horizonte/sge_registry.json'));
        if ($rel === '') {
            return ['entries' => [], 'source' => ''];
        }

        try {
            if (! Storage::disk('local')->exists($rel)) {
                return ['entries' => [], 'source' => ''];
            }

            $raw = Storage::disk('local')->get($rel);
            $entries = $this->parseRegistryPayload($raw, 'local:'.$rel);

            return ['entries' => $entries, 'source' => 'local_registry'];
        } catch (\Throwable $e) {
            Log::debug('horizonte.sge_local_failed', ['path' => $rel, 'message' => $e->getMessage()]);

            return ['entries' => [], 'source' => ''];
        }
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, source: string}
     */
    private function loadRemoteRegistry(): array
    {
        $url = trim((string) config('horizonte.sge.registry_url', ''));
        if ($url === '') {
            return ['entries' => [], 'source' => ''];
        }

        try {
            if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
                return ['entries' => [], 'source' => ''];
            }

            $timeout = max(5, min(60, (int) config('horizonte.sge.registry_http_timeout', 15)));
            $response = Http::timeout($timeout)->acceptJson()->get($url);
            if (! $response->successful()) {
                Log::debug('horizonte.sge_remote_http', ['url' => $url, 'status' => $response->status()]);

                return ['entries' => [], 'source' => ''];
            }

            $entries = $this->parseRegistryPayload($response->body(), 'url:'.$url);

            return ['entries' => $entries, 'source' => 'remote_registry'];
        } catch (\Throwable $e) {
            Log::debug('horizonte.sge_remote_failed', ['message' => $e->getMessage()]);

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
            $ibge = \App\Repositories\FundebMunicipioReferenceRepository::normalizeIbge((string) $ibgeRaw);
            if ($ibge === null) {
                continue;
            }

            $system = trim((string) ($row['system'] ?? $row['sistema'] ?? ''));
            if ($system === '') {
                continue;
            }

            $index[$ibge] = [
                'system' => $system,
                'vendor' => trim((string) ($row['vendor'] ?? $row['fornecedor'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? $row['notas'] ?? '')),
                'app_url' => trim((string) ($row['app_url'] ?? $row['url'] ?? '')),
                'source' => trim((string) ($row['source'] ?? $sourceLabel)),
            ];
        }

        return $index;
    }
}
