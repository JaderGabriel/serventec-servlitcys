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
 */
final class PortalTransparenciaApiClient
{
    public const CADASTRO_URL = 'https://portaldatransparencia.gov.br/api-de-dados/cadastrar-email';

    public const DOCS_URL = 'https://portaldatransparencia.gov.br/api-de-dados';

    public const SWAGGER_URL = 'https://api.portaldatransparencia.gov.br/swagger-ui/index.html';

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
