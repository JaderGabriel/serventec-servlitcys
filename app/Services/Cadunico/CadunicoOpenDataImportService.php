<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Cadunico\CadunicoStoragePaths;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importação CadÚnico/Cecad: API configurável → cache local → CSV em storage (último recurso).
 */
final class CadunicoOpenDataImportService
{
    public function __construct(
        private CadunicoMunicipioSnapshotRepository $repository,
        private CadunicoCecadCsvImportService $csvImport,
        private CadunicoSagiMisocialClient $misocial,
        private CadunicoCkanDiscovery $ckanDiscovery,
    ) {}

    public static function suggestedImportYear(): int
    {
        return max(2000, (int) date('Y') - 1);
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     source: string|null,
     *     attempts: list<string>,
     *     imported?: int
     * }
     */
    public function importForCity(City $city, int $ano): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [
                'success' => false,
                'message' => __('Município sem código IBGE de 7 dígitos.'),
                'source' => null,
                'attempts' => [],
            ];
        }

        return $this->importForIbge($ibge, $ano);
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     source: string|null,
     *     attempts: list<string>,
     *     imported?: int
     * }
     */
    public function importForIbge(string $ibge, int $ano): array
    {
        $attempts = [];

        $misocial = $this->misocial->importForIbge($ibge, $ano, $attempts);
        if ($misocial['success'] ?? false) {
            return $misocial;
        }

        if (! filter_var(config('ieducar.cadunico.enabled', true), FILTER_VALIDATE_BOOL)) {
            return [
                'success' => false,
                'message' => __('CadÚnico desactivado (IEDUCAR_CADUNICO_ENABLED=false).'),
                'source' => null,
                'attempts' => [],
            ];
        }

        $apiRow = $this->fetchFromApi($ibge, $ano, $attempts);
        if ($apiRow !== null) {
            $this->persistRow($ibge, $ano, $apiRow, 'api_http');
            $this->writeCache($ibge, $ano, $apiRow);

            return [
                'success' => true,
                'message' => __('Importado via API/HTTP.'),
                'source' => 'api_http',
                'attempts' => $attempts,
                'imported' => 1,
            ];
        }

        $cacheRow = $this->readCache($ibge, $ano, $attempts);
        if ($cacheRow !== null) {
            $this->persistRow($ibge, $ano, $cacheRow, 'api_cache');

            return [
                'success' => true,
                'message' => __('Importado a partir de cache local.'),
                'source' => 'api_cache',
                'attempts' => $attempts,
                'imported' => 1,
            ];
        }

        if (filter_var(config('ieducar.cadunico.auto_sync.enabled', true), FILTER_VALIDATE_BOOL)) {
            app(CadunicoRemoteCsvFetcher::class)->ensureMunicipalCsv($ibge, $ano);
        }

        $csvPath = $this->resolveStorageCsv($ibge, $ano, $attempts);
        if ($csvPath !== null) {
            $result = $this->csvImport->importFile($csvPath, $ano, $ibge);
            if ($result['imported'] > 0) {
                return [
                    'success' => true,
                    'message' => __('Importado via CSV Cecad (:file).', ['file' => basename($csvPath)]),
                    'source' => 'cecad_csv',
                    'attempts' => $attempts,
                    'imported' => $result['imported'],
                ];
            }
            foreach ($result['errors'] as $err) {
                $attempts[] = $err;
            }
            $attempts[] = __('CSV encontrado mas nenhuma linha para IBGE :ibge.', ['ibge' => $ibge]);
        }

        return [
            'success' => false,
            'message' => __('Sem dados CadÚnico para IBGE :ibge/:ano. Fonte principal: SAGI/Misocial (MDS). Complementos: API, CKAN/dados.gov.br ou CSV em :path.', [
                'ibge' => $ibge,
                'ano' => (string) $ano,
                'path' => CadunicoStoragePaths::storageRoot(),
            ]),
            'source' => null,
            'attempts' => $attempts,
        ];
    }

    /**
     * Importa todas as linhas de um CSV (upload ou storage) — útil para arquivo nacional.
     *
     * @return array{success: bool, message: string, imported: int, skipped: int, errors: list<string>}
     */
    public function importFromCsvPath(string $absolutePath, ?int $defaultYear = null, ?string $filterIbge = null): array
    {
        $result = $this->csvImport->importFile($absolutePath, $defaultYear, $filterIbge);

        return [
            'success' => $result['errors'] === [] && $result['imported'] > 0,
            'message' => $result['imported'] > 0
                ? __(':n registo(s) importado(s).', ['n' => (string) $result['imported']])
                : __('Nenhum registo importado.'),
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ];
    }

    /**
     * Varre CSV em storage para um ano (arquivo nacional ou vários municipais).
     *
     * @return array{success: bool, message: string, imported: int, files: list<string>}
     */
    public function importFromStorageForYear(int $ano, ?string $filterIbge = null): array
    {
        if ($filterIbge === null && filter_var(config('ieducar.cadunico.auto_sync.enabled', true), FILTER_VALIDATE_BOOL)) {
            app(CadunicoRemoteCsvFetcher::class)->ensureNationalCsv($ano);
        }

        $files = CadunicoStoragePaths::discoverCsvCandidates($filterIbge, $ano);
        if ($files === []) {
            return [
                'success' => false,
                'message' => __('Nenhum CSV em :path para o ano :ano.', [
                    'path' => CadunicoStoragePaths::storageRoot(),
                    'ano' => (string) $ano,
                ]),
                'imported' => 0,
                'files' => [],
            ];
        }

        $total = 0;
        $used = [];
        foreach ($files as $path) {
            $result = $this->csvImport->importFile($path, $ano, $filterIbge);
            if ($result['imported'] > 0) {
                $total += $result['imported'];
                $used[] = basename($path);
            }
            if ($filterIbge !== null && $total > 0) {
                break;
            }
        }

        return [
            'success' => $total > 0,
            'message' => $total > 0
                ? __(':n registo(s) de :files.', ['n' => (string) $total, 'files' => implode(', ', $used)])
                : __('CSV(s) encontrados mas sem linhas válidas.'),
            'imported' => $total,
            'files' => $used,
        ];
    }

    /**
     * @param  list<string>  $attempts
     * @return array<string, int|null>|null
     */
    private function fetchFromApi(string $ibge, int $ano, array &$attempts): ?array
    {
        $row = $this->fetchFromCkan($ibge, $ano, $attempts);
        if ($row !== null) {
            return $row;
        }

        $template = trim((string) config('ieducar.cadunico.open_data.api_url_template', ''));
        if ($template === '' || ! str_contains($template, '{ibge}')) {
            $attempts[] = __('API: URL modelo não configurada (IEDUCAR_CADUNICO_API_URL_TEMPLATE).');

            return null;
        }

        $url = str_replace(['{ibge}', '{ano}'], [$ibge, (string) $ano], $template);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $attempts[] = __('API: URL inválida.');

            return null;
        }

        try {
            $response = Http::timeout(max(5, (int) config('ieducar.cadunico.open_data.http_timeout', 30)))
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                $attempts[] = __('API HTTP :status em :url', ['status' => (string) $response->status(), 'url' => $url]);

                return null;
            }

            $decoded = $response->json();
            $row = $this->normalizePayload($decoded, $ibge, $ano);
            if ($row === null) {
                $attempts[] = __('API: JSON sem campos reconhecíveis para IBGE :ibge.', ['ibge' => $ibge]);

                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            $attempts[] = __('API: :msg', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  list<string>  $attempts
     * @return array<string, int|null>|null
     */
    private function fetchFromCkan(string $ibge, int $ano, array &$attempts): ?array
    {
        $discovered = $this->ckanDiscovery->discover();
        if ($discovered === null) {
            $attempts[] = __('CKAN: nenhum recurso municipal CadÚnico descoberto (dados.gov.br).');

            return null;
        }

        $base = rtrim((string) ($discovered['base_url'] ?? config('ieducar.cadunico.open_data.ckan_base_url', 'https://dados.gov.br')), '/');
        $resourceId = (string) ($discovered['resource_id'] ?? '');
        if ($resourceId === '') {
            return null;
        }

        $attempts[] = __('CKAN: :pkg — :res', [
            'pkg' => $discovered['package_title'] ?? '',
            'res' => $discovered['resource_name'] ?? $resourceId,
        ]);

        $filterVariants = [
            json_encode(['codigo_ibge' => $ibge, 'ano' => $ano], JSON_THROW_ON_ERROR),
            json_encode(['ibge' => $ibge, 'ano' => $ano], JSON_THROW_ON_ERROR),
            json_encode(['ibge_municipio' => $ibge, 'ano_referencia' => $ano], JSON_THROW_ON_ERROR),
        ];

        try {
            $response = null;
            foreach ($filterVariants as $filters) {
                $response = Http::timeout(max(5, (int) config('ieducar.cadunico.open_data.http_timeout', 30)))
                    ->get($base.'/api/3/action/datastore_search', [
                        'resource_id' => $resourceId,
                        'filters' => $filters,
                        'limit' => 5,
                    ]);
                if ($response->successful()) {
                    $records = $response->json('result.records');
                    if (is_array($records) && $records !== []) {
                        break;
                    }
                }
            }

            if ($response === null) {
                return null;
            }

            if (! $response->successful()) {
                $attempts[] = __('CKAN: HTTP :status', ['status' => (string) $response->status()]);

                return null;
            }

            $records = $response->json('result.records');
            if (! is_array($records) || $records === []) {
                $attempts[] = __('CKAN: sem registos para IBGE :ibge / :ano.', ['ibge' => $ibge, 'ano' => (string) $ano]);

                return null;
            }

            $row = $this->normalizePayload($records[0], $ibge, $ano);
            if ($row === null) {
                $attempts[] = __('CKAN: registo sem faixas etárias.');

                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            $attempts[] = __('CKAN: :msg', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  list<string>  $attempts
     */
    private function readCache(string $ibge, int $ano, array &$attempts): ?array
    {
        $path = CadunicoStoragePaths::apiCacheFile($ibge, $ano);
        if (! is_readable($path)) {
            $attempts[] = __('Cache: arquivo em falta (:path).', ['path' => $path]);

            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            $attempts[] = __('Cache: JSON vazio.');

            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $attempts[] = __('Cache: JSON inválido.');

            return null;
        }

        $row = $this->normalizePayload($decoded, $ibge, $ano);
        if ($row === null) {
            $attempts[] = __('Cache: estrutura não reconhecida.');

            return null;
        }

        return $row;
    }

    /**
     * @param  list<string>  $attempts
     */
    private function resolveStorageCsv(string $ibge, int $ano, array &$attempts): ?string
    {
        $files = CadunicoStoragePaths::discoverCsvCandidates($ibge, $ano);
        if ($files === []) {
            $attempts[] = __('CSV: nenhum arquivo em :path.', ['path' => CadunicoStoragePaths::storageRoot()]);

            return null;
        }

        $attempts[] = __('CSV: candidatos: :files', ['files' => implode(', ', array_map('basename', array_slice($files, 0, 3)))]);

        return $files[0];
    }

    /**
     * @param  mixed  $decoded
     * @return array<string, int|null>|null
     */
    private function normalizePayload(mixed $decoded, string $ibge, int $ano): ?array
    {
        if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rowIbge = FundebMunicipioReferenceRepository::normalizeIbge(
                    (string) ($item['codigo_ibge'] ?? $item['ibge'] ?? $item['ibge_municipio'] ?? '')
                );
                if ($rowIbge === $ibge || $rowIbge === null) {
                    $parsed = $this->mapRowFields($item, $ano);
                    if ($parsed !== null) {
                        return $parsed;
                    }
                }
            }

            return $this->mapRowFields($decoded[0], $ano);
        }

        if (is_array($decoded) && isset($decoded['records']) && is_array($decoded['records'])) {
            return $this->normalizePayload($decoded['records'], $ibge, $ano);
        }

        if (is_array($decoded)) {
            return $this->mapRowFields($decoded, $ano);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, int|null>|null
     */
    private function mapRowFields(array $item, int $ano): ?array
    {
        $aliases = [
            'criancas_4_5' => ['criancas_4_5', 'pop_4_5', 'faixa_4_5', 'pre_escola'],
            'criancas_6_10' => ['criancas_6_10', 'pop_6_10', 'faixa_6_10'],
            'criancas_11_14' => ['criancas_11_14', 'pop_11_14', 'faixa_11_14'],
            'criancas_15_17' => ['criancas_15_17', 'pop_15_17', 'faixa_15_17'],
            'pop_escolar' => ['populacao_escolar_estimada', 'pop_escolar', 'pop_escolar_4_17', 'criancas_4_17'],
        ];

        $out = [];
        foreach ($aliases as $key => $names) {
            $out[$key] = null;
            foreach ($names as $name) {
                if (array_key_exists($name, $item) && $item[$name] !== null && $item[$name] !== '') {
                    $out[$key] = $this->toInt($item[$name]);
                    break;
                }
                $snake = Str::snake($name);
                if (array_key_exists($snake, $item)) {
                    $out[$key] = $this->toInt($item[$snake]);
                    break;
                }
            }
        }

        $bands = ($out['criancas_4_5'] ?? 0) + ($out['criancas_6_10'] ?? 0)
            + ($out['criancas_11_14'] ?? 0) + ($out['criancas_15_17'] ?? 0);
        $pop = $out['pop_escolar'] ?? ($bands > 0 ? $bands : null);

        if ($pop === null || $pop <= 0) {
            return null;
        }

        $out['pop_escolar'] = $pop;
        $out['ano'] = $this->toInt($item['ano'] ?? $item['ano_referencia'] ?? $ano) ?? $ano;

        return $out;
    }

    /**
     * @param  array<string, int|null>  $row
     */
    private function persistRow(string $ibge, int $ano, array $row, string $fonte): void
    {
        $year = (int) ($row['ano'] ?? $ano);
        $this->repository->upsert($ibge, $year, [
            'pessoas_cadastradas' => 0,
            'familias_cadastradas' => 0,
            'criancas_0_3' => 0,
            'criancas_4_5' => (int) ($row['criancas_4_5'] ?? 0),
            'criancas_6_10' => (int) ($row['criancas_6_10'] ?? 0),
            'criancas_11_14' => (int) ($row['criancas_11_14'] ?? 0),
            'criancas_15_17' => (int) ($row['criancas_15_17'] ?? 0),
            'populacao_escolar_estimada' => (int) ($row['pop_escolar'] ?? 0),
            'fonte' => $fonte,
            'schema_version' => '1',
            'metadados' => ['imported_via' => 'CadunicoOpenDataImportService'],
        ]);
    }

    /**
     * @param  array<string, int|null>  $row
     */
    private function writeCache(string $ibge, int $ano, array $row): void
    {
        if (! filter_var(config('ieducar.cadunico.open_data.cache_enabled', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $path = CadunicoStoragePaths::apiCacheFile($ibge, $ano);
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $payload = array_merge($row, [
            'codigo_ibge' => $ibge,
            'cached_at' => now()->toIso8601String(),
        ]);

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }
        $digits = preg_replace('/\D+/', '', (string) $value);

        return ($digits !== '' && $digits !== null) ? (int) $digits : null;
    }
}
