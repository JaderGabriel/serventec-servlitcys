<?php

namespace App\Services\Fundeb;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importa VAAF/VAAT/complementação a partir de API CKAN (FNDE/dados abertos) ou JSON configurável.
 * Persiste em fundeb_municipio_references (por IBGE + ano + city_id).
 */
final class FundebOpenDataImportService
{
    public function __construct(
        private FundebMunicipioReferenceRepository $references,
    ) {}

    /**
     * @return array{success: bool, message: string, reference?: array<string, mixed>}
     */
    public function importForCityYear(City $city, int $ano): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [
                'success' => false,
                'message' => __('Cadastre o código IBGE do município (7 dígitos) na ficha da cidade.'),
            ];
        }

        if ($ano < 2000 || $ano > (int) date('Y') + 1) {
            return [
                'success' => false,
                'message' => __('Ano inválido.'),
            ];
        }

        $row = $this->fetchRow($ibge, $ano);
        if ($row === null) {
            return [
                'success' => false,
                'message' => __('Nenhum registo VAAF/VAAT encontrado na API para IBGE :ibge e ano :ano. Verifique IEDUCAR_FUNDEB_CKAN_RESOURCE_ID ou o conjunto de dados FNDE.', [
                    'ibge' => $ibge,
                    'ano' => (string) $ano,
                ]),
            ];
        }

        $vaaf = (float) ($row['vaaf'] ?? 0);
        if ($vaaf <= 0) {
            return [
                'success' => false,
                'message' => __('Registo encontrado, mas VAAF inválido ou ausente.'),
            ];
        }

        $model = $this->references->upsert($city, $ano, [
            'vaaf' => $vaaf,
            'vaat' => isset($row['vaat']) ? (float) $row['vaat'] : null,
            'complementacao_vaar' => isset($row['complementacao_vaar']) ? (float) $row['complementacao_vaar'] : null,
            'fonte' => (string) ($row['fonte'] ?? 'api_ckan_fnde'),
            'notas' => isset($row['notas']) ? (string) $row['notas'] : null,
        ]);

        return [
            'success' => true,
            'message' => __('VAAF :vaaf gravado para :ano (fonte: :fonte).', [
                'vaaf' => number_format($vaaf, 2, ',', '.'),
                'ano' => (string) $ano,
                'fonte' => $model->fonte,
            ]),
            'reference' => [
                'ano' => $model->ano,
                'vaaf' => (float) $model->vaaf,
                'vaat' => $model->vaat !== null ? (float) $model->vaat : null,
                'complementacao_vaar' => $model->complementacao_vaar !== null ? (float) $model->complementacao_vaar : null,
                'fonte' => $model->fonte,
                'imported_at' => $model->imported_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchRow(string $ibge, int $ano): ?array
    {
        $jsonUrl = trim((string) config('ieducar.fundeb.open_data.json_url', ''));
        if ($jsonUrl !== '') {
            $row = $this->fetchFromJsonUrl($jsonUrl, $ibge, $ano);
            if ($row !== null) {
                return $row;
            }
        }

        return $this->fetchFromCkan($ibge, $ano);
    }

    /**
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchFromCkan(string $ibge, int $ano): ?array
    {
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $resourceId = trim((string) config('ieducar.fundeb.open_data.resource_id', ''));
        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));

        if ($resourceId === '') {
            $resourceId = $this->discoverResourceId($base, $timeout);
        }

        if ($resourceId === '') {
            return null;
        }

        $ibgeFields = config('ieducar.fundeb.open_data.fields.ibge', []);
        $anoFields = config('ieducar.fundeb.open_data.fields.ano', []);

        foreach (is_array($ibgeFields) ? $ibgeFields : [] as $ibgeField) {
            foreach (is_array($anoFields) ? $anoFields : [] as $anoField) {
                $filters = json_encode([
                    $ibgeField => $ibge,
                    $anoField => $ano,
                ], JSON_THROW_ON_ERROR);

                $records = $this->ckanDatastoreSearch($base, $resourceId, $filters, $timeout, 5);
                $parsed = $this->parseFirstRecord($records, $ibge, $ano);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        $records = $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, 5000);
        foreach ($records as $record) {
            $parsed = $this->mapRecord($record, $ibge, $ano);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        $records = $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, 5000, $ibge);
        foreach ($records as $record) {
            $parsed = $this->mapRecord($record, $ibge, $ano);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ckanDatastoreSearch(
        string $base,
        string $resourceId,
        ?string $filters,
        int $timeout,
        int $limit,
        ?string $q = null,
    ): array {
        $query = [
            'resource_id' => $resourceId,
            'limit' => $limit,
        ];
        if ($filters !== null) {
            $query['filters'] = $filters;
        }
        if ($q !== null && $q !== '') {
            $query['q'] = $q;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($base.'/api/3/action/datastore_search', $query);

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        if (! is_array($payload) || ! ($payload['success'] ?? false)) {
            return [];
        }

        $records = $payload['result']['records'] ?? [];

        return is_array($records) ? $records : [];
    }

    private function discoverResourceId(string $base, int $timeout): string
    {
        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($base.'/api/3/action/package_search', [
                'q' => (string) config('ieducar.fundeb.open_data.search_query', 'fundeb vaaf municipio'),
                'rows' => 5,
            ]);

        if (! $response->successful()) {
            return '';
        }

        $results = $response->json('result.results') ?? [];
        if (! is_array($results)) {
            return '';
        }

        foreach ($results as $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $resources = $pkg['resources'] ?? [];
            if (! is_array($resources)) {
                continue;
            }
            foreach ($resources as $res) {
                if (! is_array($res)) {
                    continue;
                }
                $id = (string) ($res['id'] ?? '');
                $format = strtolower((string) ($res['format'] ?? ''));
                if ($id !== '' && in_array($format, ['csv', 'xlsx', 'json', ''], true)) {
                    return $id;
                }
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function parseFirstRecord(array $records, string $ibge, int $ano): ?array
    {
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $mapped = $this->mapRecord($record, $ibge, $ano);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function mapRecord(array $record, string $ibge, int $ano): ?array
    {
        $normalized = [];
        foreach ($record as $key => $value) {
            $normalized[Str::lower((string) $key)] = $value;
        }

        $rowIbge = preg_replace('/\D/', '', (string) $this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.ibge', [])));
        if (strlen($rowIbge) !== 7 || $rowIbge !== $ibge) {
            return null;
        }

        $rowAno = (int) preg_replace('/\D/', '', (string) $this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.ano', [])));
        if ($rowAno !== $ano) {
            return null;
        }

        $vaaf = $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.vaaf', [])));
        if ($vaaf === null || $vaaf <= 0) {
            return null;
        }

        return array_filter([
            'vaaf' => $vaaf,
            'vaat' => $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.vaat', []))),
            'complementacao_vaar' => $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.complementacao_vaar', []))),
            'fonte' => 'api_ckan_fnde',
            'notas' => __('Importado via CKAN em :date', ['date' => now()->format('Y-m-d H:i')]),
        ], static fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $candidates
     */
    private function firstValue(array $row, array $candidates): mixed
    {
        foreach ($candidates as $key) {
            $k = Str::lower($key);
            if (array_key_exists($k, $row)) {
                return $row[$k];
            }
        }

        return null;
    }

    /**
     * URL JSON: array de objetos ou { "records": [...] } com placeholders {ibge} {ano}
     *
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchFromJsonUrl(string $urlTemplate, string $ibge, int $ano): ?array
    {
        $url = str_replace(['{ibge}', '{ano}'], [$ibge, (string) $ano], $urlTemplate);
        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));

        $response = Http::timeout($timeout)->acceptJson()->get($url);
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $records = is_array($data['records'] ?? null) ? $data['records'] : (is_array($data) && array_is_list($data) ? $data : []);

        return $this->parseFirstRecord($records, $ibge, $ano);
    }

    private function parseMoney(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $s = trim((string) $raw);
        $s = str_replace(['R$', ' '], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
