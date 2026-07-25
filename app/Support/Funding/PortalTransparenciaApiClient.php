<?php

namespace App\Support\Funding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP da API REST do Portal da Transparência (CGU).
 *
 * Autenticação: header `chave-api-dados` (env PORTAL_TRANSPARENCIA_API_KEY).
 * Cadastro: https://portaldatransparencia.gov.br/api-de-dados/cadastrar-email
 *
 * Endpoints usados (Swagger 2026):
 * - GET /api-de-dados/despesas/recursos-recebidos
 *   Preferir `codigoFavorecido` = CNPJ da prefeitura (IBGE sozinho devolve vazio para o ente).
 * - GET /api-de-dados/convenios (`codigoIBGE`, `funcao=12`; ano via última liberação)
 * - GET /api-de-dados/emendas (`ano`, `codigoFuncao=12`; município via `localidadeDoGasto`, sem IBGE)
 * - GET /api-de-dados/emendas/documentos/{codigo}
 * - GET /api-de-dados/contratos (`codigoOrgao` SIAFI obrigatório + datas)
 * - GET /api-de-dados/contratos/cpf-cnpj (`cpfCnpj` fornecedor)
 * - GET /api-de-dados/contratos/itens-contratados (`id` do contrato)
 * - GET /api-de-dados/licitacoes (`codigoOrgao` + datas; período máx. 1 mês)
 * - GET /api-de-dados/ceis (`codigoSancionado` CNPJ/CPF)
 * - GET /api-de-dados/cnep (`codigoSancionado` CNPJ/CPF)
 * - GET /api-de-dados/cepim (`cnpjSancionado`)
 */
final class PortalTransparenciaApiClient
{
    public const CADASTRO_URL = 'https://portaldatransparencia.gov.br/api-de-dados/cadastrar-email';

    public const DOCS_URL = 'https://portaldatransparencia.gov.br/api-de-dados';

    public const SWAGGER_URL = 'https://api.portaldatransparencia.gov.br/swagger-ui/index.html';

    /** Função orçamental Educação (SIAFI / Portal). */
    public const FUNCAO_EDUCACAO = '12';

