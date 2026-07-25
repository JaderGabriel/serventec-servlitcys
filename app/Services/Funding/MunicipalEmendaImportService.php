<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalEmendaSnapshotRepository;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Importa emendas educação do Portal (catálogo anual → match por localidadeDoGasto).
 */
final class MunicipalEmendaImportService
{
    public function __construct(
        private PortalTransparenciaApiClient $portal,
        private MunicipalEmendaSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  Collection<int, City>  $cities
     * @return array{
     *   success: bool,
     *   message: string,
     *   catalog_rows: int,
     *   matched_rows: int,
     *   upserted: int,
     *   documentos_fetched: int,
     *   by_city: list<array{city_id: int, city: string, uf: string, ibge: ?string, rows: int}>
     * }
     */
    public function importForCities(Collection $cities, int $year, bool $dryRun = false): array
    {
        $portalCfg = config('ieducar.other_funding.public_queries.portal_transparencia', []);
        $apiKey = trim((string) ($portalCfg['api_key'] ?? ''));
        $enabled = filter_var($portalCfg['enabled'] ?? true, FILTER_VALIDATE_BOOL);

        if (! $enabled || $apiKey === '') {
            return [
                'success' => false,
                'message' => __('Portal da Transparência desactivado ou sem PORTAL_TRANSPARENCIA_API_KEY.'),
                'catalog_rows' => 0,
                'matched_rows' => 0,
                'upserted' => 0,
                'documentos_fetched' => 0,
                'by_city' => [],
            ];
        }

        if ($year < 2000 || $year > 2100) {
            return [
                'success' => false,
                'message' => __('Ano inválido.'),
                'catalog_rows' => 0,
                'matched_rows' => 0,
                'upserted' => 0,
                'documentos_fetched' => 0,
                'by_city' => [],
            ];
        }

        $timeout = max(15, (int) ($portalCfg['emendas_timeout'] ?? config('ieducar.other_funding.public_queries.timeout', 20)));
        $maxPages = max(1, min(100, (int) ($portalCfg['emendas_max_pages'] ?? 50)));
        $fetchDocs = filter_var($portalCfg['emendas_fetch_documentos'] ?? true, FILTER_VALIDATE_BOOL);
        $docsMaxPages = max(1, min(10, (int) ($portalCfg['emendas_documentos_max_pages'] ?? 3)));

        $catalog = $this->yearCatalog($year, $apiKey, $timeout, $maxPages);
        $byCity = [];
        $matchedRows = 0;
        $upserted = 0;
        $docsFetched = 0;
        $docsCache = [];

        foreach ($cities as $city) {
            if (! $city instanceof City) {
                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            $cityRow = [
                'city_id' => (int) $city->id,
                'city' => (string) $city->name,
                'uf' => (string) $city->uf,
                'ibge' => $ibge,
                'rows' => 0,
            ];

            if ($ibge === null) {
                $byCity[] = $cityRow;

                continue;
            }

            $matched = [];
            foreach ($catalog as $emenda) {
                $localidade = (string) ($emenda['localidadeDoGasto'] ?? '');
                if (! PortalTransparenciaApiClient::localidadeMatchesMunicipio(
                    $localidade,
                    (string) $city->name,
                    (string) $city->uf,
                )) {
                    continue;
                }

                $codigo = trim((string) ($emenda['codigoEmenda'] ?? ''));
                if ($codigo === '') {
                    continue;
                }

                $documentos = null;
                if ($fetchDocs && ! $dryRun) {
                    if (! array_key_exists($codigo, $docsCache)) {
                        $docsCache[$codigo] = $this->portal->emendasDocumentos($codigo, $apiKey, $timeout, $docsMaxPages);
                        $docsFetched++;
                        usleep(80_000);
                    }
                    $documentos = $docsCache[$codigo];
                }

                $matched[] = $this->mapEmendaRow($emenda, $city, $ibge, $year, $documentos);
            }

            $cityRow['rows'] = count($matched);
            $matchedRows += count($matched);

            if (! $dryRun && $matched !== []) {
                $upserted += $this->snapshots->upsertBatch($city, $matched);
            }

            $byCity[] = $cityRow;
        }

        return [
            'success' => true,
            'message' => $dryRun
                ? __('Dry-run: :m emenda(s) corresponderiam em :c município(s) (catálogo :n).', [
                    'm' => (string) $matchedRows,
                    'c' => (string) count($byCity),
                    'n' => (string) count($catalog),
                ])
                : __('Importadas :u linha(s) de emendas (:m matches; catálogo :n).', [
                    'u' => (string) $upserted,
                    'm' => (string) $matchedRows,
                    'n' => (string) count($catalog),
                ]),
            'catalog_rows' => count($catalog),
            'matched_rows' => $matchedRows,
            'upserted' => $upserted,
            'documentos_fetched' => $docsFetched,
            'by_city' => $byCity,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function yearCatalog(int $year, string $apiKey, int $timeout, int $maxPages): array
    {
        $ttl = max(300, (int) config('ieducar.other_funding.public_queries.cache_ttl_seconds', 3600));
        $cacheKey = 'portal_transparencia:emendas_catalog:'.$year.':'.PortalTransparenciaApiClient::FUNCAO_EDUCACAO.':'.$maxPages;

        /** @var list<array<string, mixed>> $catalog */
        $catalog = Cache::remember($cacheKey, $ttl, function () use ($year, $apiKey, $timeout, $maxPages): array {
            Log::info('portal_transparencia.emendas_catalog_fetch', [
                'year' => $year,
                'max_pages' => $maxPages,
            ]);

            return $this->portal->emendas(
                $year,
                $apiKey,
                $timeout,
                $maxPages,
                PortalTransparenciaApiClient::FUNCAO_EDUCACAO,
            );
        });

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * @param  array<string, mixed>  $emenda
     * @param  list<array<string, mixed>>|null  $documentos
     * @return array<string, mixed>
     */
    private function mapEmendaRow(array $emenda, City $city, string $ibge, int $year, ?array $documentos): array
    {
        return [
            'city_id' => $city->id,
            'ibge_municipio' => $ibge,
            'ano' => (int) ($emenda['ano'] ?? $year),
            'codigo_emenda' => (string) ($emenda['codigoEmenda'] ?? ''),
            'numero_emenda' => $emenda['numeroEmenda'] ?? null,
            'tipo_emenda' => $emenda['tipoEmenda'] ?? null,
            'autor' => $emenda['autor'] ?? $emenda['nomeAutor'] ?? null,
            'localidade_do_gasto' => $emenda['localidadeDoGasto'] ?? null,
            'funcao' => $emenda['funcao'] ?? null,
            'subfuncao' => $emenda['subfuncao'] ?? null,
            'valor_empenhado' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorEmpenhado'] ?? null),
            'valor_liquidado' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorLiquidado'] ?? null),
            'valor_pago' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorPago'] ?? null),
            'valor_resto_inscrito' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorRestoInscrito'] ?? null),
            'valor_resto_cancelado' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorRestoCancelado'] ?? null),
            'valor_resto_pago' => PortalTransparenciaApiClient::parseValorBrl($emenda['valorRestoPago'] ?? null),
            'documentos' => $documentos,
            'payload' => $emenda,
            'fonte' => 'portal_transparencia',
        ];
    }
}
