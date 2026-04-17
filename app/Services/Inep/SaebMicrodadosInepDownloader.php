<?php

namespace App\Services\Inep;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
     * Descarrega e extrai o ZIP; devolve o caminho absoluto do directório de extracção.
     *
     * @throws \RuntimeException
     */
    public function downloadAndExtract(int $year): string
    {
        $timeout = max(120, min(3600, (int) config('ieducar.saeb.microdados_download_timeout_seconds', 900)));
        $url = $this->zipUrlForYear($year);

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
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'servlitcys/1.0 (SAEB microdados INEP)'])
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
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'servlitcys/1.0 (SAEB dados abertos)'])
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
