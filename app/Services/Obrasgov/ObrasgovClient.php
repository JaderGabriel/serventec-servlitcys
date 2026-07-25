<?php

namespace App\Services\Obrasgov;

use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para API pública Obrasgov.br (nova base).
 */
final class ObrasgovClient
{
    private readonly string $baseUrl;

    private readonly int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('horizonte.obras.base_url', 'https://api-publica.obrasgov.gestao.gov.br/obras'), '/');
        $this->timeout = max(15, (int) config('horizonte.obras.http_timeout', 45));
    }

    /**
     * Lista projetos de investimento com filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, total_pages: int, total_items: int, page_number: int}|null
     */
    public function getProjetos(array $filters, int $page = 1): ?array
    {
        $url = $this->baseUrl.'/projeto-investimento';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            Log::error('obrasgov.client.unsafe_url', ['url' => $url]);

            return null;
        }

        $params = $this->normalizeParams($filters, $page);

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                Log::warning('obrasgov.client.failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'params' => $params,
                ]);

                return null;
            }

            $body = $response->json();
            if (! is_array($body)) {
                return null;
            }

            return $this->parsePaginatedResponse($body, $page);
        } catch (\Throwable $e) {
            Log::error('obrasgov.client.exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Lista geometrias (município, IBGE) com filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, total_pages: int, total_items: int, page_number: int}|null
     */
    public function getGeometrias(array $filters, int $page = 1): ?array
    {
        $url = $this->baseUrl.'/geometria';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            Log::error('obrasgov.client.unsafe_url', ['url' => $url]);

            return null;
        }

        $params = $this->normalizeParams($filters, $page);

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                Log::warning('obrasgov.client.failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();
            if (! is_array($body)) {
                return null;
            }

            return $this->parsePaginatedResponse($body, $page);
        } catch (\Throwable $e) {
            Log::error('obrasgov.client.exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Percentual de execução física de um projeto.
     *
     * @return array<string, mixed>|null
     */
    public function getExecucaoFisica(string $idProjeto): ?array
    {
        $url = $this->baseUrl.'/execucao-fisica';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, ['id_projeto_investimento' => $idProjeto]);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();
            if (! is_array($body)) {
                return null;
            }

            $items = is_array($body['data'] ?? null) ? $body['data'] : $body;

            return is_array($items[0] ?? null) ? $items[0] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Empenhos de um projeto.
     *
     * @return list<array<string, mixed>>
     */
    public function getEmpenhos(string $idProjeto): array
    {
        $url = $this->baseUrl.'/empenho';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, ['id_projeto_investimento' => $idProjeto]);

            if (! $response->successful()) {
                return [];
            }

            $body = $response->json();
            if (! is_array($body)) {
                return [];
            }

            $items = is_array($body['data'] ?? null) ? $body['data'] : $body;

            return is_array($items) ? array_filter($items, 'is_array') : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Histórico de paralisação/cancelamento de um projeto.
     *
     * @return list<array<string, mixed>>
     */
    public function getHistoricoParalisacao(string $idProjeto): array
    {
        $url = $this->baseUrl.'/historico-situacao-cancelada-paralisada';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url, ['id_projeto_investimento' => $idProjeto]);

            if (! $response->successful()) {
                return [];
            }

            $body = $response->json();
            if (! is_array($body)) {
                return [];
            }

            $items = is_array($body['data'] ?? null) ? $body['data'] : $body;

            return is_array($items) ? array_filter($items, 'is_array') : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Data da última atualização da base Obrasgov.
     *
     * @return array<string, mixed>|null
     */
    public function getDataAtualizacao(): ?array
    {
        $url = $this->baseUrl.'/data-atualizacao';
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();

            return is_array($body) ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeParams(array $filters, int $page): array
    {
        $params = $filters;
        $params['pagina'] = max(1, $page);
        $params['tamanho_da_pagina'] = max(10, min(100, (int) config('horizonte.obras.page_size', 50)));

        return $params;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{data: list<array<string, mixed>>, total_pages: int, total_items: int, page_number: int}
     */
    private function parsePaginatedResponse(array $body, int $page): array
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $totalItems = max(0, (int) ($body['total_de_itens'] ?? $body['total'] ?? 0));
        $pageSize = max(1, (int) ($body['tamanho_da_pagina'] ?? config('horizonte.obras.page_size', 50)));
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $pageSize) : 1;

        return [
            'data' => array_filter($data, 'is_array'),
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'page_number' => $page,
        ];
    }
}
