<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Cliente HTTP para a API SICONFI (Tesouro / apidatalake). */
final class SiconfiApiClient
{
    /**
     * @param  array<string, scalar|null>  $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $endpoint, array $params, ?int $timeout = null): array
    {
        $baseUrl = rtrim((string) config('horizonte.siconfi.base_url', 'https://apidatalake.tesouro.gov.br/ords/siconfi/tt'), '/');
        $timeout = $timeout ?? max(15, (int) config('horizonte.siconfi.http_timeout', 45));
        $limit = max(500, min(5000, (int) config('horizonte.siconfi.page_limit', 5000)));
        $items = [];
        $offset = 0;

        do {
            $query = array_merge($params, ['limit' => $limit, 'offset' => $offset]);
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->get($baseUrl.$endpoint, $query);
            } catch (\Throwable $e) {
                Log::warning('horizonte.siconfi_http_error', [
                    'endpoint' => $endpoint,
                    'offset' => $offset,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                Log::warning('horizonte.siconfi_http_status', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'offset' => $offset,
                ]);

                break;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                break;
            }

            $page = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            foreach ($page as $row) {
                if (is_array($row)) {
                    $items[] = $row;
                }
            }

            $hasMore = (bool) ($payload['hasMore'] ?? false);
            $offset += $limit;
        } while ($hasMore && $page !== []);

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRreo(int $ibge, int $year, int $period, string $annex): array
    {
        return $this->fetchAll('/rreo', [
            'an_exercicio' => $year,
            'nr_periodo' => $period,
            'co_tipo_demonstrativo' => 'RREO',
            'no_anexo' => $annex,
            'co_esfera' => 'M',
            'id_ente' => $ibge,
        ]);
    }
}
