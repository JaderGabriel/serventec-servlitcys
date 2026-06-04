<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\MunicipalTransferSnapshotRepository;
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
    public function importForCityYear(City $city, int $year): array
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

        foreach ($this->fetchFundebExtratoSources($city, $year, $timeout) as $bundle) {
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
            $exists = MunicipalTransferSnapshot::query()
                ->where('ibge_municipio', $ibge)
                ->where('ano', $histYear)
                ->exists();
            if ($exists) {
                continue;
            }
            $extra = array_merge(
                $this->fetchTesouroRows($city, $ibge, $histYear, $timeout),
                $this->fetchPortalTransparenciaRows($ibge, $histYear, $timeout),
            );
            if ($extra !== []) {
                $allRows = array_merge($allRows, $extra);
            }
        }

        $written = $this->snapshots->upsertBatch($city, $allRows, $importedAt);

        return [
            'success' => $written > 0,
            'message' => $written > 0
                ? __(':n registro(s) de repasse gravados para IBGE :ibge.', ['n' => $written, 'ibge' => $ibge])
                : __('Nenhum repasse identificado nas fontes configuradas para :ano.', ['ano' => $year]),
            'rows' => $written,
            'by_fonte' => $byFonte,
            'attempts' => $attempts,
        ];
    }

    /**
     * Três extratos FUNDEB: publicação Tesouro Transparente, SISWEB (REPASSES) e BB.
     *
     * @return list<array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}>
     */
    private function fetchFundebExtratoSources(City $city, int $year, int $timeout): array
    {
        return [
            $this->tesouroPublicacao->fetchForCityYear($city, $year, $timeout),
            $this->siswebFundeb->fetchForCityYear($city, $year, $timeout),
            $this->bbExtrato->fetchForCityYear($city, $year, $timeout),
        ];
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

        $baseUrl = rtrim((string) ($portal['base_url'] ?? 'https://api.portaldatransparencia.gov.br'), '/');
        $keywords = is_array($portal['education_keywords'] ?? null)
            ? $portal['education_keywords']
            : ['educacao', 'fnde', 'pnae', 'pnate', 'pdde', 'fundeb'];

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['chave-api-dados' => $apiKey])
                ->get($baseUrl.'/api-de-dados/transferencias', [
                    'codigoMunicipio' => $ibge,
                    'pagina' => 1,
                ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $items = $response->json();
        if (! is_array($items)) {
            return [];
        }

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
            $anoItem = (int) preg_replace('/\D/', '', (string) ($item['ano'] ?? $item['exercicio'] ?? $item['data'] ?? ''));
            if ($anoItem >= 2000 && $anoItem !== $year) {
                continue;
            }
            $valor = $item['valor'] ?? $item['valorTransferencia'] ?? $item['valorRecebido'] ?? null;
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
                    'meta' => ['registros' => 0],
                ];
            }
            $aggregated[$programaId]['valor'] += (float) $valor;
            $aggregated[$programaId]['meta']['registros']++;
        }

        return array_values($aggregated);
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
