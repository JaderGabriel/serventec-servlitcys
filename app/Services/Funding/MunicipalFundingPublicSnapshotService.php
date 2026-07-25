<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Funding\PortalTransparenciaApiClient;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Consultas automáticas a fontes públicas (FNDE, Tesouro, Portal da Transparência) por município/ano.
 */
final class MunicipalFundingPublicSnapshotService
{
    public function __construct(
        private FundebMunicipioReferenceRepository $fundebRefs,
        private FundebOpenDataImportService $fundebImport,
        private MunicipalTransferSnapshotRepository $transferSnapshots,
        private PortalTransparenciaApiClient $portalClient = new PortalTransparenciaApiClient,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?City $city, IeducarFilterState $filters): array
    {
        $cfg = config('ieducar.other_funding.public_queries', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city?->ibge_municipio);
        $year = $this->resolveYear($filters);

        $empty = [
            'enabled' => $enabled,
            'available' => false,
            'ibge' => $ibge,
            'year' => $year,
            'intro' => '',
            'queries' => [],
            'fetched_at' => null,
        ];

        if (! $enabled || $city === null || $ibge === null || $year === null) {
            $empty['intro'] = $ibge === null
                ? __('Configure o código IBGE do município na cidade para consultar bases públicas automaticamente.')
                : ($year === null
                    ? __('Selecione o ano letivo para consultar relatórios e prévias públicas do município.')
                    : '');

            return $empty;
        }

        $ttl = max(60, (int) ($cfg['cache_ttl_seconds'] ?? 3600));
        $cacheKey = 'other_funding_public:'.(int) $city->id.':'.$ibge.':'.$year;

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($cacheKey, $ttl, fn (): array => $this->buildFresh($city, $filters, $ibge, $year));

        return $payload;
    }

    /**
     * Invalida e reconstrói o snapshot de consultas públicas (aba Financiamentos).
     *
     * @return array<string, mixed>
     */
    public function refresh(City $city, IeducarFilterState $filters): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $year = $this->resolveYear($filters);
        if ($ibge !== null && $year !== null) {
            Cache::forget('other_funding_public:'.(int) $city->id.':'.$ibge.':'.$year);
        }

        return $this->build($city, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFresh(City $city, IeducarFilterState $filters, string $ibge, int $year): array
    {
        $cfg = config('ieducar.other_funding.public_queries', []);
        $timeout = max(5, (int) ($cfg['timeout'] ?? 12));

        $queries = [
            $this->queryFundebReferencia($city, $filters, $ibge, $year),
            $this->queryFndeDadosAbertos($ibge, $year, $cfg, $timeout),
            $this->queryTesouroTransferencias($city, $ibge, $year, $cfg, $timeout),
            $this->queryPortalTransparencia($ibge, $year, $cfg, $timeout),
        ];

        return [
            'enabled' => true,
            'available' => true,
            'ibge' => $ibge,
            'year' => $year,
            'intro' => __(
                'Consultas automáticas por IBGE :ibge e ano :ano. Os valores abaixo vêm de bases públicas (FNDE, Tesouro, Portal da Transparência) e não substituem prestação de contas nem o cadastro do i-Educar.',
                ['ibge' => $ibge, 'ano' => $year]
            ),
            'queries' => $queries,
            'fetched_at' => Carbon::now()->toIso8601String(),
        ];
    }

    private function resolveYear(IeducarFilterState $filters): ?int
    {
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return null;
        }

        $y = (int) $filters->ano_letivo;

        return $y >= 2000 ? $y : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryFundebReferencia(City $city, IeducarFilterState $filters, string $ibge, int $year): array
    {
        $ref = $this->fundebRefs->findForCityYear($city, $year);
        $resolved = FundebMunicipalReferenceResolver::resolve($city, $filters);

        $rows = [];
        if ($ref !== null) {
            $rows[] = [
                'label' => __('VAAF importado (base local)'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) $ref->vaaf),
            ];
            if ($ref->vaat !== null) {
                $rows[] = [
                    'label' => __('VAAT (base local)'),
                    'value' => DiscrepanciesFundingImpact::formatBrl((float) $ref->vaat),
                ];
            }
            if ($ref->complementacao_vaar !== null) {
                $rows[] = [
                    'label' => __('Complementação VAAR (base local)'),
                    'value' => DiscrepanciesFundingImpact::formatBrl((float) $ref->complementacao_vaar),
                ];
            }
            $rows[] = [
                'label' => __('Fonte / importação'),
                'value' => (string) ($ref->fonte ?? '—').($ref->imported_at ? ' · '.$ref->imported_at->format('d/m/Y H:i') : ''),
            ];
        }

        $previa = is_array($resolved['previa'] ?? null) ? $resolved['previa'] : null;
        if ($previa !== null) {
            $rows[] = [
                'label' => __('Prévia federal (referência)'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) ($previa['vaaf'] ?? 0)),
            ];
        }

