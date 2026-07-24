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
 * Nota: nos arquivos públicos recentes do Censo, as colunas de latitude/longitude podem
 * estar ausentes (LGPD); nesse caso o import não preenche coordenadas a partir do CSV.
 */
class InepMicrodadosCadastroEscolasDownloader
{
    /**
     * Remove CSVs anteriores do mesmo conjunto antes de gravar um novo (evita vários arquivos).
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
     * Descarrega o ZIP do INEP para um ano específico sem apagar CSVs de outros anos.
     *
     * @throws \RuntimeException em falha de rede ou arquivo inválido
     */
    public function downloadAndExtractForYear(int $year): string
    {
        $this->purgeYearCsv($year);

        return $this->extractYearZip($year);
    }

    /**
     * Descarrega o ZIP do INEP, extrai o CSV de escolas e devolve o caminho absoluto.
     *
     * @throws \RuntimeException em falha de rede ou arquivo inválido
     */
    public function downloadAndExtract(?int $year = null): string
    {
        $year ??= $this->resolveYear();
        $this->purgeExistingExtractedCsvs();

        return $this->extractYearZip($year);
    }

    /**
     * @throws \RuntimeException em falha de rede ou arquivo inválido
     */
    private function extractYearZip(int $year): string
    {
        $url = $this->resolveZipUrl($year);

        $tmpZip = tempnam(sys_get_temp_dir(), 'inep_microdados_');
        if ($tmpZip === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário.');
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
                throw new \RuntimeException('Arquivo ZIP INEP inválido ou vazio.');
            }

            $zip = new ZipArchive;
            if ($zip->open($tmpZip) !== true) {
                throw new \RuntimeException('Não foi possível abrir o ZIP do INEP.');
            }

            $innerCsvPath = $this->findMicrodadosCsvInZip($zip);
            if ($innerCsvPath !== null) {
                $destPath = $this->extractCsvMember($zip, $innerCsvPath, $year);
                $zip->close();

                return $destPath;
            }

            $matriculaPath = $this->findZipMember($zip, [
                '#/dados/Tabela_Matricula_'.$year.'\.csv$#i',
                '#/dados/Tabela_Matricula_\d{4}\.csv$#i',
                '#Tabela_Matricula_'.$year.'\.csv$#i',
            ]);
            $escolaPath = $this->findZipMember($zip, [
                '#/dados/Tabela_Escola_'.$year.'\.csv$#i',
                '#/dados/Tabela_Escola_\d{4}\.csv$#i',
                '#Tabela_Escola_'.$year.'\.csv$#i',
            ]);
            if ($matriculaPath !== null && $escolaPath !== null) {
                $destPath = $this->mergeMatriculaEscolaPackage($zip, $escolaPath, $matriculaPath, $year);
                $zip->close();

                return $destPath;
            }

            $zip->close();
            throw new \RuntimeException('ZIP INEP sem microdados_ed_basica_*.csv nem Tabela_Matricula/Tabela_Escola.');
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * URL do ZIP: template configurável, com fallbacks (ex.: 2025_ no portal INEP).
     */
    public function resolveZipUrl(int $year): string
    {
        $template = (string) config(
            'ieducar.inep_geocoding.microdados_download_url_template',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_{year}.zip'
        );
        $primary = str_replace('{year}', (string) $year, $template);
        $candidates = array_values(array_unique(array_filter([
            $primary,
            // Portal INEP 2025 (jul/2026): ficheiro com underscore final.
            str_replace('{year}', $year.'_', $template),
            'https://download.inep.gov.br/dados_abertos/microdados_censo_escolar_'.$year.'.zip',
            'https://download.inep.gov.br/dados_abertos/microdados_censo_escolar_'.$year.'_.zip',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_'.$year.'.zip',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_'.$year.'_.zip',
        ])));

        foreach ($candidates as $url) {
            try {
                $r = Http::timeout(20)->withHeaders(['User-Agent' => 'servlitcys/1.0 (INEP microdados)'])->head($url);
                if ($r->successful()) {
                    return $url;
                }
            } catch (\Throwable $e) {
                Log::debug('INEP microdados HEAD falhou', ['url' => $url, 'message' => $e->getMessage()]);
            }
        }

        return $primary;
    }

    private function findMicrodadosCsvInZip(ZipArchive $zip): ?string
    {
        $fallback = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#/dados/microdados_ed_basica_\d{4}\.csv$#i', $name) === 1) {
                return $name;
            }
            if (
                $fallback === null
                && preg_match('#microdados_ed_basica_\d{4}\.csv$#i', $name) === 1
            ) {
                $fallback = $name;
            }
            if (
                $fallback === null
                && preg_match('#(^|/)dados/microdados_ed_basica\.csv$#i', $name) === 1
            ) {
                $fallback = $name;
            }
        }

