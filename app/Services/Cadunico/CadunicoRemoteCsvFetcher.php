<?php

namespace App\Services\Cadunico;

use App\Support\Cadunico\CadunicoStoragePaths;
use Illuminate\Support\Facades\Http;

/**
 * Descarrega CSV Cecad nacional/municipal de URL configurada (sem upload manual).
 */
final class CadunicoRemoteCsvFetcher
{
    /**
     * Garante CSV nacional em storage (descarrega se URL configurada e ficheiro ausente/antigo).
     *
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string}
     */
    public function ensureNationalCsv(int $ano): array
    {
        $path = CadunicoStoragePaths::storageRoot().'/nacional_'.$ano.'.csv';

        if ($this->isFresh($path)) {
            return [
                'ok' => true,
                'path' => $path,
                'downloaded' => false,
                'message' => __('CSV nacional já disponível (:file).', ['file' => basename($path)]),
            ];
        }

        $url = $this->resolveNationalUrl($ano);
        if ($url === null) {
            return [
                'ok' => is_readable($path),
                'path' => is_readable($path) ? $path : null,
                'downloaded' => false,
                'message' => __('URL nacional não configurada (IEDUCAR_CADUNICO_NACIONAL_CSV_URL).'),
            ];
        }

        return $this->downloadTo($url, $path);
    }

    /**
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string}
     */
    public function ensureMunicipalCsv(string $ibge, int $ano): array
    {
        $path = CadunicoStoragePaths::storageRoot().'/'.$ibge.'_'.$ano.'.csv';

        if ($this->isFresh($path)) {
            return [
                'ok' => true,
                'path' => $path,
                'downloaded' => false,
                'message' => __('CSV municipal já disponível.'),
            ];
        }

        $template = trim((string) config('ieducar.cadunico.auto_sync.municipal_csv_url_template', ''));
        if ($template === '' || ! str_contains($template, '{ibge}')) {
            return [
                'ok' => is_readable($path),
                'path' => is_readable($path) ? $path : null,
                'downloaded' => false,
                'message' => __('URL municipal não configurada.'),
            ];
        }

        $url = str_replace(['{ibge}', '{ano}'], [$ibge, (string) $ano], $template);

        return $this->downloadTo($url, $path);
    }

    /**
     * Pesquisa dados.gov.br (CKAN) por recurso CSV e grava como nacional_{ano}.csv.
     *
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string}
     */
    public function tryDiscoverFromDadosGov(int $ano): array
    {
        if (! filter_var(config('ieducar.cadunico.auto_sync.dados_gov_search', false), FILTER_VALIDATE_BOOL)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Pesquisa dados.gov.br desactivada.'),
            ];
        }

        $query = trim((string) config('ieducar.cadunico.auto_sync.dados_gov_query', 'cadastro unico municipio'));
        $base = rtrim((string) config('ieducar.cadunico.open_data.ckan_base_url', 'https://dados.gov.br'), '/');
        $timeout = max(5, (int) config('ieducar.cadunico.open_data.http_timeout', 30));

        try {
            $response = Http::timeout($timeout)->get($base.'/api/3/action/package_search', [
                'q' => $query,
                'rows' => 5,
            ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'path' => null,
                    'downloaded' => false,
                    'message' => __('dados.gov.br: HTTP :status', ['status' => (string) $response->status()]),
                ];
            }

            $results = $response->json('result.results');
            if (! is_array($results)) {
                return ['ok' => false, 'path' => null, 'downloaded' => false, 'message' => __('Sem pacotes CKAN.')];
            }

            foreach ($results as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                $resources = $pkg['resources'] ?? [];
                if (! is_array($resources)) {
                    continue;
                }
                foreach ($resources as $res) {
                    if (! is_array($res)) {
                        continue;
                    }
                    $url = (string) ($res['url'] ?? '');
                    $format = strtolower((string) ($res['format'] ?? ''));
                    if ($url === '' || ! str_starts_with($url, 'http')) {
                        continue;
                    }
                    if ($format !== 'csv' && ! str_ends_with(strtolower($url), '.csv')) {
                        continue;
                    }

                    $dest = CadunicoStoragePaths::storageRoot().'/nacional_'.$ano.'.csv';

                    return $this->downloadTo($url, $dest);
                }
            }

            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Nenhum recurso CSV encontrado no CKAN para: :q', ['q' => $query]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function resolveNationalUrl(int $ano): ?string
    {
        $template = trim((string) config('ieducar.cadunico.auto_sync.nacional_csv_url_template', ''));
        if ($template === '') {
            $legacy = trim((string) config('ieducar.cadunico.auto_sync.nacional_csv_url', ''));
            if ($legacy !== '') {
                return str_replace('{ano}', (string) $ano, $legacy);
            }

            return null;
        }

        return str_replace('{ano}', (string) $ano, $template);
    }

    private function isFresh(string $path): bool
    {
        if (! is_readable($path) || filesize($path) < 32) {
            return false;
        }

        $maxAgeDays = max(1, (int) config('ieducar.cadunico.auto_sync.refresh_csv_days', 30));
        $mtime = filemtime($path);

        return $mtime !== false && $mtime >= time() - ($maxAgeDays * 86400);
    }

    /**
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string}
     */
    private function downloadTo(string $url, string $destPath): array
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('URL inválida.'),
            ];
        }

        $timeout = max(10, (int) config('ieducar.cadunico.open_data.http_timeout', 30));
        $dir = dirname($destPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Não foi possível criar pasta de storage.'),
            ];
        }

        try {
            $response = Http::timeout($timeout)->withOptions(['allow_redirects' => true])->get($url);
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'path' => null,
                    'downloaded' => false,
                    'message' => __('Download falhou HTTP :status', ['status' => (string) $response->status()]),
                ];
            }

            $body = $response->body();
            if (strlen($body) < 32) {
                return [
                    'ok' => false,
                    'path' => null,
                    'downloaded' => false,
                    'message' => __('Resposta vazia ou inválida.'),
                ];
            }

            file_put_contents($destPath, $body);

            return [
                'ok' => true,
                'path' => $destPath,
                'downloaded' => true,
                'message' => __('CSV descarregado: :url', ['url' => $url]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
