<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Repasses do pacote CKAN Tesouro Transparente (CSV por município).
 * O CSV usa COD_MUN interno; a correspondência com IBGE é por nome+UF (e cache cod_mun→IBGE).
 */
final class TesouroTransferenciasCsvService
{
    private const INDEX_SCHEMA_VERSION = 2;
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
            $rows[] = [
                'ibge_municipio' => $ibge,
                'ano' => $year,
                'fonte' => 'tesouro_csv',
                'programa_id' => $programaId,
                'programa_label' => $this->programLabel($programaId),
                'valor' => round($valor, 2),
                'meta' => [
                    'cod_mun' => $match['cod_mun'],
                    'uf' => $match['uf'],
                    'municipio' => $match['nome'],
                    'resource_id' => $resource['resource_id'],
                    'resource_name' => $resource['name'],
                    'meses_somados' => $match['months_counted'][$year] ?? 0,
                    'mensal' => $match['mensal'][$year] ?? [],
                ],
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
     * @param  array{resource_id: string, name: string, url: string, programa_id: string}  $resource
     * @return array{
     *   by_nome_uf: array<string, array{cod_mun: string, nome: string, uf: string, annual: array<int, float>, months_counted: array<int, int>}>,
     *   year_columns: array<int, int>
     * }
     */
    private function loadResourceIndex(array $resource, int $timeout): array
    {
        $cachePath = $this->resourceCachePath($resource['resource_id']);
        if (is_readable($cachePath)) {
            $decoded = json_decode((string) file_get_contents($cachePath), true);
            if ($this->isIndexCacheValid($decoded)) {
                return $decoded;
            }
        }

        $url = $resource['url'];
        if ($url === '') {
            return [];
        }

        try {
            $response = Http::timeout(max($timeout, 30))
                ->withHeaders(['User-Agent' => 'Servlitcys-TesouroCSV/1.0'])
                ->withOptions(['allow_redirects' => true])
                ->get($url);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $body = $response->body();
        $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'ISO-8859-1';
        if ($encoding !== 'UTF-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $encoding);
        }

        $index = $this->parseCsvBody($body);
        if ($index !== []) {
            $index['_schema'] = self::INDEX_SCHEMA_VERSION;
            $dir = dirname($cachePath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($cachePath, json_encode($index, JSON_UNESCAPED_UNICODE));
            cache()->put('tesouro_csv_resource_url_'.$resource['resource_id'], $resource['url'], 604800);
        }

        return $index;
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

        return [
            'by_nome_uf' => $byNomeUf,
            'year_columns' => $yearColumns,
        ];
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
        $map = cache()->get('tesouro_cod_mun_to_ibge');
        if (! is_array($map)) {
            return null;
        }

        foreach ($map as $cod => $mappedIbge) {
            if ((string) $mappedIbge === $ibge) {
                return (string) $cod;
            }
        }

        return null;
    }

    public function rememberCodMunMapping(string $codMun, string $ibge): void
    {
        $map = cache()->get('tesouro_cod_mun_to_ibge');
        if (! is_array($map)) {
            $map = [];
        }
        $map[$codMun] = $ibge;
        cache()->put('tesouro_cod_mun_to_ibge', $map, 604800);
    }

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
        $nome = $this->normalizeNome($nome);
        $uf = strtoupper(trim($uf));

        return $nome !== '' && $uf !== '' ? $nome.'|'.$uf : '';
    }

    private function normalizeNome(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        if ($nome === '') {
            return '';
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if (is_string($ascii) && $ascii !== '') {
            $nome = $ascii;
        }
        $nome = preg_replace('/[^a-z0-9\s]/', '', $nome) ?? $nome;

        return trim(preg_replace('/\s+/', ' ', $nome) ?? $nome);
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
}
