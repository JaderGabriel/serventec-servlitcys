<?php

namespace App\Services\Fundeb;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Ieducar\FundebReferenceYearOrder;
use Illuminate\Support\Collection;
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
     * Ano sugerido para importação (FNDE costuma publicar com defasagem).
     */
    public static function suggestedImportYear(): int
    {
        return max(2000, (int) date('Y') - 1);
    }

    /**
     * Anos importados automaticamente ao cadastrar ou ao informar IBGE de um município:
     * ano vigente (referência FUNDEB = ano civil anterior, defasagem FNDE) e o ano imediatamente anterior.
     *
     * @return list<int>
     */
    public static function yearsForNewCitySync(): array
    {
        $vigente = self::suggestedImportYear();

        return self::normalizeYearList([$vigente, $vigente - 1]);
    }

    /**
     * Anos configurados (lista .env ou intervalo from/to) — usado na sincronização manual em lote.
     *
     * @return list<int>
     */
    public static function configuredSyncYears(): array
    {
        $explicit = config('ieducar.fundeb.open_data.sync_years', []);
        if (is_array($explicit) && $explicit !== []) {
            return self::normalizeYearList(array_map('intval', $explicit));
        }

        $from = (int) config('ieducar.fundeb.open_data.sync_from_year', 2020);
        $to = (int) config('ieducar.fundeb.open_data.sync_to_year', 0);
        if ($to <= 0) {
            $to = (int) date('Y') - 1;
        }

        return self::yearsInRange($from, $to);
    }

    /**
     * @deprecated Use configuredSyncYears() ou resolveSyncYears()
     *
     * @return list<int>
     */
    public static function defaultSyncYears(): array
    {
        return self::configuredSyncYears();
    }

    /**
     * Anos para importação completa (config + cache + BD + intervalo do formulário).
     *
     * @return list<int>
     */
    public function resolveSyncYears(
        ?int $anoFrom = null,
        ?int $anoTo = null,
        bool $includeCached = true,
        bool $includeDatabase = true,
    ): array {
        $explicit = config('ieducar.fundeb.open_data.sync_years', []);
        $hasExplicit = is_array($explicit) && $explicit !== [];

        $from = $anoFrom ?? (int) config('ieducar.fundeb.open_data.sync_from_year', 2020);
        $to = $anoTo ?? (int) config('ieducar.fundeb.open_data.sync_to_year', 0);
        if ($to <= 0) {
            $to = (int) date('Y') - 1;
        }

        $years = $hasExplicit
            ? self::normalizeYearList(array_map('intval', $explicit))
            : self::yearsInRange($from, $to);

        if ($anoFrom !== null || $anoTo !== null) {
            $years = array_merge($years, self::yearsInRange($from, $to));
        }

        if ($includeCached && (bool) config('ieducar.fundeb.open_data.sync_include_cached_years', true)) {
            $years = array_merge($years, $this->discoverCachedYears());
        }

        if ($includeDatabase && (bool) config('ieducar.fundeb.open_data.sync_include_database_years', true)) {
            $years = array_merge($years, $this->discoverDatabaseYears());
        }

        $years = array_merge($years, self::nationalFloorYears());

        if ($anoFrom !== null && $anoFrom > 0) {
            $years = array_values(array_filter($years, static fn (int $y): bool => $y >= $anoFrom));
        }
        if ($anoTo !== null && $anoTo > 0) {
            $years = array_values(array_filter($years, static fn (int $y): bool => $y <= $anoTo));
        }

        $years = self::normalizeYearList($years);
        $max = max(1, (int) config('ieducar.fundeb.open_data.sync_max_years', 30));
        if (count($years) > $max) {
            $years = array_slice($years, 0, $max);
        }

        return $years;
    }

    /**
     * @return list<int>
     */
    public static function yearsInRange(int $from, int $to): array
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $years = [];
        for ($y = $to; $y >= $from; $y--) {
            $years[] = $y;
        }

        return self::normalizeYearList($years);
    }

    /**
     * @param  list<int>  $years
     * @return list<int>
     */
    public static function normalizeYearList(array $years): array
    {
        $maxAllowed = (int) date('Y') + 1;
        $years = array_values(array_unique(array_map('intval', $years)));
        $years = array_values(array_filter(
            $years,
            static fn (int $y): bool => $y >= 2000 && $y <= $maxAllowed,
        ));
        rsort($years);

        return $years;
    }

    /**
     * @return list<int>
     */
    private function discoverCachedYears(): array
    {
        $template = $this->cachePathTemplate();
        if ($template === '') {
            return [];
        }

        $root = storage_path('app/fundeb/api');
        if (! is_dir($root)) {
            return [];
        }

        $years = [];
        foreach (glob($root.'/*/*.json') ?: [] as $file) {
            if (preg_match('/(\d{4})\.json$/', $file, $m)) {
                $years[] = (int) $m[1];
            }
        }

        return self::normalizeYearList($years);
    }

    /**
     * @return list<int>
     */
    private function discoverDatabaseYears(): array
    {
        try {
            return self::normalizeYearList(
                FundebMunicipioReference::query()->distinct()->orderByDesc('ano')->pluck('ano')->all(),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private static function nationalFloorYears(): array
    {
        if (! (bool) config('ieducar.fundeb.open_data.national_floor.enabled', false)) {
            return [];
        }

        $byYear = config('ieducar.fundeb.open_data.national_floor.vaaf_by_year', []);
        if (! is_array($byYear)) {
            return [];
        }

        $years = [];
        foreach ($byYear as $year => $vaaf) {
            if ($vaaf !== null && (float) $vaaf > 0) {
                $years[] = (int) $year;
            }
        }

        return self::normalizeYearList($years);
    }

    /**
     * Estado da configuração e ligação CKAN (para a UI admin).
     *
     * @return array{
     *     resource_id_configured: bool,
     *     json_url_configured: bool,
     *     resource_id: string,
     *     discovered_resource_id: string,
     *     ckan_reachable: bool,
     *     ckan_base_url: string,
     *     hint: string
     * }
     */
    public function apiDiagnostics(): array
    {
        $configuredId = trim((string) config('ieducar.fundeb.open_data.resource_id', ''));
        $jsonUrl = trim((string) config('ieducar.fundeb.open_data.json_url', ''));
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));

        $discovered = '';
        $reachable = false;
        $response = Http::timeout(min($timeout, 10))
            ->acceptJson()
            ->withOptions(['allow_redirects' => true])
            ->get($base.'/api/3/action/package_search', ['q' => 'fundeb', 'rows' => 1]);
        $reachable = $response->successful() && ($response->json('success') === true);
        if ($configuredId === '' && $reachable) {
            $discovered = $this->discoverResourceId($base, $timeout);
        }
        if ($jsonUrl !== '' && ! $reachable) {
            $reachable = true;
        }

        $effectiveId = $configuredId !== '' ? $configuredId : $discovered;
        $cacheTpl = $this->cachePathTemplate();
        $hint = match (true) {
            $effectiveId !== '' && $cacheTpl !== '' => __('CKAN FNDE (:id) + cache em disco (:cache). Importação grava JSON e usa na leitura seguinte.', [
                'id' => Str::limit($effectiveId, 12),
                'cache' => Str::limit($cacheTpl, 40),
            ]),
            $cacheTpl !== '' && $effectiveId === '' => __('Cache em disco (:cache). Defina IEDUCAR_FUNDEB_CKAN_RESOURCE_ID para preencher automaticamente via API.', [
                'cache' => Str::limit($cacheTpl, 40),
            ]),
            $jsonUrl !== '' && $this->isRemoteJsonUrl($jsonUrl) => __('URL JSON remota (IEDUCAR_FUNDEB_JSON_URL) + cache opcional.'),
            $effectiveId !== '' => __('Fonte: CKAN FNDE (recurso :id).', ['id' => Str::limit($effectiveId, 12)]),
            ! $reachable => __('CKAN FNDE inacessível. Defina IEDUCAR_FUNDEB_CKAN_RESOURCE_ID ou URL JSON HTTP no .env.'),
            default => __('Nenhum recurso CKAN encontrado. Defina IEDUCAR_FUNDEB_CKAN_RESOURCE_ID no .env.'),
        };

        return [
            'resource_id_configured' => $configuredId !== '',
            'json_url_configured' => $jsonUrl !== '',
            'resource_id' => $configuredId,
            'discovered_resource_id' => $discovered,
            'ckan_reachable' => $reachable || $jsonUrl !== '',
            'ckan_base_url' => $base,
            'effective_resource_id' => $effectiveId,
            'hint' => $hint,
        ];
    }

    /**
     * Cobertura local por município para um ano.
     *
     * @return list<array{city_id: int, name: string, uf: ?string, ibge: ?string, has_ibge: bool, has_reference: bool, vaaf: ?float}>
     */
    public function localCoverageForYear(int $ano): array
    {
        $cities = City::query()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio']);
        $refsByIbge = [];
        $refsByCity = [];

        foreach (FundebMunicipioReference::query()->where('ano', $ano)->get(['city_id', 'ibge_municipio', 'vaaf']) as $ref) {
            if ($ref->city_id) {
                $refsByCity[(int) $ref->city_id] = (float) $ref->vaaf;
            }
            if ($ref->ibge_municipio) {
                $refsByIbge[(string) $ref->ibge_municipio] = (float) $ref->vaaf;
            }
        }

        $rows = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            $vaaf = $refsByCity[(int) $city->id] ?? ($ibge !== null ? ($refsByIbge[$ibge] ?? null) : null);
            $rows[] = [
                'city_id' => (int) $city->id,
                'name' => $city->name,
                'uf' => $city->uf,
                'ibge' => $ibge,
                'has_ibge' => $ibge !== null,
                'has_reference' => $vaaf !== null,
                'vaaf' => $vaaf,
            ];
        }

        return $rows;
    }

    /**
     * Cobertura por município para vários anos (painel admin).
     *
     * @param  list<int>  $anos
     * @return list<array{
     *     city_id: int,
     *     name: string,
     *     uf: ?string,
     *     ibge: ?string,
     *     has_ibge: bool,
     *     years: array<int, array{has_reference: bool, vaaf: ?float}>
     * }>
     */
    public function localCoverageForYears(array $anos): array
    {
        $anos = array_values(array_unique(array_map('intval', $anos)));
        if ($anos === []) {
            $anos = $this->resolveSyncYears();
        }

        $cities = City::query()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio']);
        $refsByCity = [];
        $refsByIbge = [];

        foreach (FundebMunicipioReference::query()->whereIn('ano', $anos)->get(['city_id', 'ibge_municipio', 'ano', 'vaaf']) as $ref) {
            $ano = (int) $ref->ano;
            if ($ref->city_id) {
                $refsByCity[(int) $ref->city_id][$ano] = (float) $ref->vaaf;
            }
            if ($ref->ibge_municipio) {
                $refsByIbge[(string) $ref->ibge_municipio][$ano] = (float) $ref->vaaf;
            }
        }

        $rows = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            $years = [];
            foreach ($anos as $ano) {
                $vaaf = $refsByCity[(int) $city->id][$ano] ?? ($ibge !== null ? ($refsByIbge[$ibge][$ano] ?? null) : null);
                $years[$ano] = [
                    'has_reference' => $vaaf !== null,
                    'vaaf' => $vaaf,
                ];
            }
            $withRef = count(array_filter($years, static fn (array $y): bool => $y['has_reference']));
            $rows[] = [
                'city_id' => (int) $city->id,
                'name' => $city->name,
                'uf' => $city->uf,
                'ibge' => $ibge,
                'has_ibge' => $ibge !== null,
                'years' => $years,
                'years_with_reference' => $withRef,
                'years_total' => count($anos),
            ];
        }

        return $rows;
    }

    /**
     * @return array{success: bool, message: string, reference?: array<string, mixed>, imported_ano?: int}
     */
    public function importForCityYear(
        City $city,
        int $ano,
        bool $useNearestYear = false,
        ?FundebImportProgress $progress = null,
    ): array {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            $msg = __('Cadastre o código IBGE do município (7 dígitos) na ficha da cidade «:name».', ['name' => $city->name]);
            $progress?->warn($msg);

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        if ($ano < 2000 || $ano > (int) date('Y') + 1) {
            $msg = __('Ano inválido.');
            $progress?->error($msg);

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $progress?->info(__('→ :city (IBGE :ibge), ano :ano…', [
            'city' => $city->name,
            'ibge' => $ibge,
            'ano' => (string) $ano,
        ]));

        $match = null;
        $importAno = $ano;

        foreach (FundebReferenceYearOrder::candidateYears($ano) as $tryAno) {
            $row = $this->fetchRow($ibge, $tryAno);
            if ($row !== null) {
                $match = $row;
                $importAno = $tryAno;
                break;
            }
        }

        if ($match === null && $useNearestYear) {
            $nearest = $this->findNearestRow($ibge, $ano);
            if ($nearest !== null) {
                $match = $nearest['row'];
                $importAno = $nearest['ano'];
            }
        }

        if ($match === null) {
            $floor = $this->nationalFloorRow($ibge, $ano);
            if ($floor !== null) {
                $match = $floor;
                $importAno = $ano;
                $this->writeCacheJson($ibge, $ano, $floor);
            }
        }

        if ($match === null) {
            $available = $this->findAvailableYears($ibge, 8);
            $diag = $this->apiDiagnostics();
            $cachePath = $this->cachePathTemplate();
            $parts = [
                __('Nenhum VAAF/VAAT para IBGE :ibge e ano :ano (após tentar cache local e fonte remota).', [
                    'ibge' => $ibge,
                    'ano' => (string) $ano,
                ]),
            ];
            if ($cachePath !== '') {
                $parts[] = __('Cache: :path — :status.', [
                    'path' => $this->resolvePathFromTemplate($cachePath, $ibge, $ano),
                    'status' => is_readable($this->resolvePathFromTemplate($cachePath, $ibge, $ano))
                        ? __('ficheiro existe mas sem VAAF válido')
                        : __('ficheiro inexistente; execute importação com CKAN configurado'),
                ]);
            }
            if ($available !== []) {
                $parts[] = __('Anos disponíveis (cache ou API): :anos. Já tentou :ano e anos anteriores; use «ano mais recente» na importação em lote.', [
                    'anos' => implode(', ', $available),
                    'ano' => (string) $ano,
                ]);
            } else {
                $parts[] = $diag['hint'];
            }
            if ($ano >= (int) date('Y')) {
                $parts[] = __('O FNDE pode ainda não ter publicado dados para :ano; use o ano anterior (:sugestao).', [
                    'ano' => (string) $ano,
                    'sugestao' => (string) self::suggestedImportYear(),
                ]);
            }

            $msg = implode(' ', $parts);
            $progress?->error(__('✗ :city / :ano: :msg', [
                'city' => $city->name,
                'ano' => (string) $ano,
                'msg' => Str::limit($msg, 120),
            ]));

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $vaaf = (float) ($match['vaaf'] ?? 0);
        if ($vaaf <= 0) {
            $msg = __('Registo encontrado, mas VAAF inválido ou ausente.');
            $progress?->error(__('✗ :city / :ano: :msg', ['city' => $city->name, 'ano' => (string) $ano, 'msg' => $msg]));

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $notas = (string) ($match['notas'] ?? '');
        if ($importAno !== $ano) {
            $notas = trim($notas.' '.__('Solicitado :pedido; gravado ano :gravado (mais recente ≤ pedido na API).', [
                'pedido' => (string) $ano,
                'gravado' => (string) $importAno,
            ]));
        }

        $model = $this->references->upsert($city, $importAno, [
            'vaaf' => $vaaf,
            'vaat' => isset($match['vaat']) ? (float) $match['vaat'] : null,
            'complementacao_vaar' => isset($match['complementacao_vaar']) ? (float) $match['complementacao_vaar'] : null,
            'fonte' => (string) ($match['fonte'] ?? 'api_ckan_fnde'),
            'notas' => $notas !== '' ? $notas : null,
        ]);

        $msg = $importAno === $ano
            ? __('VAAF :vaaf gravado para :ano (fonte: :fonte).', [
                'vaaf' => number_format($vaaf, 2, ',', '.'),
                'ano' => (string) $importAno,
                'fonte' => $model->fonte,
            ])
            : __('VAAF :vaaf gravado para :ano (pedido :pedido; fonte: :fonte).', [
                'vaaf' => number_format($vaaf, 2, ',', '.'),
                'ano' => (string) $importAno,
                'pedido' => (string) $ano,
                'fonte' => $model->fonte,
            ]);

        $progress?->success(__('✓ :city / :gravado: VAAF :vaaf (:fonte)', [
            'city' => $city->name,
            'gravado' => (string) $importAno,
            'vaaf' => number_format($vaaf, 2, ',', '.'),
            'fonte' => $model->fonte,
        ]));

        return [
            'success' => true,
            'message' => $msg,
            'imported_ano' => $importAno,
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
     * Importa todos os municípios com IBGE (ou só um city_id).
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     ok: list<array{city: string, ibge: string, ano: int, vaaf: float}>,
     *     failed: list<array{city: string, ibge: ?string, message: string}>,
     *     skipped: list<array{city: string, message: string}>
     * }
     */
    public function importBulk(
        int $ano,
        bool $useNearestYear = false,
        ?int $onlyCityId = null,
        ?FundebImportProgress $progress = null,
    ): array {
        $cityIds = $onlyCityId !== null ? [$onlyCityId] : null;

        return $this->importBulkForYears([$ano], $useNearestYear, $cityIds, $progress);
    }

    /**
     * Sincroniza municípios com IBGE para cada ano pedido (cache + API + piso nacional).
     *
     * @param  list<int>  $anos
     * @param  list<int>|null  $cityIds  null = todas as cidades cadastradas
     * @return array{
     *     success: bool,
     *     message: string,
     *     anos: list<int>,
     *     ok: list<array<string, mixed>>,
     *     failed: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function importBulkForYears(
        array $anos,
        bool $useNearestYear = false,
        ?array $cityIds = null,
        ?FundebImportProgress $progress = null,
    ): array {
        $anos = array_values(array_unique(array_map('intval', $anos)));
        if ($anos === []) {
            $anos = $this->resolveSyncYears();
        }

        $cityIds = $cityIds !== null
            ? array_values(array_unique(array_map('intval', $cityIds)))
            : null;

        $progress?->info(__('Início FUNDEB — anos: :anos', [
            'anos' => implode(', ', array_map('strval', $anos)),
        ]));
        $progress?->info($this->describeBulkScope($cityIds, $anos));

        $ok = [];
        $failed = [];
        $skipped = [];

        foreach ($anos as $index => $ano) {
            $progress?->info(__('—— Ano :ano (:i/:total) ——', [
                'ano' => (string) $ano,
                'i' => (string) ($index + 1),
                'total' => (string) count($anos),
            ]));
            $batch = $this->importBulkSingleYear($ano, $useNearestYear, $cityIds, $progress);
            foreach ($batch['ok'] as $row) {
                $ok[] = $row;
            }
            foreach ($batch['failed'] as $row) {
                $failed[] = $row;
            }
            foreach ($batch['skipped'] as $row) {
                $skipped[] = $row;
            }
        }

        $summary = $this->summarizeBulkResult($anos, $cityIds, $ok, $failed, $skipped);

        $anosLabel = count($anos) <= 6
            ? implode(', ', array_map('strval', $anos))
            : __(':n anos (:min–:max)', [
                'n' => (string) count($anos),
                'min' => (string) min($anos),
                'max' => (string) max($anos),
            ]);

        $message = __('Sincronização FUNDEB — :cities cidade(s), :anosLabel: :ok gravado(s), :fail falha(s), :skip sem IBGE.', [
            'cities' => (string) ($summary['cities_selected'] ?? 0),
            'anosLabel' => $anosLabel,
            'ok' => (string) count($ok),
            'fail' => (string) count($failed),
            'skip' => (string) count($skipped),
        ]);

        $progress?->info(__('Concluído: :msg', ['msg' => $message]));

        return [
            'success' => count($failed) === 0 && count($ok) > 0,
            'message' => $message,
            'anos' => $anos,
            'ok' => $ok,
            'failed' => $failed,
            'skipped' => $skipped,
            'summary' => $summary,
            'logs' => $progress?->entries() ?? [],
        ];
    }

    /**
     * @param  list<int>|null  $cityIds
     * @param  list<int>  $anos
     */
    private function describeBulkScope(?array $cityIds, array $anos): string
    {
        $citiesFilter = static function ($query) use ($cityIds): void {
            if ($cityIds !== null && $cityIds !== []) {
                $query->whereIn('id', $cityIds);
            }
        };
        $withIbge = (int) City::query()
            ->tap($citiesFilter)
            ->whereNotNull('ibge_municipio')
            ->where('ibge_municipio', '!=', '')
            ->count();
        $ops = $withIbge * count($anos);

        if ($cityIds === null) {
            return __('Municípios: todos com IBGE (:n) × :y ano(s) ≈ :ops operações.', [
                'n' => (string) $withIbge,
                'y' => (string) count($anos),
                'ops' => (string) $ops,
            ]);
        }

        return __('Municípios: :sel selecionado(s), :ibge com IBGE × :y ano(s) ≈ :ops operações.', [
            'sel' => (string) count($cityIds),
            'ibge' => (string) $withIbge,
            'y' => (string) count($anos),
            'ops' => (string) $ops,
        ]);
    }

    /**
     * @param  list<int>  $anos
     * @param  list<int>|null  $cityIds
     * @param  list<array<string, mixed>>  $ok
     * @param  list<array<string, mixed>>  $failed
     * @param  list<array<string, mixed>>  $skipped
     * @return array<string, mixed>
     */
    private function summarizeBulkResult(array $anos, ?array $cityIds, array $ok, array $failed, array $skipped): array
    {
        $byFonte = [];
        foreach ($ok as $row) {
            $fonte = (string) ($row['fonte'] ?? 'desconhecida');
            $byFonte[$fonte] = ($byFonte[$fonte] ?? 0) + 1;
        }
        arsort($byFonte);

        $byCity = [];
        foreach ($ok as $row) {
            $key = (string) ($row['city'] ?? '');
            if ($key === '') {
                continue;
            }
            if (! isset($byCity[$key])) {
                $byCity[$key] = ['city' => $key, 'city_id' => $row['city_id'] ?? null, 'ibge' => $row['ibge'] ?? null, 'ok' => 0, 'failed' => 0];
            }
            $byCity[$key]['ok']++;
        }
        foreach ($failed as $row) {
            $key = (string) ($row['city'] ?? '');
            if ($key === '') {
                continue;
            }
            if (! isset($byCity[$key])) {
                $byCity[$key] = ['city' => $key, 'city_id' => $row['city_id'] ?? null, 'ibge' => $row['ibge'] ?? null, 'ok' => 0, 'failed' => 0];
            }
            $byCity[$key]['failed']++;
        }

        $skippedUnique = [];
        foreach ($skipped as $row) {
            $key = (string) ($row['city'] ?? '');
            $skippedUnique[$key] = $row;
        }

        $citiesFilter = static function ($query) use ($cityIds): void {
            if ($cityIds !== null && $cityIds !== []) {
                $query->whereIn('id', $cityIds);
            }
        };
        $citiesSelected = (int) City::query()->tap($citiesFilter)->count();
        $citiesWithIbge = (int) City::query()
            ->tap($citiesFilter)
            ->whereNotNull('ibge_municipio')
            ->where('ibge_municipio', '!=', '')
            ->count();

        return [
            'ran_at' => now()->format('d/m/Y H:i:s'),
            'anos' => $anos,
            'ano_from' => $anos !== [] ? min($anos) : null,
            'ano_to' => $anos !== [] ? max($anos) : null,
            'ano_count' => count($anos),
            'city_ids' => $cityIds,
            'cities_selected' => $citiesSelected,
            'cities_with_ibge' => $citiesWithIbge,
            'operations_planned' => $citiesWithIbge * max(1, count($anos)),
            'ok_count' => count($ok),
            'failed_count' => count($failed),
            'skipped_count' => count($skippedUnique),
            'unique_cities_ok' => count(array_filter($byCity, static fn (array $r): bool => $r['ok'] > 0)),
            'by_fonte' => $byFonte,
            'by_city' => array_values($byCity),
            'skipped' => array_values($skippedUnique),
        ];
    }

    /**
     * @param  list<int>|null  $cityIds
     * @return array{
     *     ok: list<array<string, mixed>>,
     *     failed: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>
     * }
     */
    private function importBulkSingleYear(
        int $ano,
        bool $useNearestYear,
        ?array $cityIds,
        ?FundebImportProgress $progress = null,
    ): array {
        $query = City::query()->orderBy('name');
        if ($cityIds !== null && $cityIds !== []) {
            $query->whereIn('id', $cityIds);
        }

        /** @var Collection<int, City> $cities */
        $cities = $query->get();
        $ok = [];
        $failed = [];
        $skipped = [];
        $skippedCityIds = [];
        $total = $cities->count();
        $current = 0;

        foreach ($cities as $city) {
            $current++;
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                if (! isset($skippedCityIds[(int) $city->id])) {
                    $skippedCityIds[(int) $city->id] = true;
                    $skipped[] = [
                        'city_id' => (int) $city->id,
                        'city' => $city->name,
                        'message' => __('Sem IBGE cadastrado'),
                    ];
                    $progress?->warn(__('⊘ :city (:i/:total): sem IBGE', [
                        'city' => $city->name,
                        'i' => (string) $current,
                        'total' => (string) $total,
                    ]));
                }

                continue;
            }

            $result = $this->importForCityYear($city, $ano, $useNearestYear, $progress);
            if ($result['success']) {
                $ok[] = [
                    'city_id' => (int) $city->id,
                    'city' => $city->name,
                    'ibge' => $ibge,
                    'requested_ano' => $ano,
                    'ano' => (int) ($result['imported_ano'] ?? $ano),
                    'vaaf' => (float) ($result['reference']['vaaf'] ?? 0),
                    'fonte' => (string) ($result['reference']['fonte'] ?? ''),
                ];
            } else {
                $failed[] = [
                    'city_id' => (int) $city->id,
                    'city' => $city->name,
                    'ibge' => $ibge,
                    'requested_ano' => $ano,
                    'ano' => $ano,
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'ok' => $ok,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<int> anos disponíveis na API para o IBGE (desc)
     */
    public function findAvailableYears(string $ibge, int $max = 10): array
    {
        $years = $this->findCacheYears($ibge);

        foreach ($this->scanRecordsForIbge($ibge, 3000) as $record) {
            $parsed = $this->mapRecordFlexible($record, $ibge);
            if ($parsed !== null && ! in_array($parsed['ano'], $years, true)) {
                $years[] = $parsed['ano'];
            }
        }
        rsort($years);

        return array_slice(array_values(array_unique($years)), 0, $max);
    }

    /**
     * @return array{row: array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}, ano: int}|null
     */
    private function findNearestRow(string $ibge, int $preferredAno): ?array
    {
        $best = null;
        $bestAno = 0;

        foreach ($this->scanRecordsForIbge($ibge, 5000) as $record) {
            $parsed = $this->mapRecordFlexible($record, $ibge);
            if ($parsed === null || $parsed['ano'] > $preferredAno) {
                continue;
            }
            if ($parsed['ano'] > $bestAno) {
                $bestAno = $parsed['ano'];
                $best = $parsed;
            }
        }

        if ($best === null) {
            return null;
        }

        return ['row' => $best['row'], 'ano' => $bestAno];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanRecordsForIbge(string $ibge, int $limit): array
    {
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $resourceId = $this->resolveResourceId($base);
        if ($resourceId === '') {
            return [];
        }

        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));

        return $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, $limit, $ibge);
    }

    private function resolveResourceId(string $base): string
    {
        $resourceId = trim((string) config('ieducar.fundeb.open_data.resource_id', ''));
        if ($resourceId !== '') {
            return $resourceId;
        }

        return $this->discoverResourceId($base, max(5, (int) config('ieducar.fundeb.open_data.timeout', 30)));
    }

    /**
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchRow(string $ibge, int $ano): ?array
    {
        $cacheTemplate = $this->cachePathTemplate();

        if ($cacheTemplate !== '') {
            $cached = $this->fetchFromJsonUrl($cacheTemplate, $ibge, $ano);
            if ($cached !== null) {
                return $cached;
            }
        }

        $jsonUrl = trim((string) config('ieducar.fundeb.open_data.json_url', ''));
        if ($jsonUrl !== '' && $this->isRemoteJsonUrl($jsonUrl)) {
            $row = $this->fetchFromJsonUrl($jsonUrl, $ibge, $ano);
            if ($row !== null) {
                $this->writeCacheJson($ibge, $ano, $row);

                return $row;
            }
        }

        $row = $this->fetchFromCkan($ibge, $ano);
        if ($row !== null) {
            $this->writeCacheJson($ibge, $ano, $row);
        }

        return $row;
    }

    /**
     * Piso VAAF nacional quando não há dado municipal (configurável; ver IEDUCAR_FUNDEB_NATIONAL_FLOOR).
     *
     * @return array{vaaf: float, fonte?: string, notas?: string}|null
     */
    private function nationalFloorRow(string $ibge, int $ano): ?array
    {
        $enabled = (bool) config('ieducar.fundeb.open_data.national_floor.enabled', false);
        if (! $enabled) {
            return null;
        }

        $byYear = config('ieducar.fundeb.open_data.national_floor.vaaf_by_year', []);
        $vaaf = null;
        if (is_array($byYear) && isset($byYear[$ano]) && $byYear[$ano] !== null && (float) $byYear[$ano] > 0) {
            $vaaf = (float) $byYear[$ano];
        }

        if ($vaaf === null || $vaaf <= 0) {
            $vaaf = (float) config('ieducar.discrepancies.vaa_referencia_anual', 0);
        }

        if ($vaaf <= 0) {
            return null;
        }

        return [
            'vaaf' => $vaaf,
            'fonte' => 'referencia_nacional_config',
            'notas' => __('VAAF nacional de referência (:valor) — sem dado municipal para IBGE :ibge/ano :ano. Atualize quando importar dados oficiais FNDE.', [
                'valor' => number_format($vaaf, 2, ',', '.'),
                'ibge' => $ibge,
                'ano' => (string) $ano,
            ]),
        ];
    }

    private function cachePathTemplate(): string
    {
        $explicit = trim((string) config('ieducar.fundeb.open_data.cache_path', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $jsonUrl = trim((string) config('ieducar.fundeb.open_data.json_url', ''));
        if ($jsonUrl !== '' && $this->isCachePathTemplate($jsonUrl)) {
            return $jsonUrl;
        }

        if (trim((string) config('ieducar.fundeb.open_data.resource_id', '')) !== '') {
            return 'storage://app/fundeb/api/{ibge}/{ano}.json';
        }

        return '';
    }

    private function isCachePathTemplate(string $url): bool
    {
        return str_starts_with($url, 'storage://') || str_starts_with($url, 'file://');
    }

    private function isRemoteJsonUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function resolvePathFromTemplate(string $template, string $ibge, int $ano): string
    {
        $resolved = str_replace(['{ibge}', '{ano}'], [$ibge, (string) $ano], $template);
        if (str_starts_with($resolved, 'storage://')) {
            return storage_path(substr($resolved, strlen('storage://')));
        }
        if (str_starts_with($resolved, 'file://')) {
            return substr($resolved, 7);
        }

        return $resolved;
    }

    /**
     * @param  array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}  $row
     */
    private function writeCacheJson(string $ibge, int $ano, array $row): void
    {
        $template = $this->cachePathTemplate();
        if ($template === '') {
            return;
        }

        $path = $this->resolvePathFromTemplate($template, $ibge, $ano);
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $payload = [[
            'codigo_ibge' => $ibge,
            'ano' => $ano,
            'vaaf' => $row['vaaf'],
            'vaat' => $row['vaat'] ?? null,
            'complementacao_vaar' => $row['complementacao_vaar'] ?? null,
            'fonte' => $row['fonte'] ?? 'api_ckan_fnde',
            'notas' => $row['notas'] ?? __('Gravado em cache local em :date', ['date' => now()->format('Y-m-d H:i')]),
            'cached_at' => now()->toIso8601String(),
        ]];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<int>
     */
    private function findCacheYears(string $ibge): array
    {
        $template = $this->cachePathTemplate();
        if ($template === '') {
            return [];
        }

        $dir = dirname($this->resolvePathFromTemplate($template, $ibge, 0));
        if (! is_dir($dir)) {
            return [];
        }

        $years = [];
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            if (preg_match('/(\d{4})\.json$/', $file, $m)) {
                $years[] = (int) $m[1];
            }
        }

        rsort($years);

        return array_values(array_unique($years));
    }

    /**
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchFromCkan(string $ibge, int $ano): ?array
    {
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $resourceId = $this->resolveResourceId($base);
        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));

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
            ->withOptions(['allow_redirects' => true])
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
        $queries = [
            (string) config('ieducar.fundeb.open_data.search_query', 'fundeb vaaf municipio'),
            'vaaf municipio',
            'fundeb',
        ];

        foreach (array_unique($queries) as $q) {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withOptions(['allow_redirects' => true])
                ->get($base.'/api/3/action/package_search', [
                    'q' => $q,
                    'rows' => 8,
                ]);

            if (! $response->successful()) {
                continue;
            }

            $results = $response->json('result.results') ?? [];
            if (! is_array($results)) {
                continue;
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
                    $name = strtolower((string) ($res['name'] ?? '').' '.($pkg['title'] ?? ''));
                    if ($id === '') {
                        continue;
                    }
                    if (in_array($format, ['csv', 'xlsx', 'json', ''], true)
                        && (str_contains($name, 'vaaf') || str_contains($name, 'fundeb') || str_contains($name, 'aluno'))) {
                        return $id;
                    }
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
     * @return array{ano: int, row: array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}}|null
     */
    private function mapRecordFlexible(array $record, string $ibge): ?array
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
        if ($rowAno < 2000 || $rowAno > (int) date('Y') + 1) {
            return null;
        }

        $vaaf = $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.vaaf', [])));
        if ($vaaf === null || $vaaf <= 0) {
            return null;
        }

        $fonte = $this->firstValue($normalized, ['fonte', 'source']);
        $notas = $this->firstValue($normalized, ['notas', 'notes', 'observacao']);

        return [
            'ano' => $rowAno,
            'row' => array_filter([
                'vaaf' => $vaaf,
                'vaat' => $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.vaat', []))),
                'complementacao_vaar' => $this->parseMoney($this->firstValue($normalized, config('ieducar.fundeb.open_data.fields.complementacao_vaar', []))),
                'fonte' => is_string($fonte) && $fonte !== '' ? $fonte : 'api_ckan_fnde',
                'notas' => is_string($notas) && $notas !== ''
                    ? $notas
                    : __('Importado via CKAN em :date', ['date' => now()->format('Y-m-d H:i')]),
            ], static fn ($v) => $v !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function mapRecord(array $record, string $ibge, int $ano): ?array
    {
        $flex = $this->mapRecordFlexible($record, $ibge);
        if ($flex === null || $flex['ano'] !== $ano) {
            return null;
        }

        return $flex['row'];
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
     * @return array{vaaf: float, vaat?: float, complementacao_vaar?: float, fonte?: string, notas?: string}|null
     */
    private function fetchFromJsonUrl(string $urlTemplate, string $ibge, int $ano): ?array
    {
        $resolved = str_replace(['{ibge}', '{ano}'], [$ibge, (string) $ano], $urlTemplate);

        $data = $this->readJsonSource($resolved);
        if ($data === null) {
            return null;
        }

        $records = is_array($data['records'] ?? null) ? $data['records'] : (is_array($data) && array_is_list($data) ? $data : []);

        return $this->parseFirstRecord($records, $ibge, $ano);
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function readJsonSource(string $source): ?array
    {
        if (str_starts_with($source, 'storage://')) {
            $path = storage_path(substr($source, strlen('storage://')));
            if (! is_readable($path)) {
                return null;
            }
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : null;
        }

        if (str_starts_with($source, 'file://')) {
            $path = substr($source, 7);
            if (! is_readable($path)) {
                return null;
            }
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : null;
        }

        $timeout = max(5, (int) config('ieducar.fundeb.open_data.timeout', 30));
        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withOptions(['allow_redirects' => true])
            ->get($source);
        if (! $response->successful()) {
            return null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
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
