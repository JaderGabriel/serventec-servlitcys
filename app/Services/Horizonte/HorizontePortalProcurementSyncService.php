<?php

namespace App\Services\Horizonte;

use App\Models\PortalProcurementSnapshot;
use App\Repositories\PortalProcurementSnapshotRepository;
use App\Support\Funding\PortalTransparenciaApiClient;
use App\Support\Horizonte\PortalProcurementConfig;
use Illuminate\Support\Facades\Log;

/**
 * Sync HOR-08d/e/f — contratos/licitações por órgão SIAFI + enrich por CNPJ curado.
 */
final class HorizontePortalProcurementSyncService
{
    public function __construct(
        private PortalTransparenciaApiClient $portal,
        private PortalProcurementSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  array{
     *   year?: int,
     *   orgao?: string|null,
     *   tipos?: list<string>|string|null,
     *   dry_run?: bool,
     *   max_pages?: int|null,
     *   licitacoes_max_months?: int|null,
     *   skip_orgaos?: bool,
     *   skip_vendors?: bool,
     * }  $options
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   message: string,
     *   contratos_fetched: int,
     *   licitacoes_fetched: int,
     *   upserted: int,
     *   vendor_matched: int,
     *   vendor_cnpj_fetched: int,
     *   itens_software: int,
     *   by_orgao: list<array{codigo: string, sigla: string, contratos: int, licitacoes: int, upserted: int, vendor_matched: int}>
     * }
     */
    public function sync(array $options = []): array
    {
        $empty = [
            'success' => false,
            'message' => '',
            'contratos_fetched' => 0,
            'licitacoes_fetched' => 0,
            'upserted' => 0,
            'vendor_matched' => 0,
            'vendor_cnpj_fetched' => 0,
            'itens_software' => 0,
            'by_orgao' => [],
        ];

        if (! PortalProcurementConfig::enabled()) {
            return array_merge($empty, [
                'success' => true,
                'skipped' => true,
                'message' => __('Procurement Portal desactivado (HORIZONTE_PROCUREMENT_ENABLED).'),
            ]);
        }

        $portalCfg = config('ieducar.other_funding.public_queries.portal_transparencia', []);
        $apiKey = trim((string) ($portalCfg['api_key'] ?? ''));
        if ($apiKey === '' || ! filter_var($portalCfg['enabled'] ?? true, FILTER_VALIDATE_BOOL)) {
            return array_merge($empty, [
                'success' => true,
                'skipped' => true,
                'message' => __('Portal da Transparência desactivado ou sem PORTAL_TRANSPARENCIA_API_KEY.'),
            ]);
        }

        $year = (int) ($options['year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        if ($year < 2000 || $year > 2100) {
            return array_merge($empty, [
                'message' => __('Ano inválido.'),
            ]);
        }

        $skipOrgaos = (bool) ($options['skip_orgaos'] ?? false);
        $skipVendors = (bool) ($options['skip_vendors'] ?? false);
        $orgaos = $skipOrgaos ? [] : $this->resolveOrgaos($options['orgao'] ?? null);
        $vendors = PortalProcurementConfig::softwareVendors();

        if ($orgaos === [] && ($skipVendors || $vendors === [])) {
            return array_merge($empty, [
                'message' => __('Nenhum órgão SIAFI nem CNPJ curado configurado.'),
            ]);
        }

        $tipos = $this->resolveTipos($options['tipos'] ?? null);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $timeout = max(15, (int) config('horizonte.transparency.procurement.http_timeout', 30));
        $maxPages = max(1, min(30, (int) ($options['max_pages'] ?? config('horizonte.transparency.procurement.max_pages_per_org', 5))));
        $licitMonths = max(1, min(12, (int) ($options['licitacoes_max_months'] ?? config('horizonte.transparency.procurement.licitacoes_max_months', 12))));
        $itemKeywords = $this->itemSoftwareKeywords();

        $dataInicial = sprintf('01/01/%04d', $year);
        $dataFinal = sprintf('31/12/%04d', $year);

        $contratosFetched = 0;
        $licitacoesFetched = 0;
        $upserted = 0;
        $vendorMatched = 0;
        $itensSoftware = 0;
        $byOrgao = [];

        foreach ($orgaos as $orgao) {
            $codigo = $orgao['codigo'];
            $rows = [];
            $cCount = 0;
            $lCount = 0;
            $vCount = 0;
            $swCount = 0;

            if (in_array(PortalProcurementSnapshot::TIPO_CONTRATO, $tipos, true)) {
                $items = $this->portal->contratos($codigo, $apiKey, $dataInicial, $dataFinal, $timeout, $maxPages);
                $cCount = count($items);
                $contratosFetched += $cCount;
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mapped = $this->mapContrato($item, $orgao, $year, $vendors, $itemKeywords);
                    if ($mapped === null) {
                        continue;
                    }
                    if ($mapped['vendor_matched']) {
                        $vCount++;
                    }
                    if ($mapped['itens_software']) {
                        $swCount++;
                    }
                    $rows[] = $mapped;
                }
                usleep(80_000);
            }

            if (in_array(PortalProcurementSnapshot::TIPO_LICITACAO, $tipos, true)) {
                $items = $this->portal->licitacoesAno($codigo, $year, $apiKey, $timeout, $maxPages, $licitMonths);
                $lCount = count($items);
                $licitacoesFetched += $lCount;
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mapped = $this->mapLicitacao($item, $orgao, $year);
                    if ($mapped === null) {
                        continue;
                    }
                    $rows[] = $mapped;
                }
            }

            $orgUpserted = 0;
            if (! $dryRun && $rows !== []) {
                $orgUpserted = $this->snapshots->upsertBatch($rows);
                $upserted += $orgUpserted;
            } elseif ($dryRun) {
                $orgUpserted = count($rows);
            }

            $vendorMatched += $vCount;
            $itensSoftware += $swCount;
            $byOrgao[] = [
                'codigo' => $codigo,
                'sigla' => $orgao['sigla'],
                'contratos' => $cCount,
                'licitacoes' => $lCount,
                'upserted' => $orgUpserted,
                'vendor_matched' => $vCount,
            ];

            Log::info('horizonte.procurement_org_synced', [
                'orgao' => $codigo,
                'sigla' => $orgao['sigla'],
                'year' => $year,
                'contratos' => $cCount,
                'licitacoes' => $lCount,
                'dry_run' => $dryRun,
            ]);
        }

