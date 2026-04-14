<?php

namespace App\Services\Inep;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $url = (string) config(
            'ieducar.inep_geocoding.arcgis_layer_query_url',
            'https://services3.arcgis.com/ba17q0p2zHwzRK3B/arcgis/rest/services/inep_escolas_fmt_250609_geocode/FeatureServer/1/query'
        );
        $batchSize = max(5, min(100, (int) config('ieducar.inep_geocoding.batch_size', 40)));
        $ttl = max(3600, (int) config('ieducar.inep_geocoding.cache_ttl_seconds', 2592000));
        $missTtl = min(86400, $ttl);

        $out = [];
        foreach (array_chunk($normalized, $batchSize) as $chunk) {
            $toFetch = [];
            foreach ($chunk as $code) {
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

            $fetched = $this->fetchFromArcgis($url, $toFetch);
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
        // Alguns serviços ArcGIS variam o nome do campo (com/sem acento). Tentamos o mais comum.
        $whereCandidates = [
            'Código_INEP IN ('.$inList.')',
            'Codigo_INEP IN ('.$inList.')',
            'CODIGO_INEP IN ('.$inList.')',
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
}
