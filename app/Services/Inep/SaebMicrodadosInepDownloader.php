<?php

namespace App\Services\Inep;

use Illuminate\Http\Client\Response;
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
     * Ficheiros PEM de CA a tentar (ordem: bundles completos do SO antes dos caminhos do PHP/OpenSSL).
     *
     * @return list<string>
     */
    public static function collectSystemCaBundleCandidates(): array
    {
        $candidates = [];

        foreach (['SSL_CERT_FILE', 'CURL_CA_BUNDLE'] as $envKey) {
            $v = getenv($envKey);
            if (is_string($v) && $v !== '' && is_file($v) && is_readable($v)) {
                $candidates[] = $v;
            }
        }

        foreach ([
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/usr/lib/ssl/cert.pem',
            '/usr/local/share/certs/ca-root.crt',
        ] as $path) {
            if (is_readable($path)) {
                $candidates[] = $path;
            }
        }

        foreach (['curl.cainfo', 'openssl.cafile'] as $iniKey) {
            $v = ini_get($iniKey);
            if (is_string($v) && $v !== '' && is_readable($v)) {
                $candidates[] = $v;
            }
        }

        if (function_exists('openssl_get_cert_locations')) {
            $loc = openssl_get_cert_locations();
            foreach (['default_cert_file', 'cafile'] as $k) {
                if (! empty($loc[$k]) && is_string($loc[$k]) && is_readable($loc[$k])) {
                    $candidates[] = $loc[$k];
                }
            }
        }

        /** @var list<string> */
        return array_values(array_unique($candidates));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function httpClientOptionVariants(): array
    {
        $verifyEnabled = filter_var(config('ieducar.saeb.microdados_http_verify', true), FILTER_VALIDATE_BOOL);
        if (! $verifyEnabled) {
            return [['verify' => false]];
        }

        $variants = [];
        $seen = [];

        $push = function (array $opts) use (&$variants, &$seen): void {
            $key = json_encode($opts);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $variants[] = $opts;
        };

        $configured = trim((string) config('ieducar.saeb.microdados_http_ca_bundle', ''));
        if ($configured !== '' && is_readable($configured)) {
            $push($this->verifyOptionsForCaFile($configured));
        }

        foreach (self::collectSystemCaBundleCandidates() as $path) {
            $push($this->verifyOptionsForCaFile($path));
        }

        $push(['verify' => true]);

        if (filter_var(config('ieducar.saeb.microdados_http_insecure_fallback', false), FILTER_VALIDATE_BOOL)) {
            $push(['verify' => false]);
        }

        return $variants;
    }

    /**
     * @return array{verify: string, curl: array<int, string>}
     */
    private function verifyOptionsForCaFile(string $caPath): array
    {
        return [
            'verify' => $caPath,
            'curl' => [
                \CURLOPT_CAINFO => $caPath,
            ],
        ];
    }

    private function isSslConnectionError(\Throwable $e): bool
    {
        $m = strtolower($e->getMessage());

        return str_contains($m, 'ssl')
            || str_contains($m, 'certificate')
            || str_contains($m, 'curl error 60')
            || str_contains($m, 'unable to get local issuer');
    }

    /**
     * GET com sink, repetindo com outro bundle CA se o erro for SSL (cURL 60).
     *
     * @throws \Throwable
     */
    private function getWithSinkRespectingSsl(string $url, string $sinkPath, int $timeout, string $userAgent): Response
    {
        $variants = $this->httpClientOptionVariants();

        foreach ($variants as $index => $opts) {
            try {
                if (($opts['verify'] ?? null) === false) {
                    Log::warning('saeb.microdados.download_ssl_verify_disabled', ['url' => $url]);
                }

                $response = Http::timeout($timeout)
                    ->withHeaders(['User-Agent' => $userAgent])
                    ->withOptions($opts)
                    ->sink($sinkPath)
                    ->get($url);

                if ($response->successful()) {
                    return $response;
                }

                throw new \RuntimeException(__('Download falhou (HTTP :code).', ['code' => (string) $response->status()]));
            } catch (\Throwable $e) {
                if ($this->isSslConnectionError($e) && $index < count($variants) - 1) {
                    Log::debug('saeb.microdados.http_ssl_retry', [
                        'attempt' => $index + 1,
                        'of' => count($variants),
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }

                throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
            }
        }

        throw new \RuntimeException(__('Download falhou após tentativas SSL.'));
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
            $this->getWithSinkRespectingSsl($url, $tmpZip, $timeout, 'servlitcys/1.0 (SAEB microdados INEP)');

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
            $this->getWithSinkRespectingSsl($url, $path, $timeout, 'servlitcys/1.0 (SAEB dados abertos)');

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
