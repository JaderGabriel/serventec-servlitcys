<?php

namespace App\Services\Inep;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Descarrega o ZIP de microdados SAEB do INEP e extrai para cache local.
 */
final class SaebMicrodadosInepDownloader
{
    public function zipUrlForYear(int $year): string
    {
        $template = (string) config(
            'ieducar.saeb.microdados_inep_zip_url_template',
            'https://download.inep.gov.br/microdados/microdados_saeb_{year}.zip'
        );

        return str_replace('{year}', (string) $year, $template);
    }

    /**
     * URL aponta para ZIP de microdados (INEP ou outro) em vez de CSV.
     */
    public static function isZipUrl(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path !== '' && str_ends_with(strtolower($path), '.zip')) {
            return true;
        }
        $l = strtolower($url);

        return str_contains($l, 'microdados_saeb_') || str_contains($l, 'microdados_saeb');
    }

    /**
     * Extrai o ano do padrão microdados_saeb_YYYY do INEP, se existir.
     */
    public static function yearFromZipUrl(string $url): ?int
    {
        if (preg_match('/microdados_saeb_(\d{4})/i', $url, $m) !== 1) {
            return null;
        }

        return max(2000, min(2100, (int) $m[1]));
    }

    /**
     * Opções Guzzle para verificação SSL (CA do sistema ou ficheiro explícito).
     *
     * @return array{verify: bool|string}
     */
    private function guzzleVerifyOptions(): array
    {
        $verify = filter_var(config('ieducar.saeb.microdados_http_verify', true), FILTER_VALIDATE_BOOL);
        $ca = trim((string) config('ieducar.saeb.microdados_http_ca_bundle', ''));

        if ($ca !== '' && is_readable($ca)) {
            return ['verify' => $ca];
        }

        return ['verify' => $verify];
    }

    private function pendingRequest(int $timeout, string $userAgent): PendingRequest
    {
        return Http::timeout($timeout)
            ->withHeaders(['User-Agent' => $userAgent])
            ->withOptions($this->guzzleVerifyOptions());
    }

    /**
     * Descarrega e extrai o ZIP; devolve o caminho absoluto do directório de extracção.
     *
     * @throws \RuntimeException
     */
    public function downloadAndExtract(int $year, ?string $zipUrlOverride = null): string
    {
        $timeout = max(120, min(3600, (int) config('ieducar.saeb.microdados_download_timeout_seconds', 900)));
        $url = ($zipUrlOverride !== null && trim($zipUrlOverride) !== '')
            ? trim($zipUrlOverride)
            : $this->zipUrlForYear($year);

        $cacheRoot = storage_path('app/'.trim((string) config('ieducar.saeb.microdados_cache_path', 'saeb/microdados_cache'), '/'));
        if (! is_dir($cacheRoot)) {
            mkdir($cacheRoot, 0755, true);
        }

        $extractDir = $cacheRoot.'/extract_'.$year.'_'.bin2hex(random_bytes(4));
        if (! mkdir($extractDir, 0755, true) && ! is_dir($extractDir)) {
            throw new \RuntimeException(__('Não foi possível criar o directório de extracção.'));
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'saeb_microdados_');
        if ($tmpZip === false) {
            throw new \RuntimeException(__('Não foi possível criar ficheiro temporário para o ZIP.'));
        }

        try {
            $response = $this->pendingRequest($timeout, 'servlitcys/1.0 (SAEB microdados INEP)')
                ->sink($tmpZip)
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException(__('Download INEP falhou (HTTP :code): :url', [
                    'code' => (string) $response->status(),
                    'url' => $url,
                ]));
            }

            if (! is_readable($tmpZip) || filesize($tmpZip) < 1000) {
                throw new \RuntimeException(__('Ficheiro ZIP inválido ou vazio.'));
            }

            $zip = new ZipArchive;
            if ($zip->open($tmpZip) !== true) {
                throw new \RuntimeException(__('Não foi possível abrir o ZIP dos microdados SAEB.'));
            }

            $zip->extractTo($extractDir);
            $zip->close();

            return $extractDir;
        } catch (\Throwable $e) {
            File::deleteDirectory($extractDir);
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * Descarrega um CSV (dados.gov.br, link directo CKAN, etc.) para ficheiro temporário.
     *
     * @throws \RuntimeException
     */
    public function downloadCsvToTemp(string $url): string
    {
        $timeout = max(60, min(3600, (int) config('ieducar.saeb.microdados_download_timeout_seconds', 900)));
        $tmp = tempnam(sys_get_temp_dir(), 'saeb_opendata_');
        if ($tmp === false) {
            throw new \RuntimeException(__('Não foi possível criar ficheiro temporário.'));
        }
        $path = $tmp.'.csv';

        try {
            $response = $this->pendingRequest($timeout, 'servlitcys/1.0 (SAEB dados abertos)')
                ->sink($path)
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException(__('Download falhou (HTTP :code).', ['code' => (string) $response->status()]));
            }
            if (! is_readable($path) || filesize($path) < 10) {
                throw new \RuntimeException(__('Resposta vazia ou inválida.'));
            }

            return $path;
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function deleteDirectory(string $absolutePath): void
    {
        try {
            File::deleteDirectory($absolutePath);
        } catch (\Throwable $e) {
            Log::debug('saeb.microdados.cache_delete', ['path' => $absolutePath, 'message' => $e->getMessage()]);
        }
    }
}
