<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Funding\MunicipalTransferGranularityEnricher;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Facades\Http;

/**
 * Importa repasses observados (Tesouro CKAN/CSV, Portal da Transparência) para municipal_transfer_snapshots.
 */
final class MunicipalTransferImportService
{
    public function __construct(
        private MunicipalTransferSnapshotRepository $snapshots,
        private TesouroTransferenciasCsvService $tesouroCsv,
        private TesouroFundebPublicacaoService $tesouroPublicacao,
        private SiswebFundebRepassesService $siswebFundeb,
        private BbFundebExtratoService $bbExtrato,
        private MunicipalTransferGranularityEnricher $granularityEnricher,
        private PortalTransparenciaApiClient $portalClient = new PortalTransparenciaApiClient,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   message: string,
     *   rows: int,
     *   by_fonte: array<string, int>,
     *   attempts: list<array<string, mixed>>
     * }
     */
    public function importForCityYear(City $city, int $year, bool $financeRealtimeRebuild = false): array
    {
        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [
                'success' => false,
                'message' => __('IBGE do município não configurado.'),
                'rows' => 0,
                'by_fonte' => [],
                'attempts' => [],
            ];
        }

        $cfg = config('ieducar.funding.transfers', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'success' => false,
                'message' => __('Importação de repasses desactivada (IEDUCAR_FUNDING_TRANSFERS_ENABLED).'),
                'rows' => 0,
                'by_fonte' => [],
                'attempts' => [],
            ];
        }

        $timeout = max(5, (int) ($cfg['timeout'] ?? 20));
        $importedAt = now();
        $allRows = [];
        $byFonte = [];
        $attempts = [];

        foreach ($this->fetchFundebExtratoSources($city, $year, $timeout, $financeRealtimeRebuild) as $bundle) {
            $attempts[] = $bundle['attempt'];
            foreach ($bundle['rows'] as $row) {
                $allRows[] = $row;
                $fonte = (string) ($row['fonte'] ?? 'unknown');
                $byFonte[$fonte] = ($byFonte[$fonte] ?? 0) + 1;
            }
        }

        $tesouro = $this->fetchTesouroRows($city, $ibge, $year, $timeout);
        if ($tesouro !== []) {
            $allRows = array_merge($allRows, $tesouro);
            foreach ($tesouro as $row) {
                $fonte = (string) ($row['fonte'] ?? 'tesouro');
                $byFonte[$fonte] = ($byFonte[$fonte] ?? 0) + 1;
            }
        }

        $portal = $this->fetchPortalTransparenciaRows($ibge, $year, $timeout);
        if ($portal !== []) {
            $allRows = array_merge($allRows, $portal);
            $byFonte['portal_transparencia'] = ($byFonte['portal_transparencia'] ?? 0) + count($portal);
        }

        $historical = $this->historicalYears($year, (int) ($cfg['historical_years'] ?? 5));
        foreach ($historical as $histYear) {
            if ($histYear === $year) {
                continue;
            }
            if (! $financeRealtimeRebuild) {
                $exists = MunicipalTransferSnapshot::query()
                    ->where('ibge_municipio', $ibge)
                    ->where('ano', $histYear)
                    ->exists();
                if ($exists) {
                    continue;
                }
            }
            $extra = array_merge(
                $this->fetchTesouroRows($city, $ibge, $histYear, $timeout),
                $this->fetchPortalTransparenciaRows($ibge, $histYear, $timeout),
            );
            if ($extra !== []) {
                $allRows = array_merge($allRows, $extra);
            }
        }

        $allRows = $this->granularityEnricher->enrichRows($allRows, $year);

        $municipalRows = $this->countMunicipalRows($allRows);
        $written = $this->snapshots->upsertBatch($city, $allRows, $importedAt);

        return [
            'success' => $written > 0,
            'municipal_ready' => $municipalRows > 0,
            'municipal_rows' => $municipalRows,
            'message' => $this->buildImportMessage($written, $municipalRows, $city, $ibge, $year),
            'rows' => $written,
            'by_fonte' => $byFonte,
            'attempts' => $attempts,
        ];
    }

    /**
     * Importa apenas programas complementares (PNAE, PNATE, PDDE, educação geral) —
     * Portal da Transparência + Tesouro CKAN/CSV — sem extratos FUNDEB (SISWEB/BB/publicação).
     * Alimenta Finanças → Financiamentos sem reescrever a série Tempo Real.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   rows: int,
     *   by_fonte: array<string, int>,
     *   by_programa: array<string, int>,
     *   skipped_fundeb: int
     * }
     */
    public function importComplementaryForCityYear(City $city, int $year): array
    {
        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [
                'success' => false,
                'message' => __('IBGE do município não configurado.'),
                'rows' => 0,
                'by_fonte' => [],
                'by_programa' => [],
                'skipped_fundeb' => 0,
            ];
        }

        $cfg = config('ieducar.funding.transfers', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'success' => false,
                'message' => __('Importação de repasses desactivada (IEDUCAR_FUNDING_TRANSFERS_ENABLED).'),
                'rows' => 0,
                'by_fonte' => [],
                'by_programa' => [],
                'skipped_fundeb' => 0,
            ];
        }

        $timeout = max(5, (int) ($cfg['timeout'] ?? 20));
        $importedAt = now();
        $allRows = [];
        $byFonte = [];
        $skippedFundeb = 0;

        foreach (array_merge(
            $this->fetchTesouroRows($city, $ibge, $year, $timeout),
            $this->fetchPortalTransparenciaRows($ibge, $year, $timeout),
        ) as $row) {
            $programaId = strtolower((string) ($row['programa_id'] ?? ''));
            if ($programaId === 'fundeb') {
                $skippedFundeb++;

                continue;
            }
            $allRows[] = $row;
            $fonte = (string) ($row['fonte'] ?? 'unknown');
            $byFonte[$fonte] = ($byFonte[$fonte] ?? 0) + 1;
        }

        $allRows = $this->granularityEnricher->enrichRows($allRows, $year);
        $written = $this->snapshots->upsertBatch($city, $allRows, $importedAt);

        $byPrograma = [];
        foreach ($allRows as $row) {
            $pid = (string) ($row['programa_id'] ?? 'geral_educacao');
            $byPrograma[$pid] = ($byPrograma[$pid] ?? 0) + 1;
        }

        if ($written === 0) {
            $message = $skippedFundeb > 0
                ? __('Nenhum programa complementar (além do FUNDEB) encontrado para :ano — :n linha(s) FUNDEB omitida(s) de propósito.', [
                    'ano' => $year,
                    'n' => $skippedFundeb,
                ])
                : __('Nenhum repasse complementar identificado (Portal/Tesouro) para :ano. Confirme PORTAL_TRANSPARENCIA_API_KEY e fontes Tesouro.', [
                    'ano' => $year,
                ]);
        } else {
            $message = __(':n registro(s) complementares gravados para :city (IBGE :ibge) — Finanças → Financiamentos.', [
                'n' => $written,
                'city' => $city->name,
                'ibge' => $ibge,
            ]);
        }

        return [
            'success' => $written > 0,
            'message' => $message,
            'rows' => $written,
            'by_fonte' => $byFonte,
            'by_programa' => $byPrograma,
            'skipped_fundeb' => $skippedFundeb,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function countMunicipalRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if ((string) ($row['fonte'] ?? '') === 'tesouro_publicacao') {
                continue;
            }
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            if (($meta['agregacao'] ?? '') === 'uf') {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function buildImportMessage(int $written, int $municipalRows, City $city, string $ibge, int $year): string
    {
        if ($written === 0) {
            return __('Nenhum repasse identificado nas fontes configuradas para :ano.', ['ano' => $year]);
        }

        if ($municipalRows > 0) {
            return __(':n registro(s) gravados (:m municipais) para :city (IBGE :ibge) — utilizáveis em Finanças → Tempo Real.', [
                'n' => $written,
                'm' => $municipalRows,
                'city' => $city->name,
                'ibge' => $ibge,
            ]);
        }

        return __(':n registro(s) gravados apenas como total da UF (publicação STN) para :city / :ano — não aparecem em Finanças → Tempo Real. Reexecute a importação até gravar a série municipal do Tesouro CKAN (tesouro_csv).', [
            'n' => $written,
            'city' => $city->name,
            'ano' => (string) $year,
        ]);
    }

    /**
     * Três extratos FUNDEB: publicação Tesouro Transparente, SISWEB (REPASSES) e BB.
     *
     * @return list<array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}>
     */
    private function fetchFundebExtratoSources(City $city, int $year, int $timeout, bool $financeRealtimeRebuild = false): array
    {
        $sources = [
            $this->siswebFundeb->fetchForCityYear($city, $year, $timeout),
            $this->bbExtrato->fetchForCityYear($city, $year, $timeout),
        ];

        if (! $financeRealtimeRebuild) {
            array_unshift($sources, $this->tesouroPublicacao->fetchForCityYear($city, $year, $timeout));
        }

        return $sources;
    }

    /**
     * @return list<int>
     */
    private function historicalYears(int $anchorYear, int $count): array
    {
        $count = max(1, min(15, $count));
        $years = [];
        for ($i = 0; $i < $count; $i++) {
            $years[] = $anchorYear - $i;
        }

        return array_values(array_filter($years, static fn (int $y): bool => $y >= 2000));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTesouroRows(City $city, string $ibge, int $year, int $timeout): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [];
        }

        $byProgram = [];

        foreach ($this->tesouroCsv->fetchRowsForCityYear($city, $year, $timeout) as $row) {
            $byProgram[(string) $row['programa_id']] = $row;
        }

        foreach ($this->fetchTesouroDatastoreRows($ibge, $year, $timeout) as $row) {
            $pid = (string) ($row['programa_id'] ?? 'geral_educacao');
            if (! isset($byProgram[$pid])) {
                $byProgram[$pid] = $row;
            }
        }

        return array_values($byProgram);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTesouroDatastoreRows(string $ibge, int $year, int $timeout): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $base = rtrim((string) ($cfg['base_url'] ?? 'https://www.tesourotransparente.gov.br/ckan'), '/');
        $resourceId = trim((string) ($cfg['resource_id'] ?? ''));
        if ($resourceId === '') {
            $resourceId = $this->discoverTesouroDatastoreResourceId($base, (string) ($cfg['package_id'] ?? ''), $timeout);
        }
        if ($resourceId === '') {
            return [];
        }

        try {
            $records = $this->ckanDatastoreSearch($base, $resourceId, null, $timeout, 800, $ibge);
        } catch (\Throwable) {
            return [];
        }

        $keywords = config('ieducar.funding.transfers.program_keywords', []);
        $aggregated = [];

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
            $valor = $this->extractNumericValue($record, ['valor', 'vl_transferencia', 'valor_transferencia', 'valor_repassado', 'total']);
            if ($valor === null || $valor <= 0) {
                continue;
            }
            $programaId = $this->matchProgramId($blob, is_array($keywords) ? $keywords : []);
            if (! isset($aggregated[$programaId])) {
                $aggregated[$programaId] = [
                    'ibge_municipio' => $ibge,
                    'ano' => $year,
                    'fonte' => 'tesouro',
                    'programa_id' => $programaId,
                    'programa_label' => $this->programLabel($programaId),
                    'valor' => 0.0,
                    'meta' => ['registros' => 0],
                ];
            }
            $aggregated[$programaId]['valor'] += $valor;
            $aggregated[$programaId]['meta']['registros']++;
        }

        if (isset($aggregated['geral']) && count($aggregated) > 1) {
            unset($aggregated['geral']);
        }

        return array_values($aggregated);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPortalTransparenciaRows(string $ibge, int $year, int $timeout): array
    {
        $portal = config('ieducar.other_funding.public_queries.portal_transparencia', []);
        if (! (bool) ($portal['enabled'] ?? true)) {
            return [];
        }

        $apiKey = trim((string) ($portal['api_key'] ?? ''));
        if ($apiKey === '') {
            return [];
        }

        $keywords = is_array($portal['education_keywords'] ?? null)
            ? $portal['education_keywords']
            : ['educacao', 'educação', 'fnde', 'pnae', 'pnate', 'pdde', 'fundeb', 'escolar', 'merenda', 'mec'];

        $items = array_merge(
            $this->portalClient->recursosRecebidos($ibge, $year, $apiKey, $timeout),
            $this->normalizeConvenioItems(
                $this->portalClient->convenios($ibge, $apiKey, $timeout, $year),
            ),
        );

        $programKeywords = config('ieducar.funding.transfers.program_keywords', []);
        $aggregated = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
            $matchKw = false;
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($blob, strtolower($kw))) {
                    $matchKw = true;
                    break;
                }
            }
            if (! $matchKw) {
                continue;
            }
            $anoItem = PortalTransparenciaApiClient::yearFromAnoMes($item['anoMes'] ?? null)
                ?? (int) preg_replace('/\D/', '', (string) ($item['ano'] ?? $item['exercicio'] ?? $item['data'] ?? ''));
            if ($anoItem >= 2000 && $anoItem !== $year) {
                continue;
            }
            $valor = $item['valor'] ?? $item['valorLiberado'] ?? $item['valorTransferencia'] ?? $item['valorRecebido'] ?? null;
            if (! is_numeric($valor) || (float) $valor <= 0) {
                continue;
            }
            $programaId = $this->matchProgramId($blob, is_array($programKeywords) ? $programKeywords : []);
            if (! isset($aggregated[$programaId])) {
                $aggregated[$programaId] = [
                    'ibge_municipio' => $ibge,
                    'ano' => $year,
                    'fonte' => 'portal_transparencia',
                    'programa_id' => $programaId,
                    'programa_label' => $this->programLabel($programaId),
                    'valor' => 0.0,
                    'meta' => ['registros' => 0, 'repasses' => []],
                ];
            }
            $parsedDate = $this->parsePortalTransferDate($item, $year);
            $label = trim((string) (
                $item['nomeOrgao']
                ?? $item['nomeUG']
                ?? $item['descricao']
                ?? $item['objeto']
                ?? $item['nomePrograma']
                ?? $item['acao']
                ?? 'Transferência'
            ));
            $repasse = [
                'valor' => round((float) $valor, 2),
                'granularity' => $parsedDate['granularity'],
                'label' => mb_substr($label !== '' ? $label : 'Transferência', 0, 120),
            ];
            if ($parsedDate['data'] !== null) {
                $repasse['data'] = $parsedDate['data'];
            }
            if ($parsedDate['mes'] !== null) {
                $repasse['mes'] = $parsedDate['mes'];
            }
            if ($parsedDate['ano'] !== null) {
                $repasse['ano'] = $parsedDate['ano'];
            }
            $aggregated[$programaId]['meta']['repasses'][] = $repasse;
            $aggregated[$programaId]['valor'] += (float) $valor;
            $aggregated[$programaId]['meta']['registros']++;
        }

        return array_values($aggregated);
    }

    /**
     * Achata DTOs de convénio para o mesmo pipeline de palavras-chave / valores.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeConvenioItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $dim = is_array($item['dimConvenio'] ?? null) ? $item['dimConvenio'] : [];
            $orgao = is_array($item['orgao'] ?? null) ? $item['orgao'] : [];
            $subfuncao = is_array($item['subfuncao'] ?? null) ? $item['subfuncao'] : [];
            $out[] = [
                'valor' => $item['valorLiberado'] ?? $item['valor'] ?? null,
                'valorLiberado' => $item['valorLiberado'] ?? null,
                'ano' => $this->extractYearFromRecord([
                    'data' => $item['dataUltimaLiberacao'] ?? $item['dataReferencia'] ?? $item['dataInicioVigencia'] ?? null,
                ]),
                'data' => $item['dataUltimaLiberacao'] ?? $item['dataReferencia'] ?? null,
                'objeto' => (string) ($dim['objeto'] ?? ''),
                'descricao' => (string) ($dim['objeto'] ?? $dim['numero'] ?? ''),
                'nomeOrgao' => (string) ($orgao['nome'] ?? $orgao['nomeMaximo'] ?? $orgao['descricao'] ?? ''),
                'subfuncao' => (string) ($subfuncao['descricao'] ?? $subfuncao['nome'] ?? ''),
                'fonte_registro' => 'convenio',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{data: ?string, mes: ?int, ano: ?int, granularity: string}
     */
    private function parsePortalTransferDate(array $item, int $defaultYear): array
    {
        $mesFromAnoMes = PortalTransparenciaApiClient::monthFromAnoMes($item['anoMes'] ?? null);
        $anoFromAnoMes = PortalTransparenciaApiClient::yearFromAnoMes($item['anoMes'] ?? null);
        if ($mesFromAnoMes !== null) {
            return [
                'data' => null,
                'mes' => $mesFromAnoMes,
                'ano' => $anoFromAnoMes ?? $defaultYear,
                'granularity' => 'month',
            ];
        }

        foreach (['data', 'dataTransferencia', 'dataRepasse', 'dataPagamento', 'dataUltimaLiberacao', 'dataReferencia'] as $key) {
            $raw = trim((string) ($item[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
                return [
                    'data' => sprintf('%02d/%02d/%04d', (int) $m[1], (int) $m[2], (int) $m[3]),
                    'mes' => (int) $m[2],
                    'ano' => (int) $m[3],
                    'granularity' => 'day',
                ];
            }
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
                return [
                    'data' => sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]),
                    'mes' => (int) $m[2],
                    'ano' => (int) $m[1],
                    'granularity' => 'day',
                ];
            }
        }

        $mes = isset($item['mes']) && is_numeric($item['mes']) ? (int) $item['mes'] : null;
        $ano = isset($item['ano']) && is_numeric($item['ano']) ? (int) $item['ano'] : $defaultYear;
        if ($mes !== null && $mes >= 1 && $mes <= 12) {
            return [
                'data' => null,
                'mes' => $mes,
                'ano' => $ano,
                'granularity' => 'month',
            ];
        }

        return ['data' => null, 'mes' => null, 'ano' => null, 'granularity' => 'month'];
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

    private function discoverTesouroDatastoreResourceId(string $base, string $packageId, int $timeout): string
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
                if (is_array($res) && ($res['datastore_active'] ?? false) === true && filled($res['id'] ?? null)) {
                    return (string) $res['id'];
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
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
}
