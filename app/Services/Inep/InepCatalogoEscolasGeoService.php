<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Coordenadas e metadados do Catálogo de Escolas (INEP/MEC) via serviço ArcGIS público.
 *
 * Nota: a camada ArcGIS não inclui o valor numérico do IDEB; esse indicador é obtido
 * em portais como o QEdu (link gerado no repositório do mapa por código INEP).
 *
 * @see https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/inep-data/catalogo-de-escolas
 */
class InepCatalogoEscolasGeoService
{
    /**
     * Ordem e rótulos de exibição para atributos ArcGIS (chaves como no serviço).
     *
     * @return list<array{field: string, label: string}>
     */
    private function catalogFieldSchema(): array
    {
        return [
            ['field' => 'Escola', 'label' => __('Nome (Catálogo INEP)')],
            ['field' => 'Código_INEP', 'label' => __('Código INEP')],
            ['field' => 'UF', 'label' => __('UF')],
            ['field' => 'Município', 'label' => __('Município')],
            ['field' => 'Dependência_Administrativa', 'label' => __('Dependência administrativa')],
            ['field' => 'Categoria_Administrativa', 'label' => __('Categoria administrativa')],
            ['field' => 'Etapas_e_Modalidade_de_Ensino_O', 'label' => __('Etapas e modalidades oferecidas')],
            ['field' => 'Porte_da_Escola', 'label' => __('Porte da escola')],
            ['field' => 'Localização', 'label' => __('Localização (urbana/rural)')],
            ['field' => 'Localidade_Diferenciada', 'label' => __('Localidade diferenciada')],
            ['field' => 'Endereço', 'label' => __('Endereço (Catálogo INEP)')],
            ['field' => 'Telefone', 'label' => __('Telefone (Catálogo INEP)')],
            ['field' => 'Restrição_de_Atendimento', 'label' => __('Restrição de atendimento')],
            ['field' => 'Categoria_Escola_Privada', 'label' => __('Categoria escola privada')],
            ['field' => 'Conveniada_Poder_Público', 'label' => __('Conveniada poder público')],
            ['field' => 'Regulamentação_pelo_Conselho_de', 'label' => __('Regulamentação pelo conselho de educação')],
            ['field' => 'Outras_Ofertas_Educacionais', 'label' => __('Outras ofertas educacionais')],
            ['field' => 'Coordenadas', 'label' => __('Coordenadas declaradas no catálogo')],
            ['field' => 'Latitude', 'label' => __('Latitude')],
            ['field' => 'Longitude', 'label' => __('Longitude')],
        ];
    }

