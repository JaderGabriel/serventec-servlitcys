<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Models\SaebImportMeta;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Descarrega ZIPs oficiais de divulgação IDEB (municípios), converte XLSX e faz upsert em saeb_indicator_points.
 */
final class IdebDivulgacaoInepImportService
{
    public function __construct(
        private SaebMicrodadosInepDownloader $downloader,
        private IdebDivulgacaoInepConverter $converter,
        private SaebHistoricoDatabase $historicoDb,
    ) {}

    /**
     * @param  list<string>|null  $scopes  ai|af|em (null = todos em config)
     * @return array{ok: bool, message: string, detalhes?: array<string, mixed>, avisos?: list<string>}
     */
    public function import(
        ?array $scopes = null,
        bool $download = true,
        bool $importSaebNotas = true,
        ?int $minYear = null,
        bool $onlyCatalogCities = false,
    ): array {
        if (! filter_var(config('ieducar.ideb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'message' => __('Séries IDEB desativadas (IEDUCAR_IDEB_SERIES_ENABLED).'),
            ];
        }

        $packages = config('ieducar.ideb.divulgacao_municipios_urls', []);
        if (! is_array($packages) || $packages === []) {
            return [
                'ok' => false,
                'message' => __('Nenhum pacote em ideb.divulgacao_municipios_urls.'),
            ];
        }

        $minYear = $minYear ?? (int) config('ieducar.ideb.min_year', 2015);
        $preferRede = (string) config('ieducar.ideb.prefer_rede', 'Municipal');
        if ($importSaebNotas && ! filter_var(config('ieducar.ideb.import_saeb_notas', true), FILTER_VALIDATE_BOOLEAN)) {
            $importSaebNotas = false;
        }

        $allowedIbge = null;
        $ibgeToCityIds = $this->ibgeToCityIdsMap();
        if ($onlyCatalogCities) {
            $allowedIbge = [];
            foreach (array_keys($ibgeToCityIds) as $ibge) {
                $allowedIbge[$ibge] = true;
            }
            if ($allowedIbge === []) {
                return [
                    'ok' => false,
                    'message' => __('Nenhuma cidade com IBGE no catálogo analítico.'),
                ];
            }
        }

        $selected = [];
        foreach ($packages as $key => $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $scope = strtolower((string) $key);
            if ($scopes !== null && $scopes !== [] && ! in_array($scope, $scopes, true)) {
                continue;
            }
            $url = trim((string) ($pkg['url'] ?? ''));
            $etapa = trim((string) ($pkg['etapa'] ?? ''));
            if ($url === '' || $etapa === '') {
                continue;
            }
            $selected[$scope] = [
                'url' => $url,
                'etapa' => $etapa,
                'year' => (int) ($pkg['year'] ?? 2025),
            ];
        }

        if ($selected === []) {
            return [
                'ok' => false,
                'message' => __('Nenhum pacote IDEB seleccionado (use ai, af e/ou em).'),
            ];
        }

        $cacheRel = trim((string) config('ieducar.ideb.cache_path', 'ideb/divulgacao')) ?: 'ideb/divulgacao';
        Storage::disk('local')->makeDirectory($cacheRel);
        $cacheAbs = storage_path('app/'.$cacheRel);

        $upserted = 0;
        $perScope = [];
        $warnings = [];
        $yearsIdeb = [];
        $yearsSaeb = [];

        foreach ($selected as $scope => $pkg) {
            try {
                $xlsx = $this->resolveXlsxPath($pkg['url'], $scope, (int) $pkg['year'], $cacheAbs, $download);
                $converted = $this->converter->spreadsheetToPontos(
                    $xlsx,
                    (string) $pkg['etapa'],
                    $minYear,
                    $importSaebNotas,
                    $preferRede,
                    $allowedIbge,
                    $ibgeToCityIds,
                );
                $n = count($converted['pontos']);
                // Upsert por pacote (evita acumular ~80k+ pontos em memória antes de gravar).
                $chunkSize = 500;
                for ($i = 0; $i < $n; $i += $chunkSize) {
                    $chunk = array_slice($converted['pontos'], $i, $chunkSize);
                    $upserted += $this->historicoDb->upsertRawPontos($chunk);
                }
                unset($converted['pontos']);
                foreach ($converted['years_ideb'] as $y) {
                    $yearsIdeb[(int) $y] = true;
                }
                foreach ($converted['years_saeb'] as $y) {
                    $yearsSaeb[(int) $y] = true;
                }
                $warnings = array_merge($warnings, $converted['warnings']);
                $perScope[$scope] = [
                    'etapa' => $pkg['etapa'],
                    'rows' => $n,
                    'municipios' => $converted['municipios'],
                    'xlsx' => $xlsx,
                    'years_ideb' => $converted['years_ideb'],
                    'years_saeb' => $converted['years_saeb'],
                ];
            } catch (\Throwable $e) {
                $warnings[] = __('Pacote :s: :msg', ['s' => $scope, 'msg' => $e->getMessage()]);
                $perScope[$scope] = [
                    'etapa' => $pkg['etapa'],
                    'rows' => 0,
                    'municipios' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($upserted === 0) {
            return [
                'ok' => false,
                'message' => __('Nenhum ponto IDEB/SAEB gerado.')."\n".implode("\n", $warnings),
                'avisos' => $warnings,
                'detalhes' => ['per_scope' => $perScope],
            ];
        }

        $this->patchImportMeta([
            'ideb_divulgacao' => [
                'importado_em' => now()->toIso8601String(),
                'min_year' => $minYear,
                'prefer_rede' => $preferRede,
                'import_saeb_notas' => $importSaebNotas,
                'pontos' => $upserted,
                'years_ideb' => array_keys($yearsIdeb),
                'years_saeb' => array_keys($yearsSaeb),
                'per_scope' => $perScope,
                'fonte' => 'https://download.inep.gov.br/ideb/resultados/',
            ],
            'fonte_efetiva' => 'ideb:divulgacao-inep',
            'importado_em' => now()->toIso8601String(),
        ]);

        $msg = __('IDEB importado: :n ponto(s) (upsert). Séries IDEB: :yi. SAEB nas planilhas: :ys.', [
            'n' => (string) $upserted,
            'yi' => implode(', ', array_map('strval', array_keys($yearsIdeb))) ?: '—',
            'ys' => implode(', ', array_map('strval', array_keys($yearsSaeb))) ?: '—',
        ]);

        return [
            'ok' => true,
            'message' => $msg,
            'avisos' => $warnings,
            'detalhes' => [
                'upserted' => $upserted,
                'per_scope' => $perScope,
                'years_ideb' => array_keys($yearsIdeb),
                'years_saeb' => array_keys($yearsSaeb),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function patchImportMeta(array $patch): void
    {
        $prev = $this->historicoDb->meta() ?? [];
        $meta = array_merge(is_array($prev) ? $prev : [], $patch);
        SaebImportMeta::query()->updateOrCreate(
            ['id' => SaebHistoricoDatabase::META_ROW_ID],
            ['meta' => $meta]
        );
    }

    /**
     * @return array<string, list<int>>
     */
    private function ibgeToCityIdsMap(): array
    {
        $out = [];
        foreach (City::query()->forAnalytics()->whereNotNull('ibge_municipio')->orderBy('id')->get(['id', 'ibge_municipio']) as $city) {
            $ibge = preg_replace('/\D/', '', (string) $city->ibge_municipio) ?? '';
            if (strlen($ibge) !== 7) {
                continue;
            }
            $out[$ibge] ??= [];
            $out[$ibge][] = (int) $city->id;
        }

        return $out;
    }

    private function resolveXlsxPath(string $url, string $scope, int $year, string $cacheAbs, bool $download): string
    {
        $zipName = basename(parse_url($url, PHP_URL_PATH) ?: "ideb_{$scope}_{$year}.zip");
        $zipPath = $cacheAbs.DIRECTORY_SEPARATOR.$zipName;
        $extractDir = $cacheAbs.DIRECTORY_SEPARATOR.pathinfo($zipName, PATHINFO_FILENAME);

        if ($download || ! is_file($zipPath)) {
            if (! $download && ! is_file($zipPath)) {
                throw new \RuntimeException(__('ZIP IDEB em falta (use sem --no-download): :p', ['p' => $zipPath]));
            }
            File::ensureDirectoryExists($cacheAbs);
            $this->downloader->downloadFileToPath(
                $url,
                $zipPath,
                'servlitcys/1.0 (IDEB divulgacao INEP)'
            );
        }

        if (! is_file($zipPath)) {
            throw new \RuntimeException(__('ZIP IDEB em falta: :p', ['p' => $zipPath]));
        }

        File::ensureDirectoryExists($extractDir);
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(__('Não foi possível abrir o ZIP IDEB.'));
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $xlsx = $this->findFirstXlsx($extractDir);
        if ($xlsx === null) {
            throw new \RuntimeException(__('Nenhum XLSX encontrado dentro do ZIP IDEB (:scope).', ['scope' => $scope]));
        }

        return $xlsx;
    }

    private function findFirstXlsx(string $dir): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) === 'xlsx') {
                return $file->getPathname();
            }
        }

        return null;
    }
}
