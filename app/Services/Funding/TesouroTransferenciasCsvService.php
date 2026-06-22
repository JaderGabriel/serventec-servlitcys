<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\MunicipalityNomeUfKey;
use App\Support\Filesystem\ContainedPathResolver;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Repasses do pacote CKAN Tesouro Transparente (CSV por município).
 * O CSV usa COD_MUN interno; a correspondência com IBGE é por nome+UF (e cache cod_mun→IBGE).
 */
final class TesouroTransferenciasCsvService
{
    private const INDEX_SCHEMA_VERSION = 3;

    /** @var array<string, mixed> */
    private array $lastIndexLoadMeta = [];
    /**
     * @return list<array{
     *   ibge_municipio: string,
     *   ano: int,
     *   fonte: string,
     *   programa_id: string,
     *   programa_label: string,
     *   valor: float,
     *   meta: array<string, mixed>
     * }>
     */
    public function fetchRowsForCityYear(City $city, int $year, int $timeout): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        if (! (bool) ($cfg['csv_enabled'] ?? true)) {
            return [];
        }

        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [];
        }

        $resources = $this->resolveCsvResources($cfg, $timeout);
        if ($resources === []) {
            return [];
        }

        $rows = [];
        foreach ($resources as $resource) {
            $index = $this->loadResourceIndex($resource, $timeout);
            if ($index === []) {
                continue;
            }
            $match = $this->resolveMunicipality($index, $city, $ibge);
            if ($match === null) {
                continue;
            }
            $this->rememberCodMunMapping($match['cod_mun'], $ibge);
            $valor = $match['annual'][$year] ?? null;
            if ($valor === null || $valor <= 0) {
                continue;
            }
            $programaId = (string) $resource['programa_id'];
            $mensal = $this->normalizeMensalMap($match['mensal'][$year] ?? []);
            $meta = [
                'cod_mun' => $match['cod_mun'],
                'uf' => $match['uf'],
                'municipio' => $match['nome'],
                'resource_id' => $resource['resource_id'],
                'resource_name' => $resource['name'],
                'meses_somados' => $match['months_counted'][$year] ?? count($mensal),
                'mensal' => $mensal,
            ];
            if ($mensal !== []) {
                $meta['granularity'] = 'month';
                $meta['repasses'] = $this->repassesFromMensal($mensal, $year);
            }
            $rows[] = [
                'ibge_municipio' => $ibge,
                'ano' => $year,
                'fonte' => 'tesouro_csv',
                'programa_id' => $programaId,
                'programa_label' => $this->programLabel($programaId),
                'valor' => round($valor, 2),
                'meta' => $meta,
            ];
        }

        return $rows;
    }

    /**
     * Repasses mensais a partir do meta gravado ou do índice CSV em cache (para extrato Tempo Real).
     *
     * @param  array<string, mixed>  $meta
     * @return array<int, float>
     */
    public function resolveMensalForSnapshotMeta(array $meta, int $year, int $timeout = 15): array
    {
        $fromMeta = $this->normalizeMensalMap($this->mensalSliceFromMeta($meta, $year));
        if ($fromMeta !== []) {
            return $fromMeta;
        }

        $resourceId = trim((string) ($meta['resource_id'] ?? ''));
        if ($resourceId === '') {
            return [];
        }

        $resource = $this->findConfiguredResource($resourceId);
        if ($resource === null) {
            return [];
        }

        $index = $this->loadResourceIndex($resource, $timeout);
        $entry = $this->findMunicipalityEntryInIndex($index, $meta);
        if ($entry === null) {
            return [];
        }

        $mensal = $entry['mensal'][$year] ?? $entry['mensal'][(string) $year] ?? [];

        return $this->normalizeMensalMap(is_array($mensal) ? $mensal : []);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, mixed>
     */
    private function mensalSliceFromMeta(array $meta, int $year): array
    {
        $mensal = $meta['mensal'] ?? null;
        if (! is_array($mensal) || $mensal === []) {
            return [];
        }

        if (isset($mensal[$year]) || isset($mensal[(string) $year])) {
            $slice = $mensal[$year] ?? $mensal[(string) $year];

            return is_array($slice) ? $slice : [];
        }

        $first = reset($mensal);

        return is_array($first) && ! is_numeric(array_key_first($mensal))
            ? []
            : $mensal;
    }

    /**
     * @param  array<int|string, mixed>  $map
     * @return array<int, float>
     */
    private function normalizeMensalMap(array $map): array
    {
        $out = [];
        foreach ($map as $month => $valor) {
            if (! is_numeric($valor) || (float) $valor <= 0) {
                continue;
            }
            $m = (int) $month;
            if ($m >= 1 && $m <= 12) {
                $out[$m] = (float) $valor;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * @param  array<int, float>  $mensal
     * @return list<array{mes: int, ano: int, valor: float, granularity: string}>
     */
    private function repassesFromMensal(array $mensal, int $year): array
    {
        $out = [];
        foreach ($mensal as $month => $valor) {
            $out[] = [
                'mes' => (int) $month,
                'ano' => $year,
                'valor' => round((float) $valor, 2),
                'granularity' => 'month',
            ];
        }

        return $out;
    }

    /**
     * @return ?array{resource_id: string, name: string, url: string, programa_id: string}
     */
    private function findConfiguredResource(string $resourceId): ?array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $resources = $this->resolveCsvResources(is_array($cfg) ? $cfg : [], 8);
        foreach ($resources as $resource) {
            if ((string) ($resource['resource_id'] ?? '') === $resourceId) {
                return $resource;
            }
        }

        $url = trim((string) cache()->get('tesouro_csv_resource_url_'.$resourceId));
        if ($url !== '') {
            return [
                'resource_id' => $resourceId,
                'name' => 'cached',
                'url' => $url,
                'programa_id' => 'fundeb',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  array<string, mixed>  $meta
     * @return ?array<string, mixed>
     */
    private function findMunicipalityEntryInIndex(array $index, array $meta): ?array
    {
        $byNomeUf = $index['by_nome_uf'] ?? null;
        if (! is_array($byNomeUf)) {
            return null;
        }

        $uf = strtoupper(trim((string) ($meta['uf'] ?? '')));
        $nome = trim((string) ($meta['municipio'] ?? ''));
        if ($nome !== '' && $uf !== '') {
            $key = $this->nomeUfKey($nome, $uf);
            if ($key !== '' && isset($byNomeUf[$key])) {
                return $byNomeUf[$key];
            }
        }

        $codMun = trim((string) ($meta['cod_mun'] ?? ''));
        if ($codMun !== '') {
            foreach ($byNomeUf as $entry) {
                if (is_array($entry) && (string) ($entry['cod_mun'] ?? '') === $codMun) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<array{resource_id: string, name: string, url: string, programa_id: string}>
     */
    private function resolveCsvResources(array $cfg, int $timeout): array
    {
        $configured = $cfg['csv_resources'] ?? null;
        if (is_array($configured) && $configured !== []) {
            $out = [];
            foreach ($configured as $programaId => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $resourceId = trim((string) ($item['resource_id'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));
                if ($resourceId === '' && $url === '') {
                    continue;
                }
                $rid = $resourceId !== '' ? $resourceId : md5($url);
                $out[] = [
                    'resource_id' => $rid,
                    'name' => (string) ($item['name'] ?? $programaId),
                    'url' => $url,
                    'programa_id' => (string) ($item['programa_id'] ?? $programaId),
                ];
                if ($url !== '') {
                    cache()->put('tesouro_csv_resource_url_'.$rid, $url, 604800);
                }
            }

            return $out;
        }

        return $this->discoverCsvResourcesFromPackage($cfg, $timeout);
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<array{resource_id: string, name: string, url: string, programa_id: string}>
     */
    private function discoverCsvResourcesFromPackage(array $cfg, int $timeout): array
    {
        $packageId = trim((string) ($cfg['package_id'] ?? ''));
        if ($packageId === '') {
            return [];
        }

        $base = rtrim((string) ($cfg['base_url'] ?? 'https://www.tesourotransparente.gov.br/ckan'), '/');
        $cacheKey = 'tesouro_csv_resources_'.md5($packageId);
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(min($timeout, 15))
                ->acceptJson()
                ->get($base.'/api/3/action/package_show', ['id' => $packageId]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $programKeywords = config('ieducar.funding.transfers.program_keywords', []);
        $resources = $response->json('result.resources') ?? [];
        if (! is_array($resources)) {
            return [];
        }

        $out = [];
        foreach ($resources as $res) {
            if (! is_array($res)) {
                continue;
            }
            $url = (string) ($res['url'] ?? '');
            if ($url === '' || ! Str::endsWith(strtolower($url), '.csv')) {
                continue;
            }
            $name = (string) ($res['name'] ?? '');
            $blob = strtolower($name.' '.$url);
            $programaId = $this->matchProgramId($blob, is_array($programKeywords) ? $programKeywords : []);
            if ($programaId === 'geral_educacao') {
                continue;
            }
            $resourceId = (string) ($res['id'] ?? '');
            if ($resourceId === '') {
                continue;
            }
            $out[] = [
                'resource_id' => $resourceId,
                'name' => $name,
                'url' => $url,
                'programa_id' => $programaId,
            ];
            cache()->put('tesouro_csv_resource_url_'.$resourceId, $url, 604800);
        }

        $ttl = max(300, (int) ($cfg['csv_cache_ttl_seconds'] ?? 86400));
        cache()->put($cacheKey, $out, $ttl);

        return $out;
    }

    /**
     * Metadados da última tentativa de carregar índice CSV (diagnóstico CLI/UI).
     *
     * @return array<string, mixed>
     */
    public function lastIndexLoadMeta(): array
    {
        return $this->lastIndexLoadMeta;
    }

    /**
     * @param  array{resource_id: string, name: string, url: string, programa_id: string}  $resource
     * @return array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>}>,
     *   year_columns: array<int, int>
     * }
     */
    private function loadResourceIndex(array $resource, int $timeout): array
    {
        $this->lastIndexLoadMeta = [
            'resource_id' => (string) ($resource['resource_id'] ?? ''),
            'url' => (string) ($resource['url'] ?? ''),
        ];

        $cached = $this->readValidIndexCache((string) $resource['resource_id']);
        if ($cached !== []) {
            $this->lastIndexLoadMeta['source'] = 'cache';

            return $cached;
        }

        $localPath = $this->resolveLocalCsvPath($resource);
        if ($localPath !== null) {
            $index = $this->parseCsvFile($localPath);
            if ($index !== []) {
                $this->persistIndexCache($resource, $index);
                $this->lastIndexLoadMeta['source'] = 'local';
                $this->lastIndexLoadMeta['local_path'] = $localPath;

                return $index;
            }
            $this->lastIndexLoadMeta['local_error'] = __('Ficheiro local legível mas sem linhas válidas.');
        }

        $download = $this->downloadCsvToTemp($resource, $timeout);
        $this->lastIndexLoadMeta['http_code'] = $download['http_code'];
        if ($download['error'] !== null) {
            $this->lastIndexLoadMeta['fetch_error'] = $download['error'];
        }

        if ($download['path'] !== null) {
            $index = $this->parseCsvFile($download['path']);
            @unlink($download['path']);
            if ($index !== []) {
                $this->persistIndexCache($resource, $index);
                $this->lastIndexLoadMeta['source'] = 'http';

                return $index;
            }
            $this->lastIndexLoadMeta['fetch_error'] = __('CSV descarregado mas sem colunas/linhas reconhecidas.');
        }

        $stale = $this->readStaleIndexCache((string) $resource['resource_id']);
        if ($stale !== []) {
            $this->lastIndexLoadMeta['source'] = 'stale_cache';

            return $stale;
        }

        return [];
    }

    /**
     * @param  array{resource_id: string, name: string, url: string, programa_id: string}  $resource
     */
    private function resolveLocalCsvPath(array $resource): ?string
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $roots = [
            storage_path('app'),
            storage_path('app/funding'),
            storage_path('app/funding/tesouro-csv'),
        ];

        $candidates = [];
        $programaId = (string) ($resource['programa_id'] ?? '');
        $csvResources = is_array($cfg['csv_resources'] ?? null) ? $cfg['csv_resources'] : [];
        foreach ($csvResources as $key => $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemPrograma = (string) ($item['programa_id'] ?? $key);
            if ($programaId !== '' && $itemPrograma !== $programaId) {
                continue;
            }
            $local = trim((string) ($item['local_path'] ?? ''));
            if ($local !== '') {
                $candidates[] = $local;
            }
        }

        $globalLocal = trim((string) ($cfg['csv_local_path'] ?? ''));
        if ($globalLocal !== '') {
            $candidates[] = $globalLocal;
        }

        $defaultName = $programaId !== '' ? $programaId.'-por-municipio.csv' : 'fundeb-por-municipio.csv';
        $candidates[] = 'funding/tesouro-csv/'.$defaultName;

        foreach (array_unique($candidates) as $candidate) {
            $resolved = ContainedPathResolver::resolveReadableFile($candidate, $roots);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param  array{resource_id: string, name: string, url: string, programa_id: string}  $resource
     * @return array{path: ?string, http_code: ?int, error: ?string}
     */
    private function downloadCsvToTemp(array $resource, int $timeout): array
    {
        $url = trim((string) ($resource['url'] ?? ''));
        if ($url === '') {
            return ['path' => null, 'http_code' => null, 'error' => __('URL do CSV não configurada.')];
        }

        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return ['path' => null, 'http_code' => null, 'error' => __('URL do CSV bloqueada pela política de saída (SSRF).')];
        }

        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $downloadTimeout = max(
            $timeout,
            max(30, (int) ($cfg['csv_download_timeout'] ?? 120)),
        );

        $dir = storage_path('app/funding/tesouro-csv');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmpPath = $dir.'/'.preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $resource['resource_id']).'.csv.tmp';

        try {
            $response = Http::timeout($downloadTimeout)
                ->withHeaders(['User-Agent' => 'Servlitcys-TesouroCSV/1.0'])
                ->withOptions(['allow_redirects' => true])
                ->sink($tmpPath)
                ->get($url);
        } catch (\Throwable $e) {
            @unlink($tmpPath);

            return ['path' => null, 'http_code' => null, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            @unlink($tmpPath);

            return [
                'path' => null,
                'http_code' => $response->status(),
                'error' => __('HTTP :code ao descarregar CSV.', ['code' => (string) $response->status()]),
            ];
        }

        $size = is_readable($tmpPath) ? (int) @filesize($tmpPath) : 0;
        if ($size < 64) {
            $body = $response->body();
            if ($body !== '' && strlen($body) >= 64) {
                file_put_contents($tmpPath, $body);
                $size = strlen($body);
            }
        }

        if ($size < 64) {
            @unlink($tmpPath);

            return [
                'path' => null,
                'http_code' => $response->status(),
                'error' => __('CSV descarregado vazio ou ilegível.'),
            ];
        }

        return ['path' => $tmpPath, 'http_code' => $response->status(), 'error' => null];
    }

    /**
     * @return array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>, mensal: array<int|string, array<int, float>>}>,
     *   year_columns: array<int, int>
     * }
     */
    private function readValidIndexCache(string $resourceId): array
    {
        $cachePath = $this->resourceCachePath($resourceId);
        if (! is_readable($cachePath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($cachePath), true);
        if (! $this->isIndexCacheValid($decoded)) {
            return [];
        }

        return is_array($decoded) ? $this->normalizeStoredIndex($decoded) : [];
    }

    /**
     * @return array{
     *   by_nome_uf: array<string, array<string, mixed>>,
     *   year_columns?: array<int, int>
     * }
     */
    private function readStaleIndexCache(string $resourceId): array
    {
        $cachePath = $this->resourceCachePath($resourceId);
        if (! is_readable($cachePath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($cachePath), true);
        if (! is_array($decoded) || ! isset($decoded['by_nome_uf']) || ! is_array($decoded['by_nome_uf']) || $decoded['by_nome_uf'] === []) {
            return [];
        }

        return $this->normalizeStoredIndex($decoded);
    }

    /**
     * Reindexa chaves nome+UF e anos após JSON (evita cache legado incompatível).
     *
     * @param  array<string, mixed>  $index
     * @return array<string, mixed>
     */
    private function normalizeStoredIndex(array $index): array
    {
        $byNomeUf = $index['by_nome_uf'] ?? null;
        if (! is_array($byNomeUf)) {
            return $index;
        }

        $normalized = [];
        foreach ($byNomeUf as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $nome = trim((string) ($entry['nome'] ?? ''));
            $uf = strtoupper(trim((string) ($entry['uf'] ?? '')));
            $key = $this->nomeUfKey($nome, $uf);
            if ($key === '') {
                continue;
            }
            $entry['annual'] = $this->normalizeYearValueMap($entry['annual'] ?? []);
            $entry['months_counted'] = $this->normalizeYearValueMap($entry['months_counted'] ?? [], true);
            $mensal = is_array($entry['mensal'] ?? null) ? $entry['mensal'] : [];
            $mensalNorm = [];
            foreach ($mensal as $year => $months) {
                $y = (int) $year;
                if ($y >= 2000 && is_array($months)) {
                    $mensalNorm[$y] = $months;
                }
            }
            $entry['mensal'] = $mensalNorm;
            $normalized[$key] = $entry;
        }

        $index['by_nome_uf'] = $normalized;
        if (isset($index['year_columns']) && is_array($index['year_columns'])) {
            $years = [];
            foreach ($index['year_columns'] as $year => $col) {
                $y = (int) $year;
                if ($y >= 2000) {
                    $years[$y] = (int) $col;
                }
            }
            $index['year_columns'] = $years;
        }

        return $index;
    }

    /**
     * @param  array<mixed, mixed>  $map
     * @return array<int, float|int>
     */
    private function normalizeYearValueMap(array $map, bool $intValues = false): array
    {
        $out = [];
        foreach ($map as $year => $value) {
            $y = (int) $year;
            if ($y < 2000) {
                continue;
            }
            $out[$y] = $intValues ? (int) $value : (float) $value;
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildIbgeByUfNome(array $ibgeByNomeUf): array
    {
        $byUf = [];
        foreach ($ibgeByNomeUf as $key => $ibge) {
            if (! is_string($key) || ! str_contains($key, '|')) {
                continue;
            }
            [$nome, $uf] = array_pad(explode('|', $key, 2), 2, '');
            $uf = strtoupper(trim($uf));
            $nome = trim($nome);
            if ($uf === '' || $nome === '') {
                continue;
            }
            $byUf[$uf][$nome] = (string) $ibge;
        }

        return $byUf;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $ibgeByNomeUf
     * @param  array<string, array<string, string>>  $ibgeByUfNome
     */
    private function resolveIbgeForEntry(array $entry, string $csvKey, array $ibgeByNomeUf, array $ibgeByUfNome): ?string
    {
        if ($csvKey !== '' && isset($ibgeByNomeUf[$csvKey])) {
            return (string) $ibgeByNomeUf[$csvKey];
        }

        $nome = trim((string) ($entry['nome'] ?? ''));
        $uf = strtoupper(trim((string) ($entry['uf'] ?? '')));
        if ($uf !== '' && $nome !== '') {
            $rebuilt = $this->nomeUfKey($nome, $uf);
            if ($rebuilt !== '' && isset($ibgeByNomeUf[$rebuilt])) {
                return (string) $ibgeByNomeUf[$rebuilt];
            }
            $nomeKey = MunicipalityNomeUfKey::normalizeNome($nome);
            if ($nomeKey !== '' && isset($ibgeByUfNome[$uf][$nomeKey])) {
                return (string) $ibgeByUfNome[$uf][$nomeKey];
            }
        }

        return $this->resolveIbgeFromCodMun((string) ($entry['cod_mun'] ?? ''));
    }

    /**
     * @param  array<string, array<string, mixed>>  $byNomeUf
     */
    private function countIbgeCrossMatches(array $byNomeUf, array $ibgeByNomeUf, int $year, ?string $scopedUf = null): int
    {
        $ibgeByUfNome = $this->buildIbgeByUfNome($ibgeByNomeUf);
        $matches = 0;
        foreach ($byNomeUf as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $uf = strtoupper(trim((string) ($entry['uf'] ?? '')));
            if ($scopedUf !== null && $scopedUf !== $uf) {
                continue;
            }
            if (((float) ($entry['annual'][$year] ?? 0)) <= 0) {
                continue;
            }
            if ($this->resolveIbgeForEntry($entry, (string) $key, $ibgeByNomeUf, $ibgeByUfNome) !== null) {
                $matches++;
            }
        }

        return $matches;
    }

    /**
     * Anos com valores FUNDEB no CSV CKAN (para priorizar exercício em curso vs consolidado).
     *
     * @return list<int>
     */
    public function availableFundebYears(int $timeout, ?string $ufFilter = null): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $resources = array_values(array_filter(
            $this->resolveCsvResources(is_array($cfg) ? $cfg : [], $timeout),
            static fn (array $r): bool => (string) ($r['programa_id'] ?? '') === 'fundeb',
        ));
        if ($resources === []) {
            return [];
        }

        $index = $this->normalizeStoredIndex($this->loadResourceIndex($resources[0], $timeout));
        $byNomeUf = is_array($index['by_nome_uf'] ?? null) ? $index['by_nome_uf'] : [];
        $scopedUf = HorizonteUfScope::normalize($ufFilter);
        $counts = [];

        foreach ($byNomeUf as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if ($scopedUf !== null && strtoupper(trim((string) ($entry['uf'] ?? ''))) !== $scopedUf) {
                continue;
            }
            foreach ($entry['annual'] ?? [] as $year => $valor) {
                $y = (int) $year;
                if ($y >= 2000 && (float) $valor > 0) {
                    $counts[$y] = ($counts[$y] ?? 0) + 1;
                }
            }
        }

        if ($counts === []) {
            return [];
        }

        arsort($counts);

        return array_map('intval', array_keys($counts));
    }

    /**
     * Ordem de anos: referência Horizonte → exercício civil corrente (parcial) → consolidado anterior → melhor cobertura CSV.
     *
     * @return list<int>
     */
    public function fundebYearsToTry(int $refYear, int $timeout, ?string $ufFilter = null): array
    {
        $currentYear = (int) date('Y');
        $available = $this->availableFundebYears($timeout, $ufFilter);
        $availableSet = array_fill_keys($available, true);

        $candidates = [
            $refYear,
            $currentYear,
            $refYear - 1,
            $currentYear - 1,
        ];

        $ordered = [];
        foreach ($candidates as $year) {
            if ($year >= 2000 && isset($availableSet[$year]) && ! in_array($year, $ordered, true)) {
                $ordered[] = $year;
            }
        }

        foreach ($available as $year) {
            if (! in_array($year, $ordered, true)) {
                $ordered[] = $year;
            }
        }

        if ($ordered !== []) {
            return $ordered;
        }

        return array_values(array_unique([$refYear, $currentYear, $refYear - 1]));
    }

    /**
     * @param  array{resource_id: string, name: string, url: string, programa_id: string}  $resource
     * @param  array{by_nome_uf: array<string, mixed>, year_columns: array<int, int>}  $index
     */
    private function persistIndexCache(array $resource, array $index): void
    {
        if ($index === []) {
            return;
        }

        $index = $this->normalizeStoredIndex($index);
        $index['_schema'] = self::INDEX_SCHEMA_VERSION;
        $cachePath = $this->resourceCachePath((string) $resource['resource_id']);
        $dir = dirname($cachePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($cachePath, json_encode($index, JSON_UNESCAPED_UNICODE));
        cache()->put('tesouro_csv_resource_url_'.$resource['resource_id'], $resource['url'], 604800);
    }

    /**
     * Lê CSV municipal linha a linha (ficheiros CKAN ~20 MB).
     *
     * @return array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>, mensal: array<int|string, array<int, float>>}>,
     *   year_columns: array<int, int>
     * }
     */
    public function parseCsvFile(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);

            return [];
        }

        $headerLine = $this->normalizeCsvLineEncoding($headerLine);
        $header = str_getcsv($headerLine, ';', '"', '\\');
        $yearColumns = $this->detectYearColumns($header);
        if ($yearColumns === []) {
            fclose($handle);

            return [];
        }

        $byNomeUf = [];

        while (($line = fgets($handle)) !== false) {
            $line = trim($this->normalizeCsvLineEncoding($line));
            if ($line === '' || ! str_contains($line, ';')) {
                continue;
            }
            $fields = str_getcsv($line, ';', '"', '\\');
            if (count($fields) < 6) {
                continue;
            }

            $codMun = trim((string) ($fields[0] ?? ''));
            $nome = trim((string) ($fields[1] ?? ''));
            $uf = strtoupper(trim((string) ($fields[2] ?? '')));
            $mes = (int) preg_replace('/\D/', '', (string) ($fields[4] ?? ''));
            if ($codMun === '' || $nome === '' || $uf === '' || $mes < 1 || $mes > 12) {
                continue;
            }

            $key = $this->nomeUfKey($nome, $uf);
            if (! isset($byNomeUf[$key])) {
                $byNomeUf[$key] = [
                    'cod_mun' => $codMun,
                    'nome' => $nome,
                    'uf' => $uf,
                    'annual' => [],
                    'months_counted' => [],
                    'mensal' => [],
                ];
            }

            foreach ($yearColumns as $year => $colIdx) {
                if (! isset($fields[$colIdx])) {
                    continue;
                }
                $valor = $this->parseBrazilianMoney((string) $fields[$colIdx]);
                if ($valor === null || $valor <= 0) {
                    continue;
                }
                if (! isset($byNomeUf[$key]['annual'][$year])) {
                    $byNomeUf[$key]['annual'][$year] = 0.0;
                    $byNomeUf[$key]['months_counted'][$year] = 0;
                }
                $byNomeUf[$key]['annual'][$year] += $valor;
                $byNomeUf[$key]['months_counted'][$year]++;
                if (! isset($byNomeUf[$key]['mensal'][$year][$mes])) {
                    $byNomeUf[$key]['mensal'][$year][$mes] = 0.0;
                }
                $byNomeUf[$key]['mensal'][$year][$mes] += $valor;
            }
        }

        fclose($handle);

        return $this->normalizeStoredIndex([
            'by_nome_uf' => $byNomeUf,
            'year_columns' => $yearColumns,
        ]);
    }

    private function normalizeCsvLineEncoding(string $line): string
    {
        if ($line === '' || mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        $converted = @mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');

        return is_string($converted) ? $converted : $line;
    }

    /**
     * @param  mixed  $decoded
     */
    private function isIndexCacheValid(mixed $decoded): bool
    {
        if (! is_array($decoded) || ! isset($decoded['by_nome_uf']) || ! is_array($decoded['by_nome_uf'])) {
            return false;
        }

        if ((int) ($decoded['_schema'] ?? 0) < self::INDEX_SCHEMA_VERSION) {
            return false;
        }

        foreach ($decoded['by_nome_uf'] as $entry) {
            if (! is_array($entry) || ! array_key_exists('mensal', $entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>}>,
     *   year_columns: array<int, int>
     * }
     */
    public function parseCsvBody(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        if ($lines === []) {
            return [];
        }

        $header = str_getcsv((string) array_shift($lines), ';', '"', '\\');
        $yearColumns = $this->detectYearColumns($header);
        if ($yearColumns === []) {
            return [];
        }

        $byNomeUf = [];

        foreach ($lines as $line) {
            if ($line === '' || ! str_contains($line, ';')) {
                continue;
            }
            $fields = str_getcsv($line, ';', '"', '\\');
            if (count($fields) < 6) {
                continue;
            }

            $codMun = trim((string) ($fields[0] ?? ''));
            $nome = trim((string) ($fields[1] ?? ''));
            $uf = strtoupper(trim((string) ($fields[2] ?? '')));
            $mes = (int) preg_replace('/\D/', '', (string) ($fields[4] ?? ''));
            if ($codMun === '' || $nome === '' || $uf === '' || $mes < 1 || $mes > 12) {
                continue;
            }

            $key = $this->nomeUfKey($nome, $uf);
            if (! isset($byNomeUf[$key])) {
                $byNomeUf[$key] = [
                    'cod_mun' => $codMun,
                    'nome' => $nome,
                    'uf' => $uf,
                    'annual' => [],
                    'months_counted' => [],
                    'mensal' => [],
                ];
            }

            foreach ($yearColumns as $year => $colIdx) {
                if (! isset($fields[$colIdx])) {
                    continue;
                }
                $valor = $this->parseBrazilianMoney((string) $fields[$colIdx]);
                if ($valor === null || $valor <= 0) {
                    continue;
                }
                if (! isset($byNomeUf[$key]['annual'][$year])) {
                    $byNomeUf[$key]['annual'][$year] = 0.0;
                    $byNomeUf[$key]['months_counted'][$year] = 0;
                }
                $byNomeUf[$key]['annual'][$year] += $valor;
                $byNomeUf[$key]['months_counted'][$year]++;
                if (! isset($byNomeUf[$key]['mensal'][$year][$mes])) {
                    $byNomeUf[$key]['mensal'][$year][$mes] = 0.0;
                }
                $byNomeUf[$key]['mensal'][$year][$mes] += $valor;
            }
        }

        return $this->normalizeStoredIndex([
            'by_nome_uf' => $byNomeUf,
            'year_columns' => $yearColumns,
        ]);
    }

    /**
     * @param  array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>}>,
     *   year_columns: array<int, int>
     * }  $index
     * @return ?array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>}
     */
    private function resolveMunicipality(array $index, City $city, string $ibge): ?array
    {
        $uf = strtoupper(trim((string) $city->uf));
        $candidates = array_filter([
            $this->nomeUfKey((string) $city->name, $uf),
            $this->nomeUfKey($this->officialNameFromIbge($ibge) ?? '', $uf),
        ]);

        foreach ($candidates as $key) {
            if ($key !== '' && isset($index['by_nome_uf'][$key])) {
                return $index['by_nome_uf'][$key];
            }
        }

        $codFromCache = $this->codMunForIbge($ibge);
        if ($codFromCache !== null) {
            foreach ($index['by_nome_uf'] as $entry) {
                if (($entry['cod_mun'] ?? '') === $codFromCache && ($entry['uf'] ?? '') === $uf) {
                    return $entry;
                }
            }
        }

        return null;
    }

    private function officialNameFromIbge(string $ibge): ?string
    {
        $cacheKey = 'ibge_municipio_nome_'.$ibge;
        $cached = cache()->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibge);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $nome = trim((string) $response->json('nome'));
        if ($nome !== '') {
            cache()->put($cacheKey, $nome, 604800);
        }

        return $nome !== '' ? $nome : null;
    }

    private function codMunForIbge(string $ibge): ?string
    {
        foreach ($this->codMunToIbgeMap() as $cod => $mappedIbge) {
            if ((string) $mappedIbge === $ibge) {
                return (string) $cod;
            }
        }

        return null;
    }

    public function rememberCodMunMapping(string $codMun, string $ibge): void
    {
        $codMun = trim($codMun);
        $ibge = trim($ibge);
        if ($codMun === '' || $ibge === '') {
            return;
        }

        $map = $this->codMunToIbgeMap();
        if (($map[$codMun] ?? '') === $ibge) {
            return;
        }

        $map[$codMun] = $ibge;
        $path = $this->codMunMapPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($map, JSON_UNESCAPED_UNICODE));
        self::$codMunMapMemo = $map;
    }

    /**
     * @param  array<string, string>  $mappings
     */
    public function rememberCodMunMappings(array $mappings): void
    {
        if ($mappings === []) {
            return;
        }

        $map = $this->codMunToIbgeMap();
        $changed = false;
        foreach ($mappings as $codMun => $ibge) {
            $codMun = trim((string) $codMun);
            $ibge = trim((string) $ibge);
            if ($codMun === '' || $ibge === '') {
                continue;
            }
            if (($map[$codMun] ?? '') === $ibge) {
                continue;
            }
            $map[$codMun] = $ibge;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $path = $this->codMunMapPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($map, JSON_UNESCAPED_UNICODE));
        self::$codMunMapMemo = $map;
    }

    /** @var array<string, string>|null */
    private static ?array $codMunMapMemo = null;

    /**
     * @param  list<string>  $header
     * @return array<int, int>
     */
    private function detectYearColumns(array $header): array
    {
        $years = [];
        foreach ($header as $idx => $label) {
            $y = (int) preg_replace('/\D/', '', (string) $label);
            if ($y >= 2000 && $y <= 2100) {
                $years[$y] = $idx;
            }
        }

        return $years;
    }

    private function parseBrazilianMoney(string $raw): ?float
    {
        $clean = trim($raw);
        if ($clean === '' || $clean === '-') {
            return null;
        }
        $clean = str_replace(['R$', ' '], '', $clean);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        if (! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private function nomeUfKey(string $nome, string $uf): string
    {
        return MunicipalityNomeUfKey::key($nome, $uf);
    }

    private function normalizeNome(string $nome): string
    {
        return MunicipalityNomeUfKey::normalizeNome($nome);
    }

    /**
     * @return array<string, string>
     */
    private function codMunToIbgeMap(): array
    {
        if (self::$codMunMapMemo !== null) {
            return self::$codMunMapMemo;
        }

        $path = $this->codMunMapPath();
        if (! is_readable($path)) {
            self::$codMunMapMemo = [];

            return self::$codMunMapMemo;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$codMunMapMemo = is_array($decoded) ? $decoded : [];

        return self::$codMunMapMemo;
    }

    private function codMunMapPath(): string
    {
        return storage_path('app/funding/tesouro-csv/cod_mun_to_ibge.json');
    }

    /**
     * @param  array<string, list<string>>  $keywords
     */
    private function matchProgramId(string $blob, array $keywords): string
    {
        foreach ($keywords as $programId => $terms) {
            if (! is_array($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($blob, strtolower($term))) {
                    return (string) $programId;
                }
            }
        }

        return 'geral_educacao';
    }

    private function programLabel(string $programaId): string
    {
        return match ($programaId) {
            'fundeb' => 'FUNDEB',
            'pnae' => 'PNAE',
            'pnate' => 'PNATE',
            'pdde' => 'PDDE',
            default => __('Educação / transferências'),
        };
    }

    private function resourceCachePath(string $resourceId): string
    {
        return storage_path('app/funding/tesouro-csv/'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $resourceId).'.json');
    }

    /**
     * Linhas FUNDEB nacionais a partir do índice CKAN (para mapa Horizonte).
     *
     * @return list<array{
     *   ibge_municipio: string,
     *   ano: int,
     *   fonte: string,
     *   programa_id: string,
     *   programa_label: string,
     *   valor: float,
     *   meta: array<string, mixed>
     * }>
     */
    public function nationalFundebRowsForYear(
        int $year,
        int $timeout,
        ?string $ufFilter,
        IbgeMunicipalityCatalog $catalog,
    ): array {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $resources = array_values(array_filter(
            $this->resolveCsvResources(is_array($cfg) ? $cfg : [], $timeout),
            static fn (array $r): bool => (string) ($r['programa_id'] ?? '') === 'fundeb',
        ));

        if ($resources === []) {
            return [];
        }

        $scopedUf = HorizonteUfScope::normalize($ufFilter);

        $ufs = $scopedUf !== null ? [$scopedUf] : null;

        $ibgeByNomeUf = $ufs === null
            ? $catalog->nationalNomeUfToIbgeIndex()
            : $this->nomeUfIbgeIndexForUfs($catalog, $ufs);
        $ibgeByUfNome = $this->buildIbgeByUfNome($ibgeByNomeUf);

        $rows = [];
        $codMunBatch = [];
        foreach ($resources as $resource) {
            $index = $this->normalizeStoredIndex($this->loadResourceIndex($resource, $timeout));
            $byNomeUf = $index['by_nome_uf'] ?? null;
            if (! is_array($byNomeUf)) {
                continue;
            }

            foreach ($byNomeUf as $key => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $uf = strtoupper(trim((string) ($entry['uf'] ?? '')));
                if ($scopedUf !== null && $scopedUf !== $uf) {
                    continue;
                }
                $valor = $entry['annual'][$year] ?? null;
                if ($valor === null || (float) $valor <= 0) {
                    continue;
                }

                $ibge = $this->resolveIbgeForEntry($entry, (string) $key, $ibgeByNomeUf, $ibgeByUfNome);
                if ($ibge === null) {
                    continue;
                }

                $mensal = $this->normalizeMensalMap($entry['mensal'][$year] ?? []);
                $meta = [
                    'cod_mun' => (string) ($entry['cod_mun'] ?? ''),
                    'uf' => $uf,
                    'municipio' => (string) ($entry['nome'] ?? ''),
                    'resource_id' => $resource['resource_id'],
                    'resource_name' => $resource['name'],
                    'meses_somados' => $entry['months_counted'][$year] ?? count($mensal),
                    'mensal' => $mensal,
                    'horizonte_national' => true,
                ];
                if ($mensal !== []) {
                    $meta['granularity'] = 'month';
                    $meta['repasses'] = $this->repassesFromMensal($mensal, $year);
                }

                $rows[] = [
                    'ibge_municipio' => $ibge,
                    'ano' => $year,
                    'fonte' => 'tesouro_csv',
                    'programa_id' => (string) $resource['programa_id'],
                    'programa_label' => $this->programLabel((string) $resource['programa_id']),
                    'valor' => round((float) $valor, 2),
                    'meta' => $meta,
                ];
                $codMun = trim((string) ($entry['cod_mun'] ?? ''));
                if ($codMun !== '') {
                    $codMunBatch[$codMun] = $ibge;
                }
            }
        }

        if ($codMunBatch !== []) {
            $this->rememberCodMunMappings($codMunBatch);
        }

        return $rows;
    }

    /**
     * Diagnóstico quando a importação nacional FUNDEB não produz linhas.
     *
     * @return array{
     *   message: string,
     *   csv_municipalities: int,
     *   year_values: int,
     *   ibge_index_size: int,
     *   cod_mun_mappings: int
     * }
     */
    public function diagnoseNationalFundeb(
        int $year,
        int $timeout,
        ?string $ufFilter,
        IbgeMunicipalityCatalog $catalog,
    ): array {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $resources = array_values(array_filter(
            $this->resolveCsvResources(is_array($cfg) ? $cfg : [], $timeout),
            static fn (array $r): bool => (string) ($r['programa_id'] ?? '') === 'fundeb',
        ));

        if ($resources === []) {
            return [
                'message' => __('Repasses Tesouro: recurso CSV FUNDEB não configurado (ieducar.other_funding.public_queries.tesouro_ckan.csv_resources).'),
                'csv_municipalities' => 0,
                'year_values' => 0,
                'ibge_index_size' => 0,
                'cod_mun_mappings' => 0,
            ];
        }

        $index = $this->loadResourceIndex($resources[0], $timeout);
        $byNomeUf = is_array($index['by_nome_uf'] ?? null) ? $index['by_nome_uf'] : [];
        $loadMeta = $this->lastIndexLoadMeta();
        $yearValues = 0;
        foreach ($byNomeUf as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (((float) ($entry['annual'][$year] ?? 0)) > 0) {
                $yearValues++;
            }
        }

        $scopedUf = HorizonteUfScope::normalize($ufFilter);
        $ufs = $scopedUf !== null ? [$scopedUf] : null;
        $ibgeIndex = $ufs === null
            ? $catalog->nationalNomeUfToIbgeIndex()
            : $this->nomeUfIbgeIndexForUfs($catalog, $ufs);
        $codMap = $this->codMunToIbgeMap();
        $crossMatches = $this->countIbgeCrossMatches($byNomeUf, $ibgeIndex, $year, $scopedUf);

        if ($byNomeUf === []) {
            $url = trim((string) ($resources[0]['url'] ?? ''));
            $source = (string) ($loadMeta['source'] ?? '');
            if ($source === 'http' && (int) ($loadMeta['http_code'] ?? 0) === 200) {
                $message = __('Repasses Tesouro: CSV descarregado (HTTP 200) mas sem colunas de ano ou municípios reconhecidos — verifique o formato fundeb-por-municipio.csv.');
            } elseif ($source === 'stale_cache') {
                $message = __('Repasses Tesouro: índice em cache antigo vazio — rede indisponível (:err).', [
                    'err' => (string) ($loadMeta['fetch_error'] ?? __('desconhecido')),
                ]);
            } else {
                $parts = [
                    __('Repasses Tesouro: não foi possível ler o CSV FUNDEB do CKAN (rede bloqueada, timeout ou cache vazio).'),
                ];
                if (isset($loadMeta['http_code'])) {
                    $parts[] = __('HTTP :code.', ['code' => (string) $loadMeta['http_code']]);
                }
                if (! empty($loadMeta['fetch_error'])) {
                    $parts[] = (string) $loadMeta['fetch_error'];
                }
                if (! empty($loadMeta['local_error'])) {
                    $parts[] = (string) $loadMeta['local_error'];
                }
                $parts[] = __('Teste no servidor: curl -sS -o /dev/null -w "Tesouro %%{http_code}\n" ":url"', ['url' => $url !== '' ? $url : 'https://www.tesourotransparente.gov.br/ckan']);
                $parts[] = __('Alternativa offline: descarregue fundeb-por-municipio.csv para storage/app/funding/tesouro-csv/ ou defina IEDUCAR_TESOURO_CSV_LOCAL_PATH.');
                $message = implode(' ', $parts);
            }
        } elseif ($yearValues === 0) {
            $message = __('Repasses Tesouro: CSV lido (:n municípios), mas sem valores para o ano :ano.', [
                'n' => (string) count($byNomeUf),
                'ano' => (string) $year,
            ]);
        } elseif ($ibgeIndex === [] && $codMap === []) {
            $message = __('Repasses Tesouro: CSV com :n municípios e :y com valor em :ano, mas sem índice IBGE (API servicodados.ibge.gov.br inacessível). Execute antes: php artisan horizonte:fortnightly-feed --phase=ibge_catalog', [
                'n' => (string) count($byNomeUf),
                'y' => (string) $yearValues,
                'ano' => (string) $year,
            ]);
        } else {
            $message = __('Repasses Tesouro: nenhuma linha FUNDEB cruzada com IBGE (anos :anos). CSV: :csv municípios · :y com valor em :ano · cruzamentos nome+UF: :cross · índice IBGE: :idx · cache COD_MUN: :cod. Apague storage/app/funding/tesouro-csv/*.json se actualizou o ServLITCYS.', [
                'anos' => implode(', ', array_map('strval', array_unique([$year, $year - 1, (int) date('Y')]))),
                'csv' => (string) count($byNomeUf),
                'y' => (string) $yearValues,
                'ano' => (string) $year,
                'cross' => (string) $crossMatches,
                'idx' => (string) count($ibgeIndex),
                'cod' => (string) count($codMap),
            ]);
        }

        return [
            'message' => $message,
            'csv_municipalities' => count($byNomeUf),
            'year_values' => $yearValues,
            'ibge_index_size' => count($ibgeIndex),
            'cod_mun_mappings' => count($codMap),
            'cross_matches' => $crossMatches,
        ];
    }

    /**
     * @param  list<string>  $ufs
     * @return array<string, string>
     */
    private function nomeUfIbgeIndexForUfs(IbgeMunicipalityCatalog $catalog, array $ufs): array
    {
        $index = [];
        foreach ($ufs as $uf) {
            if (! is_string($uf) || $uf === '') {
                continue;
            }
            foreach ($catalog->municipalitiesForUf($uf) as $ibge => $meta) {
                $name = trim((string) ($meta['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $key = MunicipalityNomeUfKey::key($name, $uf);
                if ($key !== '') {
                    $index[$key] = (string) $ibge;
                }
            }
        }

        return $index;
    }

    private function resolveIbgeFromCodMun(string $codMun): ?string
    {
        $codMun = trim($codMun);
        if ($codMun === '') {
            return null;
        }

        $map = $this->codMunToIbgeMap();

        return $map[$codMun] ?? null;
    }
}
