<?php

namespace App\Services\Inep;

use App\Models\City;
use Illuminate\Support\Facades\File;

/**
 * Descarrega planilhas oficiais INEP (XLSX/RAR), converte para CSV canónico e importa SAEB municipal.
 */
final class SaebPlanilhaInepImportService
{
    public function __construct(
        private SaebMicrodadosInepDownloader $downloader,
        private SaebPlanilhaInepArchive $archive,
        private SaebPlanilhaInepSpreadsheetResolver $spreadsheetResolver,
        private SaebPlanilhaInepConverter $converter,
        private SaebCsvPedagogicalImportService $csvImport,
    ) {}

    /**
     * Importa planilhas INEP para **todos** os municípios (CO_MUNICIPIO) — uso Horizonte / cobertura nacional.
     *
     * @param  list<int>  $years
     * @return array{ok: bool, message: string, detalhes?: array<string, mixed>}
     */
    public function importYearsNational(
        array $years,
        bool $download,
        bool $merge,
        bool $resolveInep,
        bool $keepCache,
    ): array {
        return $this->importYearsInternal($years, $download, $merge, $resolveInep, $keepCache, null);
    }

    /**
     * Importa **um** ano de planilha INEP (nacional) — uso Horizonte particionado por ano.
     *
     * @return array{ok: bool, message: string, skipped?: bool, detalhes?: array<string, mixed>}
     */
    public function importSingleYearNational(
        int $year,
        bool $download,
        bool $merge,
        bool $resolveInep,
        bool $keepCache,
        ?array $allowedIbge = null,
    ): array {
        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'message' => __('Séries SAEB desativadas (IEDUCAR_SAEB_SERIES_ENABLED).'),
            ];
        }

        $urls = config('ieducar.saeb.planilha_resultados_urls', []);
        if (! is_array($urls)) {
            $urls = [];
        }

        $url = $urls[$year] ?? null;
        if (! is_string($url) || $url === '') {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => __('Ano :y: sem URL em planilha_resultados_urls.', ['y' => (string) $year]),
                'detalhes' => ['year' => $year, 'rows' => 0, 'municipios' => 0],
            ];
        }

        try {
            $stats = $this->processYear($year, $url, $allowedIbge, $download, $keepCache);

            if (($stats['rows'] ?? 0) === 0) {
                @unlink($stats['path']);

                return [
                    'ok' => true,
                    'skipped' => true,
                    'message' => __('Ano :y: conversão sem linhas municipais.', ['y' => (string) $year]),
                    'detalhes' => ['year' => $year, 'per_year' => [$year => $stats], 'rows' => 0],
                ];
            }

            $import = $this->csvImport->importFromCsvFile($stats['path'], $merge, $resolveInep);
            @unlink($stats['path']);

            if (! ($import['ok'] ?? false)) {
                return array_merge($import, [
                    'detalhes' => ['year' => $year, 'per_year' => [$year => $stats]],
                ]);
            }

            $import['message'] = ($import['message'] ?? '')."\n".__(
                'Planilha INEP :year — :n linha(s); municípios: :m.',
                [
                    'year' => (string) $year,
                    'n' => (string) ($stats['rows'] ?? 0),
                    'm' => (string) ($stats['municipios'] ?? 0),
                ]
            );
            $import['fonte_efetiva'] = $import['fonte_efetiva'] ?? 'saeb:planilhas-inep';
            $import['detalhes'] = [
                'year' => $year,
                'rows' => (int) ($stats['rows'] ?? 0),
                'municipios' => (int) ($stats['municipios'] ?? 0),
                'per_year' => [$year => $stats],
            ];

            return $import;
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => __('Ano :y: :msg', ['y' => (string) $year, 'msg' => $e->getMessage()]),
                'detalhes' => ['year' => $year],
            ];
        }
    }

    /**
     * @param  list<int>  $years
     * @return array{ok: bool, message: string, detalhes?: array<string, mixed>}
     */
    public function importYears(
        array $years,
        bool $download,
        bool $merge,
        bool $resolveInep,
        bool $keepCache,
    ): array {
        $allowedIbge = $this->allowedIbgeSet();
        if ($allowedIbge === []) {
            return [
                'ok' => false,
                'message' => __('Nenhuma cidade com IBGE (7 dígitos) para importar.'),
            ];
        }

        return $this->importYearsInternal($years, $download, $merge, $resolveInep, $keepCache, $allowedIbge);
    }

    /**
     * @param  list<int>  $years
     * @param  array<string, true>|null  $allowedIbge  null = todos os municípios da planilha
     * @return array{ok: bool, message: string, detalhes?: array<string, mixed>}
     */
    private function importYearsInternal(
        array $years,
        bool $download,
        bool $merge,
        bool $resolveInep,
        bool $keepCache,
        ?array $allowedIbge,
    ): array {
        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'message' => __('Séries SAEB desativadas (IEDUCAR_SAEB_SERIES_ENABLED).'),
            ];
        }

        $urls = config('ieducar.saeb.planilha_resultados_urls', []);
        if (! is_array($urls)) {
            $urls = [];
        }

        $canonicalPath = $this->canonicalOutputPath();
        $rowsTotal = 0;
        $warnings = [];
        $perYear = [];

        $out = fopen($canonicalPath, 'wb');
        if ($out === false) {
            return ['ok' => false, 'message' => __('Não foi possível criar CSV de saída.')];
        }
        fwrite($out, "municipio_ibge;ano_aplicacao;disciplina;etapa;valor;status;inep_escola\n");
        fclose($out);

        foreach ($years as $year) {
            $url = $urls[$year] ?? null;
            if (! is_string($url) || $url === '') {
                $warnings[] = __('Ano :y: sem URL em planilha_resultados_urls.', ['y' => (string) $year]);

                continue;
            }

            try {
                $stats = $this->processYear($year, $url, $allowedIbge, $download, $keepCache);
                $this->appendCanonicalRows($canonicalPath, $stats['path']);
                @unlink($stats['path']);
                $rowsTotal += $stats['rows'];
                $perYear[$year] = $stats;
                foreach ($stats['warnings'] as $w) {
                    $warnings[] = '['.$year.'] '.$w;
                }
            } catch (\Throwable $e) {
                $warnings[] = __('Ano :y: :msg', ['y' => (string) $year, 'msg' => $e->getMessage()]);
            }
        }

        if ($rowsTotal === 0) {
            @unlink($canonicalPath);

            return [
                'ok' => false,
                'message' => implode("\n", array_merge(
                    [__('Nenhuma linha gerada a partir das planilhas.')],
                    $warnings
                )),
                'detalhes' => ['per_year' => $perYear, 'warnings' => $warnings],
            ];
        }

        $import = $this->csvImport->importFromCsvFile($canonicalPath, $merge, $resolveInep);
        @unlink($canonicalPath);

        if (! $import['ok']) {
            return array_merge($import, [
                'detalhes' => ['per_year' => $perYear, 'warnings' => $warnings],
            ]);
        }

        $import['message'] = ($import['message'] ?? '')."\n\n".__(
            'Planilhas INEP — :n linha(s) canónicas; municípios com dados: :m.',
            [
                'n' => (string) $rowsTotal,
                'm' => (string) array_sum(array_map(static fn (array $s): int => (int) ($s['municipios'] ?? 0), $perYear)),
            ]
        );
        $import['fonte_efetiva'] = $import['fonte_efetiva'] ?? 'saeb:planilhas-inep';
        $import['detalhes'] = ['per_year' => $perYear, 'warnings' => $warnings];

        return $import;
    }

    /**
     * @return array{ok: bool, message: string, detalhes?: array<string, mixed>}
     */
    public function importFromUrl(
        string $url,
        ?int $yearHint,
        bool $download,
        bool $merge,
        bool $resolveInep,
        bool $keepCache,
    ): array {
        $allowedIbge = $this->allowedIbgeSet();
        if ($allowedIbge === []) {
            return ['ok' => false, 'message' => __('Nenhuma cidade com IBGE.')];
        }

        $year = $yearHint ?? (int) date('Y') - 1;
        $canonicalPath = $this->canonicalOutputPath();

        try {
            if ($download) {
                $stats = $this->processYear($year, $url, $allowedIbge, true, $keepCache);
            } else {
                $path = $this->resolveLocalPath($url);
                $stats = $this->convertSpreadsheet($path, $allowedIbge, $year, $this->tempCsvPath($year));
            }
            File::copy($stats['path'], $canonicalPath);
            @unlink($stats['path']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (($stats['rows'] ?? 0) === 0) {
            return ['ok' => false, 'message' => __('Conversão sem linhas para municípios cadastrados.')];
        }

        $import = $this->csvImport->importFromCsvFile($canonicalPath, $merge, $resolveInep);
        @unlink($canonicalPath);

        return $import;
    }

    /**
     * @param  array<string, true>  $allowedIbge
     * @return array{rows: int, municipios: int, path: string, warnings: list<string>}
     */
    private function processYear(int $year, string $url, ?array $allowedIbge, bool $download, bool $keepCache): array
    {
        $cacheRoot = storage_path('app/'.trim((string) config('ieducar.saeb.planilha_cache_path', 'saeb/planilhas'), '/'));
        if (! is_dir($cacheRoot)) {
            mkdir($cacheRoot, 0755, true);
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $localName = 'saeb_planilha_'.$year.'.'.$ext;
        $localPath = $cacheRoot.'/'.$localName;

        if ($download || ! is_file($localPath)) {
            $this->downloader->downloadFileToPath($url, $localPath);
        }

        $spreadsheetPath = $localPath;
        $extractDir = null;

        if ($ext === 'rar') {
            $extractDir = $cacheRoot.'/extract_'.$year.'_'.bin2hex(random_bytes(3));
            $candidates = $this->archive->extractRarAndFindSpreadsheets($localPath, $extractDir);
            $spreadsheetPath = $this->archive->pickBestSpreadsheet($candidates);
            if ($spreadsheetPath === null) {
                if (! $keepCache && $extractDir !== null) {
                    File::deleteDirectory($extractDir);
                }
                throw new \RuntimeException(__('Nenhuma planilha XLSX/XLSB encontrada dentro do RAR.'));
            }
        }

        try {
            return $this->convertSpreadsheet($spreadsheetPath, $allowedIbge, $year, $this->tempCsvPath($year));
        } finally {
            if (! $keepCache && $extractDir !== null && is_dir($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }
    }

    /**
     * @param  array<string, true>  $allowedIbge
     * @return array{rows: int, municipios: int, path: string, warnings: list<string>}
     */
    private function convertSpreadsheet(string $spreadsheetPath, ?array $allowedIbge, int $year, string $tempCsv): array
    {
        $readable = $this->spreadsheetResolver->resolveReadablePath($spreadsheetPath);

        return $this->converter->spreadsheetToCanonicalCsv($readable, $tempCsv, $allowedIbge, $year);
    }

    private function tempCsvPath(int $year): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'saeb_planilha_'.$year.'_');
        if ($tmp === false) {
            throw new \RuntimeException(__('Não foi possível criar ficheiro temporário.'));
        }
        $path = $tmp.'.csv';
        @unlink($tmp);

        return $path;
    }

    private function canonicalOutputPath(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'saeb_planilhas_merge_');
        if ($tmp === false) {
            throw new \RuntimeException(__('Não foi possível criar ficheiro temporário.'));
        }
        $path = $tmp.'.csv';
        @unlink($tmp);

        return $path;
    }

    private function appendCanonicalRows(string $targetPath, string $sourcePath): void
    {
        $in = fopen($sourcePath, 'rb');
        $out = fopen($targetPath, 'ab');
        if ($in === false || $out === false) {
            if ($in !== false) {
                fclose($in);
            }
            if ($out !== false) {
                fclose($out);
            }
            throw new \RuntimeException(__('Erro ao fundir CSVs.'));
        }

        $first = true;
        while (($line = fgets($in)) !== false) {
            if ($first) {
                $first = false;
                if (str_starts_with($line, 'municipio_ibge')) {
                    continue;
                }
            }
            fwrite($out, $line);
        }

        fclose($in);
        fclose($out);
    }

    private function resolveLocalPath(string $urlOrPath): string
    {
        if (is_file($urlOrPath) && is_readable($urlOrPath)) {
            return realpath($urlOrPath) ?: $urlOrPath;
        }

        $rel = storage_path('app/'.trim($urlOrPath, '/'));
        if (is_file($rel) && is_readable($rel)) {
            return realpath($rel) ?: $rel;
        }

        $base = base_path(trim($urlOrPath, '/'));
        if (is_file($base) && is_readable($base)) {
            return realpath($base) ?: $base;
        }

        throw new \RuntimeException(__('Ficheiro local não encontrado: :p', ['p' => $urlOrPath]));
    }

    /**
     * @return array<string, true>
     */
    private function allowedIbgeSet(): array
    {
        $out = [];
        foreach (City::query()->forAnalytics()->whereNotNull('ibge_municipio')->get() as $city) {
            $d = preg_replace('/\D/', '', (string) ($city->ibge_municipio ?? '')) ?? '';
            if (strlen($d) < 7) {
                $d = str_pad($d, 7, '0', STR_PAD_LEFT);
            }
            if (strlen($d) === 7) {
                $out[$d] = true;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function parseYearsOption(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            $urls = config('ieducar.saeb.planilha_resultados_urls', []);

            return is_array($urls)
                ? array_values(array_map('intval', array_keys($urls)))
                : [];
        }

        $years = [];
        foreach (preg_split('/\s*,\s*/', trim($raw)) ?: [] as $part) {
            if (is_numeric($part)) {
                $y = (int) $part;
                if ($y >= 1995 && $y <= 2100) {
                    $years[] = $y;
                }
            }
        }

        return array_values(array_unique($years));
    }
}