        if (filled($resolved['divergencia']['mensagem'] ?? null)) {
            $rows[] = [
                'label' => __('Comparação municipal × prévia'),
                'value' => (string) $resolved['divergencia']['mensagem'],
            ];
        }

        $status = $rows !== [] ? 'success' : 'empty';

        return $this->queryResult(
            'fundeb_referencia',
            __('FUNDEB — referência municipal e prévia'),
            $status,
            __('Base local (fundeb_municipio_references) + prévia configurada'),
            'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas',
            $rows,
            $ref === null
                ? __('Sem VAAF importado para este ano. Use a sincronização FNDE no admin ou importe dados abertos.')
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function queryFndeDadosAbertos(string $ibge, int $year, array $cfg, int $timeout): array
    {
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $rows = [];
        $note = null;
        $status = 'empty';

        try {
            $cached = $this->fundebImport->readCachedRowOnly($ibge, $year);
            if ($cached !== null) {
                $rows[] = ['label' => __('VAAF (cache FNDE)'), 'value' => DiscrepanciesFundingImpact::formatBrl((float) ($cached['vaaf'] ?? 0))];
                if (isset($cached['vaat'])) {
                    $rows[] = ['label' => __('VAAT (cache FNDE)'), 'value' => DiscrepanciesFundingImpact::formatBrl((float) $cached['vaat'])];
                }
                $status = 'success';
                $note = (string) ($cached['notas'] ?? __('Registo em cache local de dados abertos FNDE.'));
            } elseif ((bool) ($cfg['live_fnde_fetch'] ?? false)) {
                $live = $this->fetchFndeLive($ibge, $year, $timeout);
                if ($live !== null) {
                    $rows[] = ['label' => __('VAAF (consulta CKAN)'), 'value' => DiscrepanciesFundingImpact::formatBrl((float) ($live['vaaf'] ?? 0))];
                    $status = 'success';
                    $note = __('Consulta em tempo real ao CKAN FNDE.');
                }
            }
        } catch (\Throwable $e) {
            return $this->queryResult(
                'fnde_dados_abertos',
                __('FNDE — dados abertos (CKAN)'),
                'error',
                'CKAN FNDE',
                $base,
                [],
                $e->getMessage()
            );
        }

        if ($rows === []) {
            $note = __('Nenhum registro em cache para IBGE/ano. Active IEDUCAR_OTHER_FUNDING_LIVE_FNDE=true ou sincronize FUNDEB no admin.');
        }

        return $this->queryResult(
            'fnde_dados_abertos',
            __('FNDE — dados abertos (CKAN)'),
            $status,
            'CKAN FNDE',
            $base.'/dataset',
            $rows,
            $note
        );
    }

    /**
     * @return ?array{vaaf: float, vaat?: float}
     */
    private function fetchFndeLive(string $ibge, int $year, int $timeout): ?array
    {
        $resourceId = trim((string) config('ieducar.fundeb.open_data.resource_id', ''));
        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        if ($resourceId === '') {
            return null;
        }

        $ibgeFields = config('ieducar.fundeb.open_data.fields.ibge', ['codigo_ibge', 'ibge']);
        $anoFields = config('ieducar.fundeb.open_data.fields.ano', ['ano', 'nu_ano']);
        $vaafFields = config('ieducar.fundeb.open_data.fields.vaaf', ['vaaf', 'vaa']);

        foreach (is_array($ibgeFields) ? $ibgeFields : [] as $ibgeField) {
            foreach (is_array($anoFields) ? $anoFields : [] as $anoField) {
                $filters = json_encode([$ibgeField => $ibge, $anoField => $year], JSON_THROW_ON_ERROR);
                $records = $this->ckanDatastoreSearch($base, $resourceId, $filters, $timeout, 3);
                $parsed = $this->parseFndeRecord($records, $ibge, $vaafFields);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        $records = $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, 200, $ibge);

        return $this->parseFndeRecord($records, $ibge, $vaafFields);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $vaafFields
     * @return ?array{vaaf: float, vaat?: float}
     */
    private function parseFndeRecord(array $records, string $ibge, array $vaafFields): ?array
    {
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $norm = [];
            foreach ($record as $k => $v) {
                $norm[strtolower((string) $k)] = $v;
            }
            $rowIbge = preg_replace('/\D/', '', (string) ($norm['codigo_ibge'] ?? $norm['co_municipio'] ?? $norm['ibge'] ?? ''));
            if ($rowIbge !== $ibge) {
                continue;
            }
            $vaaf = null;
            foreach ($vaafFields as $field) {
                $key = strtolower($field);
                if (isset($norm[$key]) && is_numeric($norm[$key])) {
                    $vaaf = (float) $norm[$key];
                    break;
                }
            }
            if ($vaaf === null || $vaaf <= 0) {
                continue;
            }
            $vaat = isset($norm['vaat']) && is_numeric($norm['vaat']) ? (float) $norm['vaat'] : null;

            return array_filter(['vaaf' => $vaaf, 'vaat' => $vaat], static fn ($v) => $v !== null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function queryTesouroTransferencias(City $city, string $ibge, int $year, array $cfg, int $timeout): array
    {
        $tesouro = is_array($cfg['tesouro_ckan'] ?? null) ? $cfg['tesouro_ckan'] : [];
        if (! (bool) ($tesouro['enabled'] ?? true)) {
            return $this->queryResult(
                'tesouro_transferencias',
                __('Tesouro Transparente — transferências'),
                'skipped',
                __('Tesouro CKAN'),
                'https://www.tesourotransparente.gov.br/ckan',
                [],
                __('Consulta desativada na configuração.')
            );
        }

        $base = rtrim((string) ($tesouro['base_url'] ?? 'https://www.tesourotransparente.gov.br/ckan'), '/');
        $resourceId = trim((string) ($tesouro['resource_id'] ?? ''));
        if ($resourceId === '') {
            $resourceId = $this->discoverTesouroResourceId($base, (string) ($tesouro['package_id'] ?? ''), $timeout);
        }

        if ($resourceId === '') {
            return $this->queryResult(
                'tesouro_transferencias',
                __('Tesouro Transparente — transferências'),
                'empty',
                __('Tesouro CKAN'),
                $base.'/dataset/transferencias-obrigatorias-da-uniao-por-municipio',
                [],
                __('Recurso CKAN não configurado (IEDUCAR_TESOURO_TRANSFERENCIAS_RESOURCE_ID).')
            );
        }

        $stored = $this->transferSnapshots->forCityYear($city, $year, 'tesouro');

        try {
            $rows = [];
            $note = null;

            if ($stored !== []) {
                foreach ($stored as $snap) {
                    $rows[] = [
                        'label' => ($snap->programa_label ?? $snap->programa_id).' ('.__('importado').')',
                        'value' => DiscrepanciesFundingImpact::formatBrl((float) $snap->valor),
                    ];
                }
                $rows[] = [
                    'label' => __('Importado em'),
                    'value' => $stored[0]->imported_at?->format('d/m/Y H:i') ?? '—',
                ];
                $note = __('Valores da base local (sem somar consulta CKAN em paralelo). Repasses FUNDEB: aba Finanças → Tempo Real.');
            } else {
                $records = $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, 500, $ibge);
                $rows = $this->summarizeTesouroRecords($records, $ibge, $year);
                $note = $rows === []
                    ? __('Nenhuma linha encontrada para o IBGE no limite da consulta — confira o dataset no portal.')
                    : __('Prévia CKAN filtrada por IBGE — não some com VAAF nem com repasses já importados.');
            }

            return $this->queryResult(
                'tesouro_transferencias',
                __('Tesouro Transparente — transferências ao município'),
                $rows !== [] ? 'success' : 'empty',
                __('Tesouro CKAN'),
                $base,
                $rows,
                $note
            );
        } catch (\Throwable $e) {
            return $this->queryResult(
                'tesouro_transferencias',
                __('Tesouro Transparente — transferências'),
                'error',
                __('Tesouro CKAN'),
                $base,
                [],
                $e->getMessage()
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array{label: string, value: string}>
     */
    private function summarizeTesouroRecords(array $records, string $ibge, int $year): array
    {
        $keywords = ['fundeb', 'fnde', 'educa', 'escolar', 'pnae', 'pnate', 'pdde', 'salario'];
        $matched = [];
        $totalValor = 0.0;
        $count = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $blob = strtolower(json_encode($record, JSON_UNESCAPED_UNICODE) ?: '');
            if (! str_contains($blob, $ibge)) {
                continue;
            }
            $anoRecord = $this->extractYearFromRecord($record);
            if ($anoRecord !== null && $anoRecord !== $year) {
                continue;
            }
            $count++;
            $valor = $this->extractNumericValue($record, ['valor', 'vl_transferencia', 'valor_transferencia', 'valor_repassado', 'total']);
            if ($valor !== null) {
                $totalValor += $valor;
            }
            $label = $this->extractLabel($record);
            $hit = false;
            foreach ($keywords as $kw) {
                if (str_contains($blob, $kw)) {
                    $hit = true;
                    break;
                }
            }
            if ($hit && count($matched) < 6) {
                $matched[] = [
                    'label' => mb_substr($label, 0, 80),
                    'value' => $valor !== null
                        ? DiscrepanciesFundingImpact::formatBrl($valor)
                        : __('valor não identificado no registro'),
                ];
            }
        }

        $rows = [];
        if ($count > 0) {
            $rows[] = [
                'label' => __('Registos com IBGE no lote'),
                'value' => (string) $count,
            ];
        }
        if ($totalValor > 0) {
            $rows[] = [
                'label' => __('Soma de valores numéricos detectados'),
                'value' => DiscrepanciesFundingImpact::formatBrl($totalValor),
            ];
        }

        return array_merge($rows, $matched);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function extractYearFromRecord(array $record): ?int
    {
        foreach (['ano', 'exercicio', 'ano_referencia', 'nu_ano'] as $key) {
            foreach ($record as $k => $v) {
                if (strtolower((string) $k) === $key && is_numeric($v)) {
                    $y = (int) $v;

                    return $y >= 2000 && $y <= 2100 ? $y : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $keys
     */
    private function extractNumericValue(array $record, array $keys): ?float
    {
        $norm = [];
        foreach ($record as $k => $v) {
            $norm[strtolower((string) $k)] = $v;
        }
        foreach ($keys as $key) {
            if (isset($norm[$key]) && is_numeric($norm[$key])) {
                return (float) $norm[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function extractLabel(array $record): string
    {
        foreach (['descricao', 'nome_programa', 'programa', 'tipo_transferencia', 'especificacao', 'nome'] as $key) {
            foreach ($record as $k => $v) {
                if (strtolower((string) $k) === $key && filled($v)) {
                    return (string) $v;
                }
            }
        }

        return __('Transferência');
    }

    private function discoverTesouroResourceId(string $base, string $packageId, int $timeout): string
    {
        if ($packageId === '') {
            return '';
        }

        try {
            $response = Http::timeout(min($timeout, 10))
                ->acceptJson()
                ->get($base.'/api/3/action/package_show', ['id' => $packageId]);

            if (! $response->successful()) {
                return '';
            }

            $resources = $response->json('result.resources') ?? [];
            if (! is_array($resources)) {
                return '';
            }
            foreach ($resources as $res) {
                if (! is_array($res)) {
                    continue;
                }
                if (($res['datastore_active'] ?? false) === true && filled($res['id'] ?? null)) {
                    return (string) $res['id'];
                }
            }
            foreach ($resources as $res) {
                if (is_array($res) && filled($res['id'] ?? null)) {
                    return (string) $res['id'];
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function queryPortalTransparencia(string $ibge, int $year, array $cfg, int $timeout): array
    {
        $portal = is_array($cfg['portal_transparencia'] ?? null) ? $cfg['portal_transparencia'] : [];
        if (! (bool) ($portal['enabled'] ?? true)) {
            return $this->queryResult(
                'portal_transparencia',
                __('Portal da Transparência — educação federal'),
                'skipped',
                __('API dados.gov.br / Transparência'),
                'https://portaldatransparencia.gov.br',
                [],
                __('Consulta desativada na configuração.')
            );
        }

        $apiKey = trim((string) ($portal['api_key'] ?? ''));
        $maxRows = max(3, min(20, (int) ($portal['max_rows'] ?? 8)));

        if ($apiKey === '') {
            return $this->queryResult(
                'portal_transparencia',
                __('Portal da Transparência — educação federal'),
                'skipped',
                __('API Portal da Transparência'),
                PortalTransparenciaApiClient::CADASTRO_URL,
                [],
                __('Defina PORTAL_TRANSPARENCIA_API_KEY no .env para consultar recursos recebidos e convênios (função 12) por município.')
            );
        }

        $keywords = is_array($portal['education_keywords'] ?? null)
            ? $portal['education_keywords']
            : ['educacao', 'educação', 'fnde', 'pnae', 'pnate', 'pdde', 'fundeb', 'escolar', 'merenda', 'transporte escolar', 'mec'];

        try {
            $recursos = $this->portalClient->recursosRecebidos($ibge, $year, $apiKey, $timeout, maxPages: 3);
            $conveniosRaw = $this->portalClient->convenios($ibge, $apiKey, $timeout, $year, maxPages: 2);

            $recursosMatched = [];
            $recursosTotal = 0.0;
            foreach ($recursos as $item) {
                if (! is_array($item) || ! $this->portalItemMatchesEducation($item, $keywords)) {
                    continue;
                }
                $anoItem = PortalTransparenciaApiClient::yearFromAnoMes($item['anoMes'] ?? null)
                    ?? (int) preg_replace('/\D/', '', (string) ($item['ano'] ?? $item['exercicio'] ?? ''));
                if ($anoItem >= 2000 && $anoItem !== $year) {
                    continue;
                }
                $valor = $item['valor'] ?? $item['valorEmpenhado'] ?? $item['valorPago'] ?? null;
                if (is_numeric($valor) && (float) $valor > 0) {
                    $recursosTotal += (float) $valor;
                }
                $recursosMatched[] = $item;
            }

            $conveniosMatched = [];
            $conveniosTotal = 0.0;
            foreach ($conveniosRaw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                // Endpoint já filtra funcao=12; keywords só se o blob não for claramente educação
                if (! $this->portalItemMatchesEducation($item, $keywords) && ! $this->portalConvenioLooksEducation($item)) {
                    continue;
                }
                $valor = $item['valorLiberado'] ?? $item['valor'] ?? $item['valorGlobal'] ?? null;
                if (is_numeric($valor) && (float) $valor > 0) {
                    $conveniosTotal += (float) $valor;
                }
                $conveniosMatched[] = $item;
            }

            $highlights = [
                [
                    'label' => __('Recursos educação (amostra)'),
                    'value' => DiscrepanciesFundingImpact::formatBrl($recursosTotal),
                    'hint' => __(':n lançamento(s) com perfil educação/FNDE', ['n' => (string) count($recursosMatched)]),
                ],
                [
                    'label' => __('Convênios educação'),
                    'value' => DiscrepanciesFundingImpact::formatBrl($conveniosTotal),
                    'hint' => __(':n convênio(s) função 12 / keywords', ['n' => (string) count($conveniosMatched)]),
                ],
            ];

            $recursoRows = [];
            foreach (array_slice($recursosMatched, 0, $maxRows) as $item) {
                $orgao = (string) ($item['nomeOrgao'] ?? $item['nomeUG'] ?? $item['orgao'] ?? $item['ug'] ?? '—');
                $mes = PortalTransparenciaApiClient::monthFromAnoMes($item['anoMes'] ?? null);
                $valor = $item['valor'] ?? $item['valorEmpenhado'] ?? $item['valorPago'] ?? null;
                $label = mb_substr($orgao, 0, 64);
                if ($mes !== null) {
                    $label .= ' · '.$mes.'/'.$year;
                }
                $recursoRows[] = [
                    'label' => $label,
                    'value' => is_numeric($valor)
                        ? DiscrepanciesFundingImpact::formatBrl((float) $valor)
                        : __('sem valor'),
                ];
            }

            $convenioRows = [];
            foreach (array_slice($conveniosMatched, 0, min(5, $maxRows)) as $item) {
                $objeto = trim((string) ($item['objeto'] ?? $item['descricao'] ?? $item['nomePrograma'] ?? __('Convênio')));
                $valor = $item['valorLiberado'] ?? $item['valor'] ?? $item['valorGlobal'] ?? null;
                $situacao = trim((string) ($item['situacaoConvenio'] ?? $item['situacao'] ?? ''));
                $label = mb_substr($objeto, 0, 56);
                if ($situacao !== '') {
                    $label .= ' ('.$situacao.')';
                }
                $convenioRows[] = [
                    'label' => $label,
                    'value' => is_numeric($valor)
                        ? DiscrepanciesFundingImpact::formatBrl((float) $valor)
                        : __('sem valor'),
                ];
            }

            $sections = [];
            if ($recursoRows !== []) {
                $sections[] = [
                    'title' => __('Recursos recebidos (amostra)'),
                    'rows' => $recursoRows,
                ];
            }
            if ($convenioRows !== []) {
                $sections[] = [
                    'title' => __('Convênios educação'),
                    'rows' => $convenioRows,
                ];
            }

            $hasData = $recursosMatched !== [] || $conveniosMatched !== [];
            $flatRows = array_merge(
                [
                    [
                        'label' => __('Total recursos (filtro educação)'),
                        'value' => DiscrepanciesFundingImpact::formatBrl($recursosTotal),
                    ],
                    [
                        'label' => __('Total convênios (liberado)'),
                        'value' => DiscrepanciesFundingImpact::formatBrl($conveniosTotal),
                    ],
                ],
                array_slice($recursoRows, 0, 3),
            );

            return $this->queryResult(
                'portal_transparencia',
                __('Portal da Transparência — recursos + convênios educação'),
                $hasData ? 'success' : 'empty',
                __('API Portal da Transparência (CGU)'),
                PortalTransparenciaApiClient::DOCS_URL,
                $flatRows,
                $hasData
                    ? __('Amostra: recursos-recebidos (até 3 páginas) + convênios função 12. Classificação por palavras-chave — não é auditoria completa. Use «Repasse observado» após funding:enrich-consultoria-financiamentos.')
                    : __('Nenhum recurso/convênio com perfil educação neste município/ano na amostra. Confira a chave API, o ano do filtro e o portal manualmente.'),
                $sections !== [] ? $sections : null,
                $highlights,
            );
        } catch (\Throwable $e) {
            return $this->queryResult(
                'portal_transparencia',
                __('Portal da Transparência — educação federal'),
                'error',
                __('API Portal da Transparência'),
                PortalTransparenciaApiClient::DOCS_URL,
                [],
                $e->getMessage()
            );
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keywords
     */
    private function portalItemMatchesEducation(array $item, array $keywords): bool
    {
        $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($blob, strtolower((string) $kw))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function portalConvenioLooksEducation(array $item): bool
    {
        $funcao = strtolower((string) ($item['funcao'] ?? $item['nomeFuncao'] ?? $item['codigoFuncao'] ?? ''));
        if ($funcao === '12' || str_contains($funcao, 'educa')) {
            return true;
        }

        return false;
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

    /**
     * @param  list<array{label: string, value: string}>  $rows
     * @param  list<array{title: string, rows: list<array{label: string, value: string}>}>|null  $sections
     * @return array<string, mixed>
     */
    private function queryResult(
        string $id,
        string $titulo,
        string $status,
        string $fonte,
        string $sourceUrl,
        array $rows,
        ?string $note = null,
        ?array $sections = null,
        ?array $highlights = null,
    ): array {
        $statusLabel = match ($status) {
            'success' => __('Dados encontrados'),
            'empty' => __('Sem registros'),
            'error' => __('Erro na consulta'),
            'skipped' => __('Não consultado'),
            default => $status,
        };

        $out = [
            'id' => $id,
            'titulo' => $titulo,
            'status' => $status,
            'status_label' => $statusLabel,
            'fonte' => $fonte,
            'source_url' => $sourceUrl,
            'rows' => $rows,
            'note' => $note,
        ];
        if ($sections !== null && $sections !== []) {
            $out['sections'] = $sections;
        }
        if ($highlights !== null && $highlights !== []) {
            $out['highlights'] = $highlights;
        }

        return $out;
    }
}
