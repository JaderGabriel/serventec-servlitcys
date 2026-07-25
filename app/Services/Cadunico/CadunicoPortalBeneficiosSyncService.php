<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Models\MunicipalBenefitSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalBenefitSnapshotRepository;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * CUN-04 — importa agregados PBF/NBF/BPC (Portal) por IBGE, sem NIS/CPF.
 */
final class CadunicoPortalBeneficiosSyncService
{
    public function __construct(
        private PortalTransparenciaApiClient $portal,
        private MunicipalBenefitSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  list<int>|null  $cityIds
     * @return Collection<int, City>
     */
    public function consultoriaCities(?array $cityIds = null): Collection
    {
        $query = City::query()->active()->orderBy('name');
        if ($cityIds !== null) {
            $query->whereIn('id', $cityIds);
        }

        return $query->get()->filter(static function (City $city): bool {
            if (! $city->hasDataSetup()) {
                return false;
            }

            return FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) !== null;
        })->values();
    }

    /**
     * @param  list<int>|null  $cityIds
     * @param  list<string>|null  $programas
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   message: string,
     *   cities: int,
     *   upserted: int,
     *   fetched: int,
     *   dry_run: bool,
     *   by_city: list<array<string, mixed>>
     * }
     */
    public function sync(?array $cityIds = null, ?array $programas = null, ?int $months = null, bool $dryRun = false): array
    {
        $empty = [
            'success' => false,
            'message' => '',
            'cities' => 0,
            'upserted' => 0,
            'fetched' => 0,
            'dry_run' => $dryRun,
            'by_city' => [],
        ];

        $portal = config('ieducar.other_funding.public_queries.portal_transparencia', []);
        if (! (bool) ($portal['enabled'] ?? true)) {
            return array_merge($empty, [
                'success' => true,
                'skipped' => true,
                'message' => __('Portal da Transparência desactivado.'),
            ]);
        }

        $apiKey = trim((string) ($portal['api_key'] ?? ''));
        if ($apiKey === '') {
            return array_merge($empty, [
                'success' => true,
                'skipped' => true,
                'message' => __('PORTAL_TRANSPARENCIA_API_KEY não configurada.'),
            ]);
        }

        $cfg = config('ieducar.cadunico.beneficios_portal', []);
        if (! filter_var($cfg['enabled'] ?? true, FILTER_VALIDATE_BOOL)) {
            return array_merge($empty, [
                'success' => true,
                'skipped' => true,
                'message' => __('Sync de benefícios Portal desactivado (IEDUCAR_CADUNICO_BENEFICIOS_PORTAL_ENABLED).'),
            ]);
        }

        $cities = $this->consultoriaCities($cityIds);
        if ($cities->isEmpty()) {
            return array_merge($empty, [
                'success' => true,
                'message' => __('Nenhum município de consultoria com IBGE encontrado.'),
            ]);
        }

        $programas = $this->normalizeProgramas($programas ?? ($cfg['programas'] ?? null));
        $months = max(1, min(24, $months ?? (int) ($cfg['months'] ?? 6)));
        $mesAnos = PortalTransparenciaApiClient::mesAnoWindow($months);
        $timeout = max(10, (int) ($cfg['timeout'] ?? $portal['emendas_timeout'] ?? 25));
        $maxPages = max(1, min(5, (int) ($cfg['max_pages'] ?? 2)));
        $sleepUs = max(0, (int) ($cfg['sleep_us'] ?? 80_000));

        $upserted = 0;
        $fetched = 0;
        $byCity = [];
        $batch = [];

        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }

            $cityFetched = 0;
            $cityRows = 0;

