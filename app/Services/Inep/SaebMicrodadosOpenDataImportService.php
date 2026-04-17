<?php

namespace App\Services\Inep;

use App\Models\City;
use Illuminate\Support\Collection;

/**
 * Orquestra download de microdados SAEB (INEP ou CSV em dados.gov) e gravação em historico.json
 * apenas para municípios/UF das cidades com analytics activo.
 */
final class SaebMicrodadosOpenDataImportService
{
    public function __construct(
        private SaebMicrodadosInepDownloader $downloader,
        private SaebMicrodadosCsvStreamConverter $converter,
        private SaebCsvPedagogicalImportService $csvImport,
    ) {}

    /**
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, detalhes?: array<string, mixed>}
     */
    public function syncFromInepZip(int $year, bool $merge, bool $resolveInep, bool $purgeExtract = true, ?string $zipUrlOverride = null): array
    {
        if (! filter_var(config('ieducar.saeb.microdados_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'message' => __('Importação por microdados INEP desactivada (IEDUCAR_SAEB_MICRODADOS_ENABLED).'),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        }

        $cities = City::query()->forAnalytics()->whereNotNull('ibge_municipio')->orderBy('id')->get();
        $allowedIbge = $this->allowedIbgeSet($cities);
        if ($allowedIbge === []) {
            return [
                'ok' => false,
                'message' => __('Nenhuma cidade com IBGE (7 dígitos) e base configurada.'),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        }

        $extractDir = null;
        $canonicalPath = null;

        try {
            $extractDir = $this->downloader->downloadAndExtract($year, $zipUrlOverride);
            $csvPath = $this->converter->pickBestCsv($extractDir);
            if ($csvPath === null) {
                $this->downloader->deleteDirectory($extractDir);
                $extractDir = null;

                return [
                    'ok' => false,
                    'message' => __('Não foi encontrado um CSV adequado no ZIP (ajuste o dicionário de dados ou IEDUCAR_SAEB_MICRODADOS_CSV_MIN_SCORE).'),
                    'fonte_efetiva' => null,
                    'path' => $this->jsonPath(),
                ];
            }

            $canonicalPath = tempnam(sys_get_temp_dir(), 'saeb_canonical_');
            if ($canonicalPath === false) {
                throw new \RuntimeException(__('Não foi possível criar ficheiro temporário.'));
            }
            $canonicalPath .= '.csv';

            $stats = $this->converter->streamToCanonicalCsv($csvPath, $canonicalPath, $allowedIbge, $cities);

            if ($stats['rows'] === 0) {
                if ($extractDir !== null) {
                    $this->downloader->deleteDirectory($extractDir);
                    $extractDir = null;
                }

                return [
                    'ok' => false,
                    'message' => implode("\n", array_merge(
                        [__('Conversão sem linhas úteis.')],
                        $stats['warnings']
                    )),
                    'fonte_efetiva' => null,
                    'path' => $this->jsonPath(),
                    'detalhes' => [
                        'csv_escolhido' => $csvPath,
                        'linhas_ignoradas' => $stats['skipped'],
                    ],
                ];
            }

            $extra = __('Microdados INEP :year — linhas canónicas: :n; linhas ignoradas no filtro: :s.', [
                'year' => (string) $year,
                'n' => (string) $stats['rows'],
                's' => (string) $stats['skipped'],
            ]);
            if ($stats['warnings'] !== []) {
                $extra .= "\n".implode("\n", $stats['warnings']);
            }

            $result = $this->csvImport->importFromCsvFile($canonicalPath, $merge, $resolveInep);
            if ($result['ok'] && isset($result['message'])) {
                $result['message'] = $result['message']."\n\n".$extra;
            }
            if (! $result['ok']) {
                $result['detalhes'] = array_merge($result['detalhes'] ?? [], [
                    'csv_fonte' => $csvPath,
                    'stats' => $stats,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            if ($extractDir !== null) {
                $this->downloader->deleteDirectory($extractDir);
            }

            return [
                'ok' => false,
                'message' => __('Falha na sincronização de microdados: :msg', ['msg' => $e->getMessage()]),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        } finally {
            if ($canonicalPath !== null && is_file($canonicalPath)) {
                @unlink($canonicalPath);
            }
            if ($purgeExtract && $extractDir !== null && is_dir($extractDir)) {
                $this->downloader->deleteDirectory($extractDir);
            }
        }
    }

    /**
     * CSV directo (URL pública: dados.gov.br, CKAN, armazenamento próprio).
     *
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, detalhes?: array<string, mixed>}
     */
    public function syncFromRemoteCsvUrl(string $url, bool $merge, bool $resolveInep): array
    {
        if (! filter_var(config('ieducar.saeb.microdados_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'message' => __('Importação por microdados desactivada (IEDUCAR_SAEB_MICRODADOS_ENABLED).'),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return [
                'ok' => false,
                'message' => __('URL inválida (use http ou https).'),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        }

        $cities = City::query()->forAnalytics()->whereNotNull('ibge_municipio')->orderBy('id')->get();
        $allowedIbge = $this->allowedIbgeSet($cities);
        if ($allowedIbge === []) {
            return [
                'ok' => false,
                'message' => __('Nenhuma cidade com IBGE e base configurada.'),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        }

        $downloaded = null;
        $canonicalPath = null;

        try {
            $downloaded = $this->downloader->downloadCsvToTemp($url);
            $canonicalPath = tempnam(sys_get_temp_dir(), 'saeb_canonical_url_');
            if ($canonicalPath === false) {
                throw new \RuntimeException(__('Não foi possível criar ficheiro temporário.'));
            }
            $canonicalPath .= '.csv';

            $stats = $this->converter->streamToCanonicalCsv($downloaded, $canonicalPath, $allowedIbge, $cities);

            if ($stats['rows'] === 0) {
                return [
                    'ok' => false,
                    'message' => implode("\n", array_merge(
                        [__('Conversão sem linhas úteis.')],
                        $stats['warnings']
                    )),
                    'fonte_efetiva' => null,
                    'path' => $this->jsonPath(),
                    'detalhes' => ['url' => $url, 'skipped' => $stats['skipped']],
                ];
            }

            $extra = __('Fonte URL — linhas: :n; ignoradas: :s.', [
                'n' => (string) $stats['rows'],
                's' => (string) $stats['skipped'],
            ]);

            $result = $this->csvImport->importFromCsvFile($canonicalPath, $merge, $resolveInep);
            if ($result['ok'] && isset($result['message'])) {
                $result['message'] = $result['message']."\n\n".$extra;
            }
            $result['fonte_efetiva'] = $result['fonte_efetiva'] ?? 'saeb:opendata-url';

            return $result;
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => __('Falha ao obter ou processar o CSV: :msg', ['msg' => $e->getMessage()]),
                'fonte_efetiva' => null,
                'path' => $this->jsonPath(),
            ];
        } finally {
            if ($downloaded !== null && is_file($downloaded)) {
                @unlink($downloaded);
            }
            if ($canonicalPath !== null && is_file($canonicalPath)) {
                @unlink($canonicalPath);
            }
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     * @return array<string, true>
     */
    private function allowedIbgeSet($cities): array
    {
        $out = [];
        foreach ($cities as $city) {
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
     * URL do formulário admin ou CLI: CSV directo, ou ZIP INEP (detectado pelo sufixo / padrão do nome).
     *
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, detalhes?: array<string, mixed>}
     */
    public function syncFromMicrodadosFormUrl(string $url, bool $merge, bool $resolveInep, bool $purgeExtract, int $fallbackYear): array
    {
        $url = trim($url);
        if (SaebMicrodadosInepDownloader::isZipUrl($url)) {
            $fromUrl = SaebMicrodadosInepDownloader::yearFromZipUrl($url);
            $y = $fromUrl ?? max(2000, min(2100, $fallbackYear));

            return $this->syncFromInepZip($y, $merge, $resolveInep, $purgeExtract, $url);
        }

        return $this->syncFromRemoteCsvUrl($url, $merge, $resolveInep);
    }

    private function jsonPath(): string
    {
        return trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json')) ?: 'saeb/historico.json';
    }
}