        return $fallback;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function findZipMember(ZipArchive $zip, array $patterns): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name) === 1) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Extrai um CSV legado (formato único) para storage/app/public/inep.
     */
    private function extractCsvMember(ZipArchive $zip, string $innerCsvPath, int $year): string
    {
        $stream = $zip->getStream($innerCsvPath);
        if ($stream === false) {
            throw new \RuntimeException('Não foi possível ler o CSV dentro do ZIP.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('inep');
        $targetRel = 'inep/microdados_ed_basica_'.$year.'.csv';
        $destPath = $disk->path($targetRel);
        $out = fopen($destPath, 'wb');
        if ($out === false) {
            fclose($stream);
            throw new \RuntimeException('Não foi possível gravar '.$destPath);
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        if (! is_readable($destPath)) {
            throw new \RuntimeException('CSV extraído não está legível.');
        }

        return $destPath;
    }

    /**
     * Pacote 2025+: Tabela_Matricula (QT_MAT_*) + Tabela_Escola (CO_MUNICIPIO / TP_DEPENDENCIA)
     * → CSV único compatível com InepCensoMunicipioMatriculasIndexer.
     */
    private function mergeMatriculaEscolaPackage(
        ZipArchive $zip,
        string $escolaMember,
        string $matriculaMember,
        int $year,
    ): string {
        $escolaStream = $zip->getStream($escolaMember);
        if ($escolaStream === false) {
            throw new \RuntimeException('Não foi possível ler Tabela_Escola no ZIP.');
        }

        /** @var array<string, array{ibge: string, dep: string}> $byEntity */
        $byEntity = [];
        $escolaHeader = fgetcsv($escolaStream, 0, ';', '"', '\\');
        if ($escolaHeader === false) {
            fclose($escolaStream);
            throw new \RuntimeException('Tabela_Escola sem cabeçalho.');
        }
        $escolaMap = [];
        foreach ($escolaHeader as $i => $col) {
            $escolaMap[mb_strtolower(trim((string) $col))] = (int) $i;
        }
        $entIdx = $escolaMap['co_entidade'] ?? null;
        $munIdx = $escolaMap['co_municipio'] ?? null;
        $depIdx = $escolaMap['tp_dependencia'] ?? null;
        if ($entIdx === null || $munIdx === null) {
            fclose($escolaStream);
            throw new \RuntimeException('Tabela_Escola sem CO_ENTIDADE/CO_MUNICIPIO.');
        }
        while (($row = fgetcsv($escolaStream, 0, ';', '"', '\\')) !== false) {
            $ent = trim((string) ($row[$entIdx] ?? ''));
            $mun = trim((string) ($row[$munIdx] ?? ''));
            if ($ent === '' || $mun === '') {
                continue;
            }
            $byEntity[$ent] = [
                'ibge' => $mun,
                'dep' => $depIdx !== null ? trim((string) ($row[$depIdx] ?? '')) : '',
            ];
        }
        fclose($escolaStream);

        $matStream = $zip->getStream($matriculaMember);
        if ($matStream === false) {
            throw new \RuntimeException('Não foi possível ler Tabela_Matricula no ZIP.');
        }
        $matHeader = fgetcsv($matStream, 0, ';', '"', '\\');
        if ($matHeader === false) {
            fclose($matStream);
            throw new \RuntimeException('Tabela_Matricula sem cabeçalho.');
        }
        $matMap = [];
        foreach ($matHeader as $i => $col) {
            $matMap[mb_strtolower(trim((string) $col))] = (int) $i;
        }
        $matEntIdx = $matMap['co_entidade'] ?? null;
        if ($matEntIdx === null) {
            fclose($matStream);
            throw new \RuntimeException('Tabela_Matricula sem CO_ENTIDADE.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('inep');
        $targetRel = 'inep/microdados_ed_basica_'.$year.'.csv';
        $destPath = $disk->path($targetRel);
        $out = fopen($destPath, 'wb');
        if ($out === false) {
            fclose($matStream);
            throw new \RuntimeException('Não foi possível gravar '.$destPath);
        }

        $outHeader = array_merge(['NU_ANO_CENSO', 'CO_MUNICIPIO', 'TP_DEPENDENCIA'], $matHeader);
        fputcsv($out, $outHeader, ';', '"', '\\');

        $written = 0;
        while (($row = fgetcsv($matStream, 0, ';', '"', '\\')) !== false) {
            $ent = trim((string) ($row[$matEntIdx] ?? ''));
            $meta = $byEntity[$ent] ?? null;
            if ($meta === null) {
                continue;
            }
            $ano = trim((string) ($row[$matMap['nu_ano_censo'] ?? -1] ?? ''));
            if ($ano === '') {
                $ano = (string) $year;
            }
            fputcsv($out, array_merge([$ano, $meta['ibge'], $meta['dep']], $row), ';', '"', '\\');
            $written++;
        }
        fclose($matStream);
        fclose($out);

        if ($written === 0) {
            @unlink($destPath);
            throw new \RuntimeException('Merge Matricula×Escola não produziu linhas (CO_ENTIDADE sem match).');
        }

        Log::info('INEP microdados 2025+ mesclados', [
            'year' => $year,
            'schools_map' => count($byEntity),
            'rows' => $written,
            'path' => $destPath,
        ]);

        return $destPath;
    }

    private function purgeYearCsv(int $year): void
    {
        $disk = Storage::disk('public');
        $rel = 'inep/microdados_ed_basica_'.$year.'.csv';
        if ($disk->exists($rel)) {
            $disk->delete($rel);
        }
    }
}