            foreach ($mesAnos as $mesAno) {
                foreach ($programas as $programa) {
                    $items = $this->fetchPrograma($programa, $ibge, $mesAno, $apiKey, $timeout, $maxPages);
                    $fetched += count($items);
                    $cityFetched += count($items);
                    $agg = PortalTransparenciaApiClient::aggregateBeneficioPorMunicipio($items);

                    if ($agg['quantidade'] <= 0 && $agg['valor'] === null && $items === []) {
                        if ($sleepUs > 0) {
                            usleep($sleepUs);
                        }

                        continue;
                    }

                    $row = [
                        'city_id' => $city->id,
                        'ibge_municipio' => $ibge,
                        'programa' => $programa,
                        'mes_ano' => $mesAno,
                        'quantidade_beneficiados' => (int) $agg['quantidade'],
                        'valor' => $agg['valor'],
                        'data_referencia' => $agg['data_referencia'],
                        'tipo_descricao' => $agg['tipo_descricao'],
                        'payload' => [
                            'rows' => count($items),
                            'sample' => $items[0] ?? null,
                        ],
                        'fonte' => 'portal_transparencia',
                        'imported_at' => now()->toDateTimeString(),
                    ];
                    $batch[] = $row;
                    $cityRows++;

                    if ($sleepUs > 0) {
                        usleep($sleepUs);
                    }
                }
            }

            $byCity[] = [
                'city_id' => $city->id,
                'city' => $city->name,
                'uf' => $city->uf,
                'ibge' => $ibge,
                'fetched' => $cityFetched,
                'rows' => $cityRows,
            ];
        }

        if ($dryRun) {
            return [
                'success' => true,
                'message' => __('Dry-run: :c município(s), :r linha(s) a gravar (:f itens Portal).', [
                    'c' => (string) $cities->count(),
                    'r' => (string) count($batch),
                    'f' => (string) $fetched,
                ]),
                'cities' => $cities->count(),
                'upserted' => 0,
                'fetched' => $fetched,
                'dry_run' => true,
                'by_city' => $byCity,
            ];
        }

        try {
            $upserted = $this->snapshots->upsertBatch($batch);
        } catch (\Throwable $e) {
            Log::warning('cadunico.beneficios_portal_upsert_failed', ['message' => $e->getMessage()]);

            return array_merge($empty, [
                'success' => false,
                'cities' => $cities->count(),
                'fetched' => $fetched,
                'message' => __('Falha ao gravar benefícios: :erro', ['erro' => $e->getMessage()]),
                'by_city' => $byCity,
            ]);
        }

        return [
            'success' => true,
            'message' => __('Benefícios Portal: :u linha(s) em :c município(s) (:f itens API).', [
                'u' => (string) $upserted,
                'c' => (string) $cities->count(),
                'f' => (string) $fetched,
            ]),
            'cities' => $cities->count(),
            'upserted' => $upserted,
            'fetched' => $fetched,
            'dry_run' => false,
            'by_city' => $byCity,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPrograma(
        string $programa,
        string $ibge,
        int $mesAno,
        string $apiKey,
        int $timeout,
        int $maxPages,
    ): array {
        return match ($programa) {
            MunicipalBenefitSnapshot::PROGRAMA_PBF => $this->portal->bolsaFamiliaPorMunicipio($ibge, $mesAno, $apiKey, $timeout, $maxPages),
            MunicipalBenefitSnapshot::PROGRAMA_NBF => $this->portal->novoBolsaFamiliaPorMunicipio($ibge, $mesAno, $apiKey, $timeout, $maxPages),
            MunicipalBenefitSnapshot::PROGRAMA_BPC => $this->portal->bpcPorMunicipio($ibge, $mesAno, $apiKey, $timeout, $maxPages),
            default => [],
        };
    }

    /**
     * @param  list<string>|string|null  $raw
     * @return list<string>
     */
    private function normalizeProgramas(array|string|null $raw): array
    {
        $list = is_string($raw)
            ? array_map('trim', explode(',', $raw))
            : (is_array($raw) ? $raw : MunicipalBenefitSnapshot::PROGRAMAS);

        $out = [];
        foreach ($list as $item) {
            $p = strtolower(trim((string) $item));
            if (in_array($p, MunicipalBenefitSnapshot::PROGRAMAS, true)) {
                $out[] = $p;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : MunicipalBenefitSnapshot::PROGRAMAS;
    }
}