        $vendorCnpjFetched = 0;
        if (! $skipVendors && $vendors !== []) {
            $vendorResult = $this->enrichFromVendors(
                $vendors,
                $year,
                $apiKey,
                $timeout,
                $dryRun,
                $itemKeywords,
            );
            $vendorCnpjFetched = $vendorResult['fetched'];
            $upserted += $vendorResult['upserted'];
            $vendorMatched += $vendorResult['vendor_matched'];
            $itensSoftware += $vendorResult['itens_software'];
            $contratosFetched += $vendorResult['fetched'];
        }

        return [
            'success' => true,
            'message' => $dryRun
                ? __('Dry-run: :c contrato(s) + :l licitação(ões); vendors CNPJ :vn (:v match, :sw itens software).', [
                    'c' => (string) $contratosFetched,
                    'l' => (string) $licitacoesFetched,
                    'vn' => (string) $vendorCnpjFetched,
                    'v' => (string) $vendorMatched,
                    'sw' => (string) $itensSoftware,
                ])
                : __('Procurement: :u linha(s) (:c contratos, :l licitações; :v vendor; :sw itens software).', [
                    'u' => (string) $upserted,
                    'c' => (string) $contratosFetched,
                    'l' => (string) $licitacoesFetched,
                    'v' => (string) $vendorMatched,
                    'sw' => (string) $itensSoftware,
                ]),
            'contratos_fetched' => $contratosFetched,
            'licitacoes_fetched' => $licitacoesFetched,
            'upserted' => $upserted,
            'vendor_matched' => $vendorMatched,
            'vendor_cnpj_fetched' => $vendorCnpjFetched,
            'itens_software' => $itensSoftware,
            'by_orgao' => $byOrgao,
        ];
    }

    /**
     * @param  array<string, string>  $vendors
     * @param  list<string>  $itemKeywords
     * @return array{fetched: int, upserted: int, vendor_matched: int, itens_software: int}
     */
    private function enrichFromVendors(
        array $vendors,
        int $year,
        string $apiKey,
        int $timeout,
        bool $dryRun,
        array $itemKeywords,
    ): array {
        $maxPages = max(1, min(20, (int) config('horizonte.transparency.procurement.vendor_max_pages', 3)));
        $itensMaxPages = max(1, min(5, (int) config('horizonte.transparency.procurement.itens_max_pages', 2)));
        $itensPerContract = max(0, min(20, (int) config('horizonte.transparency.procurement.itens_per_vendor_contract', 5)));

        $fetched = 0;
        $upserted = 0;
        $matched = 0;
        $software = 0;
        $rows = [];
        $itensFetchedFor = 0;

        foreach ($vendors as $cnpj => $label) {
            $items = $this->portal->contratosPorCnpj((string) $cnpj, $apiKey, $timeout, $maxPages);
            $fetched += count($items);
            usleep(80_000);

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $orgao = $this->orgaoFromContrato($item);
                $forcedVendors = [(string) $cnpj => $label];
                $itens = null;
                $itensSoftware = false;

                if ($itensPerContract > 0 && $itensFetchedFor < $itensPerContract) {
                    $id = $item['id'] ?? null;
                    if ($id !== null && $id !== '') {
                        $itens = $this->portal->itensContratados($id, $apiKey, $timeout, $itensMaxPages);
                        $itensFetchedFor++;
                        $itensSoftware = $this->itensSuggestSoftware($itens, $itemKeywords);
                        usleep(60_000);
                    }
                }

                $mapped = $this->mapContrato($item, $orgao, $year, $forcedVendors, $itemKeywords, $itens, $itensSoftware);
                if ($mapped === null) {
                    continue;
                }
                $matched++;
                if ($mapped['itens_software']) {
                    $software++;
                }
                $rows[] = $mapped;
            }
        }

        if (! $dryRun && $rows !== []) {
            $upserted = $this->snapshots->upsertBatch($rows);
        } elseif ($dryRun) {
            $upserted = count($rows);
        }

        Log::info('horizonte.procurement_vendors_enriched', [
            'year' => $year,
            'vendors' => count($vendors),
            'fetched' => $fetched,
            'itens_software' => $software,
            'dry_run' => $dryRun,
        ]);

        return [
            'fetched' => $fetched,
            'upserted' => $upserted,
            'vendor_matched' => $matched,
            'itens_software' => $software,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{codigo: string, sigla: string, nome: string}
     */
    private function orgaoFromContrato(array $item): array
    {
        $ug = is_array($item['unidadeGestora'] ?? null) ? $item['unidadeGestora'] : [];
        $vinculado = is_array($ug['orgaoVinculado'] ?? null) ? $ug['orgaoVinculado'] : [];
        $maximo = is_array($ug['orgaoMaximo'] ?? null) ? $ug['orgaoMaximo'] : [];

        $codigo = preg_replace('/\D/', '', (string) ($vinculado['codigoSIAFI'] ?? $maximo['codigo'] ?? '')) ?: '00000';

        return [
            'codigo' => $codigo,
            'sigla' => trim((string) ($vinculado['sigla'] ?? $maximo['sigla'] ?? '')),
            'nome' => trim((string) ($vinculado['nome'] ?? $maximo['nome'] ?? $ug['nome'] ?? 'Órgão Portal')),
        ];
    }

    /**
     * @return list<string>
     */
    private function itemSoftwareKeywords(): array
    {
        $raw = config('horizonte.transparency.procurement.item_software_keywords', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $kw) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw !== '') {
                $out[] = $kw;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @param  list<string>  $keywords
     */
    private function itensSuggestSoftware(array $itens, array $keywords): bool
    {
        if ($itens === [] || $keywords === []) {
            return false;
        }

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $blob = mb_strtolower(
                trim((string) ($item['descricao'] ?? '')).' '.
                trim((string) ($item['descComplementarItemCompra'] ?? '')),
            );
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($blob, $kw)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{codigo: string, sigla: string, nome: string}>
     */
    private function resolveOrgaos(?string $filter): array
    {
        $all = PortalProcurementConfig::orgaosSiafi();
        $filter = trim((string) $filter);
        if ($filter === '') {
            return $all;
        }

        $digits = preg_replace('/\D/', '', $filter) ?: '';
        $sigla = strtoupper($filter);

        return array_values(array_filter(
            $all,
            static fn (array $o): bool => ($digits !== '' && $o['codigo'] === $digits)
                || strtoupper($o['sigla']) === $sigla,
        ));
    }

    /**
     * @param  list<string>|string|null  $tipos
     * @return list<string>
     */
    private function resolveTipos(array|string|null $tipos): array
    {
        if ($tipos === null || $tipos === '' || $tipos === []) {
            return [
                PortalProcurementSnapshot::TIPO_CONTRATO,
                PortalProcurementSnapshot::TIPO_LICITACAO,
            ];
        }

        if (is_string($tipos)) {
            $tipos = explode(',', $tipos);
        }

        $out = [];
        foreach ($tipos as $t) {
            $t = strtolower(trim((string) $t));
            if (in_array($t, ['contrato', 'contratos'], true)) {
                $out[] = PortalProcurementSnapshot::TIPO_CONTRATO;
            }
            if (in_array($t, ['licitacao', 'licitacoes', 'licitação', 'licitações'], true)) {
                $out[] = PortalProcurementSnapshot::TIPO_LICITACAO;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : [
            PortalProcurementSnapshot::TIPO_CONTRATO,
            PortalProcurementSnapshot::TIPO_LICITACAO,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{codigo: string, sigla: string, nome: string}  $orgao
     * @param  array<string, string>  $vendors
     * @param  list<string>  $itemKeywords
     * @param  list<array<string, mixed>>|null  $itens
     * @return array<string, mixed>|null
     */
    private function mapContrato(
        array $item,
        array $orgao,
        int $year,
        array $vendors,
        array $itemKeywords = [],
        ?array $itens = null,
        ?bool $itensSoftware = null,
    ): ?array {
        $id = $item['id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }

        $fornecedor = is_array($item['fornecedor'] ?? null) ? $item['fornecedor'] : [];
        $cnpj = preg_replace('/\D/', '', (string) ($fornecedor['cnpjFormatado'] ?? $fornecedor['cnpj'] ?? '')) ?: '';
        $nome = trim((string) ($fornecedor['nome'] ?? $fornecedor['razaoSocialReceita'] ?? $fornecedor['nomeFantasiaReceita'] ?? ''));
        $ug = is_array($item['unidadeGestora'] ?? null) ? $item['unidadeGestora'] : [];
        $compra = is_array($item['compra'] ?? null) ? $item['compra'] : [];

        [$matched, $label] = $this->matchVendor($cnpj, $vendors);

        $valorInicial = $item['valorInicialCompra'] ?? null;
        $valorFinal = $item['valorFinalCompra'] ?? null;

        $objeto = (string) ($item['objeto'] ?? ($compra['objeto'] ?? ''));
        if ($itensSoftware === null) {
            $itensSoftware = $this->objetoSuggestSoftware($objeto, $itemKeywords);
        }

        return [
            'tipo' => PortalProcurementSnapshot::TIPO_CONTRATO,
            'ano' => $year,
            'codigo_orgao' => $orgao['codigo'],
            'orgao_sigla' => $orgao['sigla'],
            'orgao_nome' => $orgao['nome'],
            'external_id' => (string) $id,
            'numero' => $item['numero'] ?? null,
            'objeto' => $objeto !== '' ? $objeto : null,
            'situacao' => $item['situacaoContrato'] ?? null,
            'modalidade' => $item['modalidadeCompra'] ?? null,
            'valor' => is_numeric($valorInicial) ? (float) $valorInicial : null,
            'valor_final' => is_numeric($valorFinal) ? (float) $valorFinal : null,
            'data_assinatura' => $item['dataAssinatura'] ?? null,
            'data_inicio_vigencia' => $item['dataInicioVigencia'] ?? null,
            'data_fim_vigencia' => $item['dataFimVigencia'] ?? null,
            'data_publicacao' => $item['dataPublicacaoDOU'] ?? null,
            'fornecedor_cnpj' => strlen($cnpj) === 14 ? $cnpj : null,
            'fornecedor_nome' => $nome !== '' ? $nome : null,
            'ibge_municipio' => null,
            'municipio_nome' => null,
            'uf' => null,
            'ug_codigo' => $ug['codigo'] ?? null,
            'ug_nome' => $ug['nome'] ?? null,
            'vendor_matched' => $matched,
            'vendor_label' => $label,
            'itens_software' => (bool) $itensSoftware,
            'itens' => $itens,
            'payload' => $item,
            'fonte' => 'portal_transparencia',
        ];
    }

    /**
     * @param  list<string>  $keywords
     */
    private function objetoSuggestSoftware(string $objeto, array $keywords): bool
    {
        if ($objeto === '' || $keywords === []) {
            return false;
        }
        $blob = mb_strtolower($objeto);
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($blob, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{codigo: string, sigla: string, nome: string}  $orgao
     * @return array<string, mixed>|null
     */
    private function mapLicitacao(array $item, array $orgao, int $year): ?array
    {
        $id = $item['id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }

        $licitacao = is_array($item['licitacao'] ?? null) ? $item['licitacao'] : [];
        $municipio = is_array($item['municipio'] ?? null) ? $item['municipio'] : [];
        $ufObj = is_array($municipio['uf'] ?? null) ? $municipio['uf'] : [];
        $ug = is_array($item['unidadeGestora'] ?? null) ? $item['unidadeGestora'] : [];

        $valor = $item['valor'] ?? null;

        return [
            'tipo' => PortalProcurementSnapshot::TIPO_LICITACAO,
            'ano' => $year,
            'codigo_orgao' => $orgao['codigo'],
            'orgao_sigla' => $orgao['sigla'],
            'orgao_nome' => $orgao['nome'],
            'external_id' => (string) $id,
            'numero' => $licitacao['numero'] ?? null,
            'objeto' => $licitacao['objeto'] ?? null,
            'situacao' => $item['situacaoCompra'] ?? null,
            'modalidade' => $item['modalidadeLicitacao'] ?? null,
            'valor' => is_numeric($valor) ? (float) $valor : null,
            'valor_final' => null,
            'data_assinatura' => null,
            'data_inicio_vigencia' => null,
            'data_fim_vigencia' => null,
            'data_publicacao' => $item['dataPublicacao'] ?? $item['dataAbertura'] ?? null,
            'fornecedor_cnpj' => null,
            'fornecedor_nome' => null,
            'ibge_municipio' => $municipio['codigoIBGE'] ?? null,
            'municipio_nome' => $municipio['nomeIBGE'] ?? null,
            'uf' => $ufObj['sigla'] ?? null,
            'ug_codigo' => $ug['codigo'] ?? null,
            'ug_nome' => $ug['nome'] ?? null,
            'vendor_matched' => false,
            'vendor_label' => null,
            'itens_software' => false,
            'itens' => null,
            'payload' => $item,
            'fonte' => 'portal_transparencia',
        ];
    }

    /**
     * @param  array<string, string>  $vendors
     * @return array{0: bool, 1: string|null}
     */
    private function matchVendor(string $cnpj, array $vendors): array
    {
        if ($cnpj === '' || $vendors === [] || ! isset($vendors[$cnpj])) {
            return [false, null];
        }

        return [true, $vendors[$cnpj]];
    }
}