    /**
     * @param  list<int|string>  $codes  Códigos INEP (8 dígitos habituais)
     * @return array<int, array{
     *   lat: float,
     *   lng: float,
     *   nome_inep: string,
     *   catalog: list<array{field: string, label: string, value: string}>,
     *   catalog_assoc: array<string, string>
     * }>
     */
    public function lookupByInepCodes(array $codes): array
    {
        if (! filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $normalized = [];
        foreach ($codes as $c) {
            $n = $this->normalizeInepCode($c);
            if ($n !== null) {
                $normalized[] = $n;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return [];
        }

        // Fonte local (legada): `inep_school_geos` pode não existir em produção (foi descontinuada).
        // Se existir, usa como cache offline; se não existir, ignora e segue para ArcGIS/Redis cache.
        $local = collect();
        try {
            $conn = DB::connection();
            $has = $conn->getSchemaBuilder()->hasTable('inep_school_geos');
            if ($has) {
                $rows = $conn->table('inep_school_geos')
                    ->whereIn('inep_code', $normalized)
                    ->get()
                    ->keyBy('inep_code');
                $local = $rows;
            }
        } catch (\Throwable $e) {
            Log::debug('INEP local table lookup skipped', ['message' => $e->getMessage()]);
            $local = collect();
        }

        $urls = config('ieducar.inep_geocoding.arcgis_layer_query_urls');
        if (! is_array($urls) || $urls === []) {
            $urls = [
                (string) config(
                    'ieducar.inep_geocoding.arcgis_layer_query_url',
                    'https://services3.arcgis.com/ba17q0p2zHwzRK3B/arcgis/rest/services/inep_escolas_fmt_250609_geocode/FeatureServer/1/query'
                ),
            ];
        }
        $urls = array_values(array_filter(array_map(static fn ($u) => is_string($u) ? trim($u) : '', $urls)));
        if ($urls === []) {
            return [];
        }
        $batchSize = max(5, min(100, (int) config('ieducar.inep_geocoding.batch_size', 40)));
        $ttl = max(3600, (int) config('ieducar.inep_geocoding.cache_ttl_seconds', 2592000));
        $missTtl = min(86400, $ttl);

        $out = [];

        foreach ($normalized as $code) {
            $row = $local->get($code);
            $lat = is_object($row) ? ($row->lat ?? null) : null;
            $lng = is_object($row) ? ($row->lng ?? null) : null;
            if (is_numeric($lat) && is_numeric($lng) && $this->validCoord((float) $lat, (float) $lng)) {
                $payload = null;
                if (is_object($row)) {
                    $payload = $row->payload ?? null;
                }
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = is_array($decoded) ? $decoded : null;
                }
                $out[$code] = [
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'nome_inep' => is_array($payload) ? (string) (($payload['Escola'] ?? '') ?: ($payload['nome'] ?? '')) : '',
                    'catalog' => [],
                    'catalog_assoc' => is_array($payload) ? $payload : [],
                ];
            }
        }

        // Fallback CSV (só INEPs já presentes em school_unit_geos das cidades forAnalytics — ver export).
        foreach ($this->coordsFromCsvFallback($normalized) as $code => $hit) {
            if (! isset($out[$code])) {
                $out[$code] = $hit;
            }
        }

        foreach (array_chunk($normalized, $batchSize) as $chunk) {
            $toFetch = [];
            foreach ($chunk as $code) {
                if (isset($out[$code])) {
                    continue;
                }
                $cached = Cache::get($this->cacheKey($code));
                if (is_array($cached) && empty($cached['miss'])) {
                    $row = $this->hydrateFromCache($cached);
                    if ($row !== null) {
                        $out[$code] = $row;

                        continue;
                    }
                }
                // Cache miss, falha anterior ou coordenadas inválidas no cache: voltar a pedir ao ArcGIS
                // (não bloquear indefinidamente com «miss» — evita mapa vazio quando a API já devolve o INEP).
                $toFetch[] = $code;
            }
            if ($toFetch === []) {
                continue;
            }

            $fetched = [];
            foreach ($urls as $url) {
                $fetched = $this->fetchFromArcgis($url, $toFetch);
                if ($fetched !== []) {
                    break;
                }
            }
            foreach ($toFetch as $code) {
                if (isset($fetched[$code])) {
                    $row = $fetched[$code];
                    Cache::put($this->cacheKey($code), $row, $ttl);
                    $out[$code] = $row;
                } else {
                    // Falha transitória ou escola fora da camada: TTL curto para permitir retry sem martelar a API
                    Cache::put($this->cacheKey($code), ['miss' => true], $missTtl);
                }
            }
        }

        return $out;
    }

    public function cacheKey(int $code): string
    {
        return 'inep_geo_v2_'.$code;
    }

    /**
     * Diagnóstico read-only: percorre cada fallback (tabela legada, cache, ArcGIS por URL e variantes de WHERE)
     * sem gravar cache nem alterar dados. Útil para `php artisan app:probe-inep-geo-fallbacks`.
     *
     * @param  list<int|string>  $codes
     * @return array<string, mixed>
     */
    public function diagnoseInepGeocodingFallbacks(array $codes): array
    {
        $normalized = [];
        foreach ($codes as $c) {
            $n = $this->normalizeInepCode($c);
            if ($n !== null) {
                $normalized[] = $n;
            }
        }
        $normalized = array_values(array_unique($normalized));

        $out = [
            'inep_geocoding_enabled' => filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN),
            'codes_normalized' => $normalized,
            'fallback_1_local_table_inep_school_geos' => [
                'table' => 'inep_school_geos',
                'exists' => false,
                'rows' => [],
                'error' => null,
            ],
            'fallback_2_redis_cache' => [],
            'fallback_2b_csv_local_scope' => [],
            'fallback_3_arcgis' => [],
            'merged_like_lookup' => [
                'note' => 'Simulação: inep_school_geos ∪ CSV (escopo local) ∪ Redis ∪ ArcGIS.',
                'would_resolve' => [],
            ],
        ];

        try {
            $conn = DB::connection();
            $has = $conn->getSchemaBuilder()->hasTable('inep_school_geos');
            $out['fallback_1_local_table_inep_school_geos']['exists'] = $has;
            if ($has && $normalized !== []) {
                $rows = $conn->table('inep_school_geos')
                    ->whereIn('inep_code', $normalized)
                    ->get();
                foreach ($rows as $row) {
                    $ic = is_object($row) ? ($row->inep_code ?? null) : null;
                    $lat = is_object($row) ? ($row->lat ?? null) : null;
                    $lng = is_object($row) ? ($row->lng ?? null) : null;
                    $ok = is_numeric($lat) && is_numeric($lng) && $this->validCoord((float) $lat, (float) $lng);
                    $out['fallback_1_local_table_inep_school_geos']['rows'][(int) $ic] = [
                        'valid_coords' => $ok,
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $out['fallback_1_local_table_inep_school_geos']['error'] = $e->getMessage();
        }

        foreach ($normalized as $code) {
            $k = $this->cacheKey($code);
            $raw = Cache::get($k);
            $summary = [
                'cache_key' => $k,
                'present' => $raw !== null,
            ];
            if (is_array($raw)) {
                $summary['is_miss_marker'] = ! empty($raw['miss']);
                $summary['has_lat_lng'] = isset($raw['lat'], $raw['lng']);
                if ($summary['has_lat_lng']) {
                    $summary['valid_coords'] = $this->validCoord((float) $raw['lat'], (float) $raw['lng']);
                }
            } else {
                $summary['is_miss_marker'] = false;
                $summary['has_lat_lng'] = false;
            }
            $out['fallback_2_redis_cache'][(string) $code] = $summary;
        }

        $csvRel = (string) config('ieducar.inep_geocoding.fallback_csv_path', 'app/inep_geo_fallback.csv');
        $csvPath = str_starts_with($csvRel, '/') ? $csvRel : storage_path($csvRel);
        $csvHits = $this->coordsFromCsvFallback($normalized);
        $out['fallback_2b_csv_local_scope'] = [
            'enabled' => filter_var(config('ieducar.inep_geocoding.fallback_csv_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'path' => $csvPath,
            'readable' => is_readable($csvPath),
            'inep_hits' => array_keys($csvHits),
            'count' => count($csvHits),
        ];

        $urls = config('ieducar.inep_geocoding.arcgis_layer_query_urls');
        if (! is_array($urls) || $urls === []) {
            $urls = [
                (string) config(
                    'ieducar.inep_geocoding.arcgis_layer_query_url',
                    'https://services3.arcgis.com/ba17q0p2zHwzRK3B/arcgis/rest/services/inep_escolas_fmt_250609_geocode/FeatureServer/1/query'
                ),
            ];
        }
        $urls = array_values(array_filter(array_map(static fn ($u) => is_string($u) ? trim($u) : '', $urls)));

        $inList = implode(',', array_map(static fn (int $i) => (string) $i, array_map('intval', $normalized)));
        $whereCandidates = [
            '"Código_INEP" IN ('.$inList.')',
            '"Codigo_INEP" IN ('.$inList.')',
            '"CODIGO_INEP" IN ('.$inList.')',
        ];

        $queryParamsBase = [
            'outFields' => implode(',', [
                'Escola',
                'Código_INEP',
                'Codigo_INEP',
                'UF',
                'Município',
                'Municipio',
                'Latitude',
                'Longitude',
            ]),
            'f' => 'json',
            'returnGeometry' => 'true',
            'outSR' => 4326,
        ];

        foreach ($urls as $idx => $url) {
            $urlEntry = [
                'index' => $idx,
                'url' => $url,
                'where_attempts' => [],
                'fetch_parsed_hits' => [],
            ];
            foreach ($whereCandidates as $where) {
                try {
                    $response = Http::timeout(25)
                        ->acceptJson()
                        ->get($url, array_merge($queryParamsBase, ['where' => $where]));
                    $status = $response->status();
                    $json = $response->json();
                    $featCount = is_array($json) && isset($json['features']) && is_array($json['features'])
                        ? count($json['features'])
                        : 0;
                    $errMsg = null;
                    if (is_array($json) && isset($json['error'])) {
                        $err = $json['error'];
                        $errMsg = is_array($err) ? (string) ($err['message'] ?? json_encode($err)) : (string) $err;
                    }
                    $sampleIneps = [];
                    if (is_array($json['features'] ?? null)) {
                        foreach (array_slice($json['features'], 0, 5) as $f) {
                            $attrs = is_array($f['attributes'] ?? null) ? $f['attributes'] : [];
                            $ci = (int) ($attrs['Código_INEP'] ?? ($attrs['Codigo_INEP'] ?? 0));
                            if ($ci > 0) {
                                $sampleIneps[] = $ci;
                            }
                        }
                    }
                    $urlEntry['where_attempts'][] = [
                        'where' => $where,
                        'http_status' => $status,
                        'ok' => $response->successful(),
                        'arcgis_error' => $errMsg,
                        'feature_count' => $featCount,
                        'sample_inep_from_features' => $sampleIneps,
                        'exceeded_transfer_limit' => $json['exceededTransferLimit'] ?? null,
                    ];
                } catch (\Throwable $e) {
                    $urlEntry['where_attempts'][] = [
                        'where' => $where,
                        'exception' => $e->getMessage(),
                    ];
                }
            }
            // Mesma lógica de parse que lookupByInepCodes (sem escrever cache).
            $urlEntry['fetch_parsed_hits'] = $this->fetchFromArcgis($url, $normalized);
            $out['fallback_3_arcgis'][] = $urlEntry;
        }

        $resolved = [];
        foreach ($normalized as $code) {
            $source = null;
            $rowsLocal = $out['fallback_1_local_table_inep_school_geos']['rows'] ?? [];
            $row = $rowsLocal[$code] ?? $rowsLocal[(string) $code] ?? null;
            if (is_array($row) && ! empty($row['valid_coords'])) {
                $source = 'inep_school_geos';
            } elseif (isset($csvHits[$code])) {
                $source = 'csv_fallback_local_scope';
            } elseif (($out['fallback_2_redis_cache'][(string) $code]['has_lat_lng'] ?? false)
                && ($out['fallback_2_redis_cache'][(string) $code]['valid_coords'] ?? false)
                && empty($out['fallback_2_redis_cache'][(string) $code]['is_miss_marker'])) {
                $source = 'redis_cache';
            } else {
                foreach ($out['fallback_3_arcgis'] as $arc) {
                    $hits = $arc['fetch_parsed_hits'] ?? [];
                    if (isset($hits[$code])) {
                        $source = 'arcgis:'.Str::limit((string) ($arc['url'] ?? ''), 80);

                        break;
                    }
                }
            }
            $resolved[(string) $code] = $source ?? 'none';
        }
        $out['merged_like_lookup']['would_resolve'] = $resolved;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return ?array{
     *   lat: float,
     *   lng: float,
     *   nome_inep: string,
     *   catalog: list<array{field: string, label: string, value: string}>,
     *   catalog_assoc: array<string, string>
     * }
     */
    private function hydrateFromCache(array $cached): ?array
    {
        if (isset($cached['lat'], $cached['lng']) && $this->validCoord((float) $cached['lat'], (float) $cached['lng'])) {
            return [
                'lat' => (float) $cached['lat'],
                'lng' => (float) $cached['lng'],
                'nome_inep' => (string) ($cached['nome_inep'] ?? ''),
                'catalog' => is_array($cached['catalog'] ?? null) ? $cached['catalog'] : [],
                'catalog_assoc' => is_array($cached['catalog_assoc'] ?? null) ? $cached['catalog_assoc'] : [],
            ];
        }

        return null;
    }

    private function normalizeInepCode(mixed $c): ?int
    {
        if ($c === null || $c === '') {
            return null;
        }
        if (is_int($c) || is_float($c)) {
            $n = (int) $c;

            return $n > 0 ? $n : null;
        }
        $digits = preg_replace('/\D/', '', (string) $c);
        if ($digits === '' || $digits === '0') {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @param  list<int>  $codes
     * @return array<int, array{
     *   lat: float,
     *   lng: float,
     *   nome_inep: string,
     *   catalog: list<array{field: string, label: string, value: string}>,
     *   catalog_assoc: array<string, string>
     * }>
     */
    private function fetchFromArcgis(string $url, array $codes): array
    {
        $inList = implode(',', array_map(static fn (int $i) => (string) $i, array_map('intval', $codes)));
        // Observação: alguns endpoints Aceitam o campo apenas quando citado (aspas duplas).
        // Sem isso, é comum responder "Invalid query parameters".
        $whereCandidates = [
            '"Código_INEP" IN ('.$inList.')',
            '"Codigo_INEP" IN ('.$inList.')',
            '"CODIGO_INEP" IN ('.$inList.')',
        ];

        try {
            $data = null;
            $lastStatus = null;
            foreach ($whereCandidates as $where) {
                $response = Http::timeout(25)
                    ->acceptJson()
                    ->get($url, [
                        'where' => $where,
                        // Evita payload gigante; ainda assim traz campos do popup.
                        'outFields' => implode(',', [
                            'Escola',
                            'Código_INEP',
                            'Codigo_INEP',
                            'UF',
                            'Município',
                            'Municipio',
                            'Dependência_Administrativa',
                            'Categoria_Administrativa',
                            'Etapas_e_Modalidade_de_Ensino_O',
                            'Porte_da_Escola',
                            'Localização',
                            'Localizacao',
                            'Localidade_Diferenciada',
                            'Endereço',
                            'Endereco',
                            'Telefone',
                            'Coordenadas',
                            'Latitude',
                            'Longitude',
                        ]),
                        'f' => 'json',
                        // Quando Latitude/Longitude não vêm como atributos, a geometria costuma ter (x,y).
                        'returnGeometry' => 'true',
                        'outSR' => 4326,
                    ]);

                $lastStatus = $response->status();
                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json();
                if (is_array($data) && isset($data['error'])) {
                    $msg = is_array($data['error']) ? ($data['error']['message'] ?? null) : null;
                    Log::warning('INEP ArcGIS geocode: erro no payload', ['message' => $msg, 'where' => $where]);
                    $data = null;

                    continue;
                }

                break;
            }

            if (! is_array($data)) {
                Log::warning('INEP ArcGIS geocode: HTTP não OK', ['status' => $lastStatus]);

                return [];
            }

            $features = is_array($data['features'] ?? null) ? $data['features'] : [];
            if ($features === []) {
                Log::info('INEP ArcGIS geocode: 0 features', [
                    'url' => $url,
                    'requested_count' => count($codes),
                ]);
            }
            $out = [];
            foreach ($features as $f) {
                $attrs = is_array($f['attributes'] ?? null) ? $f['attributes'] : [];
                $code = (int) ($attrs['Código_INEP'] ?? ($attrs['Codigo_INEP'] ?? 0));
                if ($code <= 0) {
                    continue;
                }
                $latAttr = $attrs['Latitude'] ?? null;
                $lngAttr = $attrs['Longitude'] ?? null;
                $lat = is_numeric($latAttr) ? (float) $latAttr : 0.0;
                $lng = is_numeric($lngAttr) ? (float) $lngAttr : 0.0;

                if (! $this->validCoord($lat, $lng)) {
                    $geom = is_array($f['geometry'] ?? null) ? $f['geometry'] : [];
                    $x = $geom['x'] ?? null;
                    $y = $geom['y'] ?? null;
                    if (is_numeric($x) && is_numeric($y)) {
                        // ArcGIS costuma devolver x=longitude, y=latitude.
                        $lng = (float) $x;
                        $lat = (float) $y;
                    }
                }

                if (! $this->validCoord($lat, $lng)) {
                    continue;
                }
                [$catalog, $catalogAssoc] = $this->buildCatalogFromAttributes($attrs);
                $out[$code] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'nome_inep' => (string) ($attrs['Escola'] ?? ''),
                    'catalog' => $catalog,
                    'catalog_assoc' => $catalogAssoc,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('INEP ArcGIS geocode: excepção', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array{0: list<array{field: string, label: string, value: string}>, 1: array<string, string>}
     */
    private function buildCatalogFromAttributes(array $attrs): array
    {
        $rows = [];
        $assoc = [];
        foreach ($this->catalogFieldSchema() as $def) {
            $field = $def['field'];
            if (! array_key_exists($field, $attrs)) {
                continue;
            }
            $raw = $attrs[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_float($raw) || is_int($raw)) {
                $str = (string) $raw;
            } else {
                $str = trim((string) $raw);
            }
            if ($str === '') {
                continue;
            }
            $rows[] = [
                'field' => $field,
                'label' => $def['label'],
                'value' => $str,
            ];
            $assoc[$field] = $str;
        }

        return [$rows, $assoc];
    }

    private function validCoord(float $lat, float $lng): bool
    {
        if (abs($lat) < 0.01 && abs($lng) < 0.01) {
            return false;
        }

        return abs($lat) <= 90 && abs($lng) <= 180;
    }

    /**
     * INEPs permitidos no CSV: apenas os que existem em school_unit_geos para cidades forAnalytics.
     *
     * @return array<int, true>
     */
    private function allowedInepWhitelistFromLocalSchools(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cityIds = City::query()->forAnalytics()->pluck('id');
        $cache = SchoolUnitGeo::query()
            ->whereIn('city_id', $cityIds)
            ->whereNotNull('inep_code')
            ->where('inep_code', '>', 0)
            ->distinct()
            ->pluck('inep_code')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->flip()
            ->all();

        return $cache;
    }

    /**
     * Lê `ieducar.inep_geocoding.fallback_csv_path` (storage) e devolve hits só para INEP na whitelist local.
     *
     * @param  list<int>  $normalizedCodes
     * @return array<int, array{lat: float, lng: float, nome_inep: string, catalog: array, catalog_assoc: array}>
     */
    private function coordsFromCsvFallback(array $normalizedCodes): array
    {
        if (! filter_var(config('ieducar.inep_geocoding.fallback_csv_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $rel = (string) config('ieducar.inep_geocoding.fallback_csv_path', 'app/inep_geo_fallback.csv');
        $path = str_starts_with($rel, '/') ? $rel : storage_path($rel);
        if (! is_readable($path)) {
            return [];
        }

        $whitelist = $this->allowedInepWhitelistFromLocalSchools();
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }

        $delimiter = ';';
        $header = fgetcsv($fh, 0, $delimiter);
        if ($header === false) {
            fclose($fh);

            return [];
        }

        $map = [];
        foreach ($header as $i => $h) {
            $map[mb_strtolower(trim((string) $h))] = $i;
        }
        if (! isset($map['inep_code']) || count($header) < 2) {
            rewind($fh);
            $delimiter = ',';
            $header = fgetcsv($fh, 0, $delimiter);
            $map = [];
            if (is_array($header)) {
                foreach ($header as $i => $h) {
                    $map[mb_strtolower(trim((string) $h))] = $i;
                }
            }
        }
        if (! isset($map['inep_code'])) {
            fclose($fh);

            return [];
        }

        $wanted = [];
        foreach ($normalizedCodes as $c) {
            if (isset($whitelist[$c])) {
                $wanted[$c] = true;
            }
        }
        if ($wanted === []) {
            fclose($fh);

            return [];
        }

        $out = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $inepRaw = $row[$map['inep_code']] ?? '';
            if (! is_numeric($inepRaw)) {
                continue;
            }
            $inep = (int) $inepRaw;
            if ($inep <= 0 || ! isset($wanted[$inep]) || isset($out[$inep])) {
                continue;
            }

            $lat = $this->csvPickFloat($row, $map, ['official_lat', 'lat']);
            $lng = $this->csvPickFloat($row, $map, ['official_lng', 'lng']);
            if ($lat === null || $lng === null || ! $this->validCoord($lat, $lng)) {
                continue;
            }

            $out[$inep] = [
                'lat' => $lat,
                'lng' => $lng,
                'nome_inep' => '',
                'catalog' => [],
                'catalog_assoc' => ['Fonte' => 'CSV fallback (escopo local)'],
            ];
        }
        fclose($fh);

        return $out;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    private function csvPickFloat(array $row, array $map, array $keys): ?float
    {
        foreach ($keys as $k) {
            $k = mb_strtolower($k);
            if (! isset($map[$k])) {
                continue;
            }
            $v = trim((string) ($row[$map[$k]] ?? ''));
            if ($v === '' || $v === 'null') {
                continue;
            }
            if (is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }
}