    /**
     * Recursos recebidos no exercício.
     *
     * Com `codigoFavorecido` (CNPJ do município) o endpoint devolve transferências ao ente.
     * Com apenas `codigoIBGE` a API tipicamente devolve lista vazia para a prefeitura.
     *
     * @return list<array<string, mixed>>
     */
    public function recursosRecebidos(
        string $ibge,
        int $year,
        string $apiKey,
        int $timeout = 20,
        int $maxPages = 5,
        ?string $codigoFavorecido = null,
    ): array {
        $ibge = trim($ibge);
        $apiKey = trim($apiKey);
        $favorecido = preg_replace('/\D/', '', (string) $codigoFavorecido) ?: '';
        if ($apiKey === '' || $year < 2000) {
            return [];
        }
        if ($favorecido === '' && $ibge === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(20, $maxPages));

        for ($page = 1; $page <= $maxPages; $page++) {
            $query = [
                'mesAnoInicio' => sprintf('01/%04d', $year),
                'mesAnoFim' => sprintf('12/%04d', $year),
                'pagina' => $page,
            ];
            if ($favorecido !== '') {
                $query['codigoFavorecido'] = $favorecido;
            } else {
                $query['codigoIBGE'] = $ibge;
            }

            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.'/api-de-dados/despesas/recursos-recebidos', $query);
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.recursos_failed', [
                    'ibge' => $ibge,
                    'year' => $year,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.recursos_http', [
                    'ibge' => $ibge,
                    'year' => $year,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                }
            }

            if (count($items) < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Resolve CNPJ da prefeitura e consulta recursos recebidos (fallback IBGE se CNPJ falhar).
     *
     * @return list<array<string, mixed>>
     */
    public function recursosRecebidosParaMunicipio(
        string $ibge,
        int $year,
        string $apiKey,
        int $timeout = 20,
        int $maxPages = 5,
    ): array {
        $cnpj = $this->resolveMunicipioCnpj($ibge, $apiKey, $timeout);
        if ($cnpj !== null) {
            $rows = $this->recursosRecebidos($ibge, $year, $apiKey, $timeout, $maxPages, $cnpj);
            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->recursosRecebidos($ibge, $year, $apiKey, $timeout, $maxPages, null);
    }

    /**
     * CNPJ do ente municipal (cache 30 dias), a partir dos convênios do Portal.
     */
    public function resolveMunicipioCnpj(string $ibge, string $apiKey, int $timeout = 20): ?string
    {
        $ibge = trim($ibge);
        $apiKey = trim($apiKey);
        if ($ibge === '' || $apiKey === '') {
            return null;
        }

        $cacheKey = 'portal_transparencia:municipio_cnpj:'.$ibge;

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && preg_match('/^\d{14}$/', $cached) === 1) {
            return $cached;
        }

        $resolved = $this->discoverMunicipioCnpjFromConvenios($ibge, $apiKey, $timeout);
        if ($resolved !== null) {
            Cache::put($cacheKey, $resolved, now()->addDays(30));
        }

        return $resolved;
    }

    /**
     * Convénios do Poder Executivo Federal no município.
     *
     * Filtro de ano usa data da **última liberação** (não início de vigência): convênios
     * antigos com liberação no exercício pediam lista vazia com dataInicial/dataFinal.
     *
     * @return list<array<string, mixed>>
     */
    public function convenios(
        string $ibge,
        string $apiKey,
        int $timeout = 20,
        ?int $year = null,
        int $maxPages = 3,
        ?string $funcao = '12',
    ): array {
        $ibge = trim($ibge);
        $apiKey = trim($apiKey);
        if ($ibge === '' || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(20, $maxPages));

        $queryBase = [
            'codigoIBGE' => $ibge,
        ];
        if ($funcao !== null && $funcao !== '') {
            $queryBase['funcao'] = $funcao;
        }
        if ($year !== null && $year >= 2000) {
            $queryBase['dataUltimaLiberacaoInicial'] = sprintf('01/01/%04d', $year);
            $queryBase['dataUltimaLiberacaoFinal'] = sprintf('31/12/%04d', $year);
        }

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.'/api-de-dados/convenios', array_merge($queryBase, [
                        'pagina' => $page,
                    ]));
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.convenios_failed', [
                    'ibge' => $ibge,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.convenios_http', [
                    'ibge' => $ibge,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                }
            }

            if (count($items) < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Emendas parlamentares (lista nacional por ano/função — sem filtro IBGE na API).
     *
     * Município: cruzar `localidadeDoGasto` (ex.: «CAMPESTRE - MG») com nome/UF do ente.
     * Valores vêm como string BR («60.000,00») — usar {@see parseValorBrl()}.
     *
     * @return list<array<string, mixed>>
     */
    public function emendas(
        int $year,
        string $apiKey,
        int $timeout = 20,
        int $maxPages = 5,
        string $codigoFuncao = self::FUNCAO_EDUCACAO,
        ?string $codigoSubfuncao = null,
        ?string $nomeAutor = null,
        ?string $tipoEmenda = null,
    ): array {
        $apiKey = trim($apiKey);
        if ($apiKey === '' || $year < 2000) {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(100, $maxPages));

        $queryBase = [
            'ano' => $year,
        ];
        if ($codigoFuncao !== '') {
            $queryBase['codigoFuncao'] = $codigoFuncao;
        }
        if ($codigoSubfuncao !== null && $codigoSubfuncao !== '') {
            $queryBase['codigoSubfuncao'] = $codigoSubfuncao;
        }
        if ($nomeAutor !== null && trim($nomeAutor) !== '') {
            $queryBase['nomeAutor'] = trim($nomeAutor);
        }
        if ($tipoEmenda !== null && trim($tipoEmenda) !== '') {
            $queryBase['tipoEmenda'] = trim($tipoEmenda);
        }

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.'/api-de-dados/emendas', array_merge($queryBase, [
                        'pagina' => $page,
                    ]));
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.emendas_failed', [
                    'year' => $year,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.emendas_http', [
                    'year' => $year,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                }
            }

            if (count($items) < 15) {
                break;
            }
        }

        return $out;
    }

    /**
     * Emendas do ano/função cuja `localidadeDoGasto` corresponde ao município (nome + UF).
     *
     * A API não filtra por IBGE — percorre páginas até `maxPages`. Para cobertura
     * nacional completa use um job com `maxPages` alto e cache (fase A2).
     *
     * @return list<array<string, mixed>>
     */
    public function emendasParaMunicipio(
        string $municipioNome,
        string $uf,
        int $year,
        string $apiKey,
        int $timeout = 20,
        int $maxPages = 20,
        string $codigoFuncao = self::FUNCAO_EDUCACAO,
    ): array {
        $rows = $this->emendas($year, $apiKey, $timeout, $maxPages, $codigoFuncao);
        $matched = [];
        foreach ($rows as $row) {
            $localidade = (string) ($row['localidadeDoGasto'] ?? '');
            if (self::localidadeMatchesMunicipio($localidade, $municipioNome, $uf)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * Documentos orçamentais ligados a uma emenda (`codigoEmenda`).
     *
     * @return list<array<string, mixed>>
     */
    public function emendasDocumentos(
        string $codigoEmenda,
        string $apiKey,
        int $timeout = 20,
        int $maxPages = 5,
    ): array {
        $codigoEmenda = trim($codigoEmenda);
        $apiKey = trim($apiKey);
        if ($codigoEmenda === '' || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(20, $maxPages));
        $path = $baseUrl.'/api-de-dados/emendas/documentos/'.rawurlencode($codigoEmenda);

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($path, ['pagina' => $page]);
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.emendas_documentos_failed', [
                    'codigo' => $codigoEmenda,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.emendas_documentos_http', [
                    'codigo' => $codigoEmenda,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                }
            }

            if (count($items) < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Contratos do Poder Executivo Federal por órgão SIAFI (`codigoOrgao` obrigatório).
     *
     * @return list<array<string, mixed>>
     */
    public function contratos(
        string $codigoOrgao,
        string $apiKey,
        string $dataInicial,
        string $dataFinal,
        int $timeout = 25,
        int $maxPages = 5,
    ): array {
        return $this->paginatedOrgaoList(
            '/api-de-dados/contratos',
            'contratos',
            $codigoOrgao,
            $apiKey,
            $dataInicial,
            $dataFinal,
            $timeout,
            $maxPages,
        );
    }

    /**
     * Contratos em que o CPF/CNPJ figura como fornecedor (`/contratos/cpf-cnpj`).
     *
     * @return list<array<string, mixed>>
     */
    public function contratosPorCnpj(
        string $cpfCnpj,
        string $apiKey,
        int $timeout = 25,
        int $maxPages = 5,
    ): array {
        $cpfCnpj = preg_replace('/\D/', '', $cpfCnpj) ?: '';
        $apiKey = trim($apiKey);
        if ($cpfCnpj === '' || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(30, $maxPages));

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.'/api-de-dados/contratos/cpf-cnpj', [
                        'cpfCnpj' => $cpfCnpj,
                        'pagina' => $page,
                    ]);
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.contratos_cnpj_failed', [
                    'cnpj' => $cpfCnpj,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.contratos_cnpj_http', [
                    'cnpj' => $cpfCnpj,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }
            if (isset($items['Erro na API']) || isset($items['message'])) {
                break;
            }

            $pageCount = 0;
            foreach ($items as $item) {
                if (is_array($item) && ! isset($item['Erro na API'])) {
                    $out[] = $item;
                    $pageCount++;
                }
            }

            if ($pageCount < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Itens contratados de um contrato (`id` do ContratoDTO).
     *
     * @return list<array<string, mixed>>
     */
    public function itensContratados(
        int|string $contratoId,
        string $apiKey,
        int $timeout = 25,
        int $maxPages = 3,
    ): array {
        $contratoId = trim((string) $contratoId);
        $apiKey = trim($apiKey);
        if ($contratoId === '' || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(10, $maxPages));

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.'/api-de-dados/contratos/itens-contratados', [
                        'id' => $contratoId,
                        'pagina' => $page,
                    ]);
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.itens_contratados_failed', [
                    'id' => $contratoId,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }
            if (isset($items['Erro na API']) || isset($items['message'])) {
                break;
            }

            $pageCount = 0;
            foreach ($items as $item) {
                if (is_array($item) && ! isset($item['Erro na API'])) {
                    $out[] = $item;
                    $pageCount++;
                }
            }

            if ($pageCount < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Licitações por órgão SIAFI. A API limita o período a **1 mês** — use
     * {@see licitacoesAno()} para varrer o exercício mês a mês.
     *
     * @return list<array<string, mixed>>
     */
    public function licitacoes(
        string $codigoOrgao,
        string $apiKey,
        string $dataInicial,
        string $dataFinal,
        int $timeout = 25,
        int $maxPages = 5,
    ): array {
        return $this->paginatedOrgaoList(
            '/api-de-dados/licitacoes',
            'licitacoes',
            $codigoOrgao,
            $apiKey,
            $dataInicial,
            $dataFinal,
            $timeout,
            $maxPages,
        );
    }

    /**
     * Varre até `$maxMonths` meses do ano (a partir de janeiro) para licitações.
     *
     * @return list<array<string, mixed>>
     */
    public function licitacoesAno(
        string $codigoOrgao,
        int $year,
        string $apiKey,
        int $timeout = 25,
        int $maxPagesPerMonth = 3,
        int $maxMonths = 12,
    ): array {
        $out = [];
        $maxMonths = max(1, min(12, $maxMonths));
        for ($month = 1; $month <= $maxMonths; $month++) {
            $start = sprintf('01/%02d/%04d', $month, $year);
            $endDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
            $end = sprintf('%02d/%02d/%04d', $endDay, $month, $year);
            foreach ($this->licitacoes($codigoOrgao, $apiKey, $start, $end, $timeout, $maxPagesPerMonth) as $item) {
                $out[] = $item;
            }
            usleep(80_000);
        }

        return $out;
    }

    /**
     * Cadastro Nacional de Empresas Inidôneas e Suspensas (CEIS).
     *
     * @return list<array<string, mixed>>
     */
    public function ceis(string $codigoSancionado, string $apiKey, int $timeout = 25, int $maxPages = 3): array
    {
        return $this->paginatedSancaoList(
            '/api-de-dados/ceis',
            'ceis',
            'codigoSancionado',
            $codigoSancionado,
            $apiKey,
            $timeout,
            $maxPages,
        );
    }

    /**
     * Cadastro Nacional de Empresas Punidas (CNEP).
     *
     * @return list<array<string, mixed>>
     */
    public function cnep(string $codigoSancionado, string $apiKey, int $timeout = 25, int $maxPages = 3): array
    {
        return $this->paginatedSancaoList(
            '/api-de-dados/cnep',
            'cnep',
            'codigoSancionado',
            $codigoSancionado,
            $apiKey,
            $timeout,
            $maxPages,
        );
    }

    /**
     * Entidades Privadas sem Fins Lucrativos Impedidas (CEPIM).
     *
     * @return list<array<string, mixed>>
     */
    public function cepim(string $cnpjSancionado, string $apiKey, int $timeout = 25, int $maxPages = 3): array
    {
        return $this->paginatedSancaoList(
            '/api-de-dados/cepim',
            'cepim',
            'cnpjSancionado',
            $cnpjSancionado,
            $apiKey,
            $timeout,
            $maxPages,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paginatedSancaoList(
        string $path,
        string $logKey,
        string $cnpjParam,
        string $cnpj,
        string $apiKey,
        int $timeout,
        int $maxPages,
    ): array {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?: '';
        $apiKey = trim($apiKey);
        if (strlen($cnpj) !== 14 || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(10, $maxPages));

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.$path, [
                        $cnpjParam => $cnpj,
                        'pagina' => $page,
                    ]);
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.'.$logKey.'_failed', [
                    'cnpj' => $cnpj,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.'.$logKey.'_http', [
                    'cnpj' => $cnpj,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }
            if (isset($items['Erro na API']) || isset($items['message'])) {
                break;
            }

            $pageCount = 0;
            foreach ($items as $item) {
                if (is_array($item) && ! isset($item['Erro na API'])) {
                    $out[] = $item;
                    $pageCount++;
                }
            }

            if ($pageCount < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paginatedOrgaoList(
        string $path,
        string $logKey,
        string $codigoOrgao,
        string $apiKey,
        string $dataInicial,
        string $dataFinal,
        int $timeout,
        int $maxPages,
    ): array {
        $codigoOrgao = preg_replace('/\D/', '', $codigoOrgao) ?: '';
        $apiKey = trim($apiKey);
        if ($codigoOrgao === '' || $apiKey === '') {
            return [];
        }

        $baseUrl = $this->baseUrl();
        $headers = $this->headers($apiKey);
        $out = [];
        $maxPages = max(1, min(30, $maxPages));
        $queryBase = [
            'codigoOrgao' => $codigoOrgao,
            'dataInicial' => $dataInicial,
            'dataFinal' => $dataFinal,
        ];

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($baseUrl.$path, array_merge($queryBase, ['pagina' => $page]));
            } catch (\Throwable $e) {
                Log::debug('portal_transparencia.'.$logKey.'_failed', [
                    'orgao' => $codigoOrgao,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::debug('portal_transparencia.'.$logKey.'_http', [
                    'orgao' => $codigoOrgao,
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);

                break;
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            if (isset($items['Erro na API']) || isset($items['message'])) {
                break;
            }

            $pageCount = 0;
            foreach ($items as $item) {
                if (is_array($item) && ! isset($item['Erro na API'])) {
                    $out[] = $item;
                    $pageCount++;
                }
            }

            if ($pageCount < 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * Converte valor monetário do Portal («60.000,00» ou «60000.00») para float.
     */
    public static function parseValorBrl(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        // BR: 1.234.567,89
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $str) === 1
            || (str_contains($str, ',') && str_contains($str, '.'))) {
            $normalized = str_replace('.', '', $str);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($str, ',')) {
            $normalized = str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $str) ?? '');
        } else {
            $normalized = preg_replace('/[^\d.-]/', '', $str) ?? '';
        }

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    /**
     * Cruza `localidadeDoGasto` com município + UF.
     *
     * Aceita «NOME - UF». Rejeita localidades só de UF («MINAS GERAIS (UF)»)
     * quando se pede um município concreto.
     */
    public static function localidadeMatchesMunicipio(string $localidade, string $municipioNome, string $uf): bool
    {
        $localidade = trim($localidade);
        $municipioNome = trim($municipioNome);
        $uf = strtoupper(trim($uf));
        if ($localidade === '' || $municipioNome === '' || strlen($uf) !== 2) {
            return false;
        }

        $locNorm = self::normalizeLocalidadeToken($localidade);
        $nomeNorm = self::normalizeLocalidadeToken($municipioNome);

        if ($locNorm === '' || $nomeNorm === '') {
            return false;
        }

        // Só UF / estado — não é o município.
        if (preg_match('/\([Uu][Ff]\)\s*$/u', $localidade) === 1) {
            return false;
        }

        $ufInLoc = null;
        if (preg_match('/\s*-\s*([A-Za-z]{2})\s*$/u', $localidade, $m) === 1) {
            $ufInLoc = strtoupper($m[1]);
        }
        if ($ufInLoc !== null && $ufInLoc !== $uf) {
            return false;
        }

        // Preferir «NOME - UF»
        $expected = $nomeNorm.' '.$uf;
        $locCompact = preg_replace('/\s*-\s*/', ' ', $locNorm) ?? $locNorm;
        if ($locCompact === $expected || str_starts_with($locCompact, $nomeNorm.' '.$uf)) {
            return true;
        }

        // Nome exacto no início + UF presente ou implícita
        if ($ufInLoc === $uf && ($locNorm === $nomeNorm || str_starts_with($locNorm, $nomeNorm.' ') || str_starts_with($locNorm, $nomeNorm.'-'))) {
            return true;
        }

        return false;
    }

    private static function normalizeLocalidadeToken(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'Ê' => 'E', 'È' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Extrai ano civil de `anoMes` (ex.: 202506, "2025/06", "06/2025").
     */
    public static function yearFromAnoMes(mixed $anoMes): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $anoMes) ?? '';
        if (strlen($digits) >= 6) {
            $y = (int) substr($digits, 0, 4);
            if ($y >= 2000 && $y <= 2100) {
                return $y;
            }
        }
        if (strlen($digits) === 4) {
            $y = (int) $digits;
            if ($y >= 2000 && $y <= 2100) {
                return $y;
            }
        }

        return null;
    }

    /**
     * Extrai mês (1–12) de `anoMes`.
     */
    public static function monthFromAnoMes(mixed $anoMes): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $anoMes) ?? '';
        if (strlen($digits) >= 6) {
            $m = (int) substr($digits, 4, 2);
            if ($m >= 1 && $m <= 12) {
                return $m;
            }
        }

        return null;
    }

    /**
     * @return array{chave-api-dados: string}
     */
    public function headers(string $apiKey): array
    {
        return ['chave-api-dados' => trim($apiKey)];
    }

    public function baseUrl(): string
    {
        $portal = config('ieducar.other_funding.public_queries.portal_transparencia', []);

        return rtrim((string) ($portal['base_url'] ?? 'https://api.portaldatransparencia.gov.br'), '/');
    }

    private function discoverMunicipioCnpjFromConvenios(string $ibge, string $apiKey, int $timeout): ?string
    {
        $items = $this->convenios($ibge, $apiKey, $timeout, year: null, maxPages: 2, funcao: null);
        $fallback = null;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $conv = is_array($item['convenente'] ?? null) ? $item['convenente'] : [];
            $cnpj = preg_replace('/\D/', '', (string) ($conv['cnpjFormatado'] ?? $conv['cnpj'] ?? '')) ?: '';
            if (strlen($cnpj) !== 14) {
                continue;
            }
            $nome = mb_strtoupper((string) ($conv['nome'] ?? $conv['razaoSocialReceita'] ?? $conv['nomeFantasiaReceita'] ?? ''));
            if (
                str_contains($nome, 'MUNICIPIO')
                || str_contains($nome, 'MUNICÍPIO')
                || str_contains($nome, 'PREFEITURA')
            ) {
                return $cnpj;
            }
            $fallback ??= $cnpj;
        }

        return $fallback;
    }
}
