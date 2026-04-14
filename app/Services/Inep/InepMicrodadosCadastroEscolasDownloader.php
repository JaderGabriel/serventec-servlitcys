<?php

namespace App\Services\Inep;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Descarrega o ZIP oficial do INEP (Censo Escolar), extrai `microdados_ed_basica_*.csv`
 * para o disco public (`storage/app/public/inep/`).
 *
 * Nota: nos ficheiros públicos recentes do Censo, as colunas de latitude/longitude podem
 * estar ausentes (LGPD); nesse caso o import não preenche coordenadas a partir do CSV.
 */
class InepMicrodadosCadastroEscolasDownloader
{
    /**
     * Remove CSVs anteriores do mesmo conjunto antes de gravar um novo (evita vários ficheiros).
     */
    public function purgeExistingExtractedCsvs(): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('inep')) {
            return;
        }
        foreach ($disk->files('inep') as $rel) {
            $base = basename($rel);
            if (preg_match('/^(microdados_ed_basica_|MICRODADOS_CADASTRO_ESCOLAS_).+\.csv$/i', $base)) {
                $disk->delete($rel);
            }
        }
    }

    /**
     * Descobre o ano a usar (config ou tentativa descendente até encontrar o ZIP).
     */
    public function resolveYear(): int
    {
        $configured = trim((string) config('ieducar.inep_geocoding.microdados_download_year', ''));
        if ($configured !== '' && ctype_digit($configured)) {
            return max(2000, min(2100, (int) $configured));
        }

        $start = (int) date('Y');
        $template = (string) config(
            'ieducar.inep_geocoding.microdados_download_url_template',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_{year}.zip'
        );

        for ($y = $start; $y >= 2015; $y--) {
            $url = str_replace('{year}', (string) $y, $template);
            try {
                $r = Http::timeout(15)->head($url);
                if ($r->successful()) {
                    return $y;
                }
            } catch (\Throwable $e) {
                Log::debug('INEP microdados HEAD falhou', ['year' => $y, 'message' => $e->getMessage()]);
            }
        }

        return $start;
    }

    /**
     * Descarrega o ZIP do INEP, extrai o CSV de escolas e devolve o caminho absoluto.
     *
     * @throws \RuntimeException em falha de rede ou ficheiro inválido
     */
    public function downloadAndExtract(?int $year = null): string
    {
        $year ??= $this->resolveYear();
        $template = (string) config(
            'ieducar.inep_geocoding.microdados_download_url_template',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_{year}.zip'
        );
        $url = str_replace('{year}', (string) $year, $template);

        $this->purgeExistingExtractedCsvs();

        $tmpZip = tempnam(sys_get_temp_dir(), 'inep_microdados_');
        if ($tmpZip === false) {
            throw new \RuntimeException('Não foi possível criar ficheiro temporário.');
        }

        try {
            $response = Http::timeout(600)
                ->withHeaders(['User-Agent' => 'servlitcys/1.0 (INEP microdados)'])
                ->sink($tmpZip)
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Download INEP falhou (HTTP '.$response->status().'): '.$url);
            }

            if (! is_readable($tmpZip) || filesize($tmpZip) < 1000) {
                throw new \RuntimeException('Ficheiro ZIP INEP inválido ou vazio.');
            }

            $zip = new ZipArchive;
            if ($zip->open($tmpZip) !== true) {
                throw new \RuntimeException('Não foi possível abrir o ZIP do INEP.');
            }

            $innerCsvPath = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#/dados/microdados_ed_basica_\d{4}\.csv$#', $name)) {
                    $innerCsvPath = $name;
                    break;
                }
            }
            if ($innerCsvPath === null) {
                $zip->close();
                throw new \RuntimeException('ZIP INEP sem microdados_ed_basica_*.csv em …/dados/.');
            }

            $stream = $zip->getStream($innerCsvPath);
            if ($stream === false) {
                $zip->close();
                throw new \RuntimeException('Não foi possível ler o CSV dentro do ZIP.');
            }

            $disk = Storage::disk('public');
            $disk->makeDirectory('inep');
            $targetRel = 'inep/microdados_ed_basica_'.$year.'.csv';
            $destPath = $disk->path($targetRel);
            $out = fopen($destPath, 'wb');
            if ($out === false) {
                fclose($stream);
                $zip->close();
                throw new \RuntimeException('Não foi possível gravar '.$destPath);
            }
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
            $zip->close();

            if (! is_readable($destPath)) {
                throw new \RuntimeException('CSV extraído não está legível.');
            }

            return $destPath;
        } finally {
            @unlink($tmpZip);
        }
    }
}
