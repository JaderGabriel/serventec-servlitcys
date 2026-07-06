<?php

namespace App\Services\Horizonte;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalDemographySnapshot;
use App\Models\MunicipalTransferSnapshot;
use App\Models\SaebIndicatorPoint;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteMunicipalSgeCache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Exporta/importa dados Horizonte (tabelas + cache IBGE/SGE) para transferência local → produção sem git.
 */
final class HorizonteDataBundleService
{
    public const BUNDLE_VERSION = 2;

    private const IBGE_CACHE_TTL = 604800;

    private const CHUNK_SIZE = 500;

    /**
     * @param  array<string, bool>  $sections
     * @return array{success: bool, message: string, path: string, manifest: array<string, mixed>}
     */
    public function export(array $sections, ?string $outputPath = null): array
    {
        $enabled = $this->normalizeSections($sections);
        if ($enabled === []) {
            return [
                'success' => false,
                'message' => __('Nenhuma seção selecionada para exportar.'),
                'path' => '',
                'manifest' => [],
            ];
        }

        $dir = storage_path('app/horizonte/bundles/build-'.now()->format('Ymd-His'));
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(__('Não foi possível criar directório temporário.'));
        }

        $counts = [];
        $files = [];

        if ($enabled['fundeb'] ?? false) {
            $files['fundeb.jsonl'] = $this->exportFundeb($dir.'/fundeb.jsonl', $counts);
        }
        if ($enabled['censo'] ?? false) {
            $files['censo.jsonl'] = $this->exportCenso($dir.'/censo.jsonl', $counts);
        }
        if ($enabled['saeb'] ?? false) {
            $files['saeb.jsonl'] = $this->exportSaeb($dir.'/saeb.jsonl', $counts);
        }
        if ($enabled['ibge_cache'] ?? false) {
            $files['ibge_cache.json'] = $this->exportIbgeCache($dir.'/ibge_cache.json', $counts);
        }
        if ($enabled['sge_registry'] ?? false) {
            $files['sge_registry.json'] = $this->exportSgeRegistry($dir.'/sge_registry.json', $counts);
        }
        if ($enabled['cadunico'] ?? false) {
            $files['cadunico.jsonl'] = $this->exportCadunico($dir.'/cadunico.jsonl', $counts);
        }
        if ($enabled['demography'] ?? false) {
            $files['demography.jsonl'] = $this->exportDemography($dir.'/demography.jsonl', $counts);
        }
        if ($enabled['transfers'] ?? false) {
            $files['transfers.jsonl'] = $this->exportTransfers($dir.'/transfers.jsonl', $counts);
        }

        $manifest = [
            'version' => self::BUNDLE_VERSION,
            'exported_at' => now()->toIso8601String(),
            'reference_year' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'sections' => array_keys(array_filter($enabled)),
            'counts' => $counts,
            'files' => array_keys($files),
        ];

        file_put_contents($dir.'/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $zipPath = $outputPath ?? storage_path('app/horizonte/bundles/horizonte-'.now()->format('Ymd-His').'.zip');
        $zipDir = dirname($zipPath);
        if (! is_dir($zipDir) && ! mkdir($zipDir, 0755, true) && ! is_dir($zipDir)) {
            throw new \RuntimeException(__('Não foi possível criar directório de destino.'));
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeDirectory($dir);

            return [
                'success' => false,
                'message' => __('Não foi possível criar arquivo ZIP.'),
                'path' => '',
                'manifest' => $manifest,
            ];
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        $zip->close();
        $this->removeDirectory($dir);

        Storage::disk('local')->put('horizonte/bundles/latest.zip', file_get_contents($zipPath));

        return [
            'success' => true,
            'message' => __('Pacote Horizonte exportado — :path', ['path' => $zipPath]),
            'path' => $zipPath,
            'manifest' => $manifest,
        ];
    }

    /**
     * @param  array<string, bool>  $sections
     * @return array{success: bool, message: string, imported: array<string, int>, manifest: array<string, mixed>}
     */
    public function import(string $zipPath, array $sections = [], bool $dryRun = false): array
    {
        if (! is_readable($zipPath)) {
            return [
                'success' => false,
                'message' => __('Arquivo não encontrado ou ilegível: :path', ['path' => $zipPath]),
                'imported' => [],
                'manifest' => [],
            ];
        }

        $tmpdir = storage_path('app/horizonte/bundles/import-'.uniqid('', true));
        if (! is_dir($tmpdir) && ! mkdir($tmpdir, 0755, true) && ! is_dir($tmpdir)) {
            throw new \RuntimeException(__('Não foi possível criar directório temporário.'));
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->removeDirectory($tmpdir);

            return [
                'success' => false,
                'message' => __('ZIP inválido ou corrompido.'),
                'imported' => [],
                'manifest' => [],
            ];
        }
        $zip->extractTo($tmpdir);
        $zip->close();

        $manifestRaw = @file_get_contents($tmpdir.'/manifest.json');
        $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
        if (! is_array($manifest) || (int) ($manifest['version'] ?? 0) < 1 || (int) ($manifest['version'] ?? 0) > self::BUNDLE_VERSION) {
            $this->removeDirectory($tmpdir);

            return [
                'success' => false,
                'message' => __('Manifest inválido ou versão incompatível.'),
                'imported' => [],
                'manifest' => is_array($manifest) ? $manifest : [],
            ];
        }

        $enabled = $this->normalizeSections($sections);
        if ($enabled === []) {
            foreach ($manifest['sections'] ?? [] as $section) {
                $enabled[(string) $section] = true;
            }
        }

        $imported = [];

        if (($enabled['fundeb'] ?? false) && is_readable($tmpdir.'/fundeb.jsonl')) {
            $imported['fundeb'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/fundeb.jsonl')
                : $this->importFundeb($tmpdir.'/fundeb.jsonl');
        }
        if (($enabled['censo'] ?? false) && is_readable($tmpdir.'/censo.jsonl')) {
            $imported['censo'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/censo.jsonl')
                : $this->importCenso($tmpdir.'/censo.jsonl');
        }
        if (($enabled['saeb'] ?? false) && is_readable($tmpdir.'/saeb.jsonl')) {
            $imported['saeb'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/saeb.jsonl')
                : $this->importSaeb($tmpdir.'/saeb.jsonl');
        }
        if (($enabled['ibge_cache'] ?? false) && is_readable($tmpdir.'/ibge_cache.json')) {
            $imported['ibge_cache'] = $dryRun
                ? 1
                : $this->importIbgeCache($tmpdir.'/ibge_cache.json');
        }
        if (($enabled['sge_registry'] ?? false) && is_readable($tmpdir.'/sge_registry.json')) {
            $imported['sge_registry'] = $dryRun
                ? 1
                : $this->importSgeRegistry($tmpdir.'/sge_registry.json');
        }
        if (($enabled['cadunico'] ?? false) && is_readable($tmpdir.'/cadunico.jsonl')) {
            $imported['cadunico'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/cadunico.jsonl')
                : $this->importCadunico($tmpdir.'/cadunico.jsonl');
        }
        if (($enabled['demography'] ?? false) && is_readable($tmpdir.'/demography.jsonl')) {
            $imported['demography'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/demography.jsonl')
                : $this->importDemography($tmpdir.'/demography.jsonl');
        }
        if (($enabled['transfers'] ?? false) && is_readable($tmpdir.'/transfers.jsonl')) {
            $imported['transfers'] = $dryRun
                ? $this->countJsonlLines($tmpdir.'/transfers.jsonl')
                : $this->importTransfers($tmpdir.'/transfers.jsonl');
        }

        $this->removeDirectory($tmpdir);

        $total = array_sum($imported);
        $prefix = $dryRun ? '[dry-run] ' : '';

        return [
            'success' => $total > 0 || $imported !== [],
            'message' => $prefix.__('Importação Horizonte concluída — :n registo(s)/seção(ões).', ['n' => (string) $total]),
            'imported' => $imported,
            'manifest' => $manifest,
        ];
    }

    /**
     * @param  array<string, bool>  $sections
     * @return array<string, bool>
     */
    public function normalizeSections(array $sections): array
    {
        $defaults = [
            'fundeb' => true,
            'censo' => true,
            'saeb' => true,
            'cadunico' => true,
            'demography' => true,
            'transfers' => true,
            'ibge_cache' => true,
            'sge_registry' => true,
        ];

        if ($sections === []) {
            return $defaults;
        }

        $normalized = [];
        foreach ($defaults as $key => $_) {
            if (array_key_exists($key, $sections)) {
                $normalized[$key] = (bool) $sections[$key];
            }
        }

        return $normalized !== [] ? $normalized : $defaults;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportFundeb(string $path, array &$counts): string
    {
        $handle = fopen($path, 'wb');
        $count = 0;

        FundebMunicipioReference::query()
            ->whereNotNull('ibge_municipio')
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            });

        fclose($handle);
        $counts['fundeb'] = $count;

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportCenso(string $path, array &$counts): string
    {
        if (! Schema::hasTable('inep_censo_municipio_matriculas')) {
            $counts['censo'] = 0;
            touch($path);

            return $path;
        }

        $handle = fopen($path, 'wb');
        $count = 0;

        InepCensoMunicipioMatricula::query()
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            });

        fclose($handle);
        $counts['censo'] = $count;

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportSaeb(string $path, array &$counts): string
    {
        $handle = fopen($path, 'wb');
        $count = 0;

        SaebIndicatorPoint::query()
            ->whereNotNull('ibge_municipio')
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            });

        fclose($handle);
        $counts['saeb'] = $count;

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportIbgeCache(string $path, array &$counts): string
    {
        $payload = ['uf_catalogs' => [], 'meta' => []];
        $ufCount = 0;

        foreach (IbgeMunicipalityCatalog::brazilianUfs() as $uf) {
            $catalog = AdminHomeMapCache::get('ibge_municipality_catalog_uf:'.$uf);
            if (is_array($catalog) && $catalog !== []) {
                $payload['uf_catalogs'][$uf] = $catalog;
                $ufCount++;
                foreach ($catalog as $meta) {
                    if (is_array($meta) && isset($meta['ibge'])) {
                        $payload['meta'][$meta['ibge']] = $meta;
                    }
                }
            }
        }

        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $counts['ibge_cache_ufs'] = $ufCount;
        $counts['ibge_cache_meta'] = count($payload['meta']);

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportSgeRegistry(string $path, array &$counts): string
    {
        $index = HorizonteMunicipalSgeCache::get();
        $localPath = trim((string) config('horizonte.sge.registry_path', 'horizonte/sge_registry.json'));
        $localRaw = null;
        if ($localPath !== '' && Storage::disk('local')->exists($localPath)) {
            $localRaw = Storage::disk('local')->get($localPath);
        }

        $payload = [
            'cache_index' => $index,
            'local_registry_raw' => $localRaw,
        ];

        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $counts['sge_registry'] = count($index);

        return $path;
    }

    private function importFundeb(string $path): int
    {
        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row)) {
                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            $ano = (int) ($row['ano'] ?? 0);
            if ($ibge === null || $ano < 1990) {
                continue;
            }

            unset($row['id'], $row['created_at'], $row['updated_at']);
            $row['ibge_municipio'] = $ibge;
            $row['imported_at'] = $row['imported_at'] ?? now()->toIso8601String();

            FundebMunicipioReference::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $ano],
                $row,
            );
            $imported++;
        }

        fclose($handle);

        return $imported;
    }

    private function importCenso(string $path): int
    {
        if (! Schema::hasTable('inep_censo_municipio_matriculas')) {
            return 0;
        }

        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row)) {
                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            $ano = (int) ($row['ano'] ?? 0);
            if ($ibge === null || $ano < 1990) {
                continue;
            }

            unset($row['id'], $row['created_at'], $row['updated_at']);
            $row['ibge_municipio'] = $ibge;
            $row['imported_at'] = $row['imported_at'] ?? now()->toIso8601String();

            InepCensoMunicipioMatricula::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $ano],
                $row,
            );
            $imported++;
        }

        fclose($handle);

        return $imported;
    }

    private function importSaeb(string $path): int
    {
        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row)) {
                continue;
            }

            $dedupeKey = trim((string) ($row['dedupe_key'] ?? ''));
            if ($dedupeKey === '') {
                continue;
            }

            unset($row['id'], $row['created_at'], $row['updated_at']);

            SaebIndicatorPoint::query()->updateOrCreate(
                ['dedupe_key' => $dedupeKey],
                $row,
            );
            $imported++;
        }

        fclose($handle);

        return $imported;
    }

    private function importIbgeCache(string $path): int
    {
        $raw = file_get_contents($path);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($payload)) {
            return 0;
        }

        $count = 0;
        $repo = AdminHomeMapCache::repository();

        foreach (is_array($payload['uf_catalogs'] ?? null) ? $payload['uf_catalogs'] : [] as $uf => $catalog) {
            if (! is_array($catalog) || $catalog === []) {
                continue;
            }
            $repo->put('ibge_municipality_catalog_uf:'.strtoupper((string) $uf), $catalog, self::IBGE_CACHE_TTL);
            $count++;
        }

        foreach (is_array($payload['meta'] ?? null) ? $payload['meta'] : [] as $ibge => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $repo->put('ibge_municipality_meta:'.(string) $ibge, $meta, self::IBGE_CACHE_TTL);
        }

        return $count;
    }

    private function importSgeRegistry(string $path): int
    {
        $raw = file_get_contents($path);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($payload)) {
            return 0;
        }

        $index = is_array($payload['cache_index'] ?? null) ? $payload['cache_index'] : [];
        HorizonteMunicipalSgeCache::put($index);

        $localRaw = $payload['local_registry_raw'] ?? null;
        if (is_string($localRaw) && trim($localRaw) !== '') {
            $rel = trim((string) config('horizonte.sge.registry_path', 'horizonte/sge_registry.json'));
            if ($rel !== '') {
                Storage::disk('local')->put($rel, $localRaw);
            }
        }

        return count($index);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportCadunico(string $path, array &$counts): string
    {
        if (! Schema::hasTable('cadunico_municipio_snapshots')) {
            $counts['cadunico'] = 0;
            touch($path);

            return $path;
        }

        $handle = fopen($path, 'wb');
        $count = 0;
        CadunicoMunicipioSnapshot::query()->orderBy('id')->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }
        });
        fclose($handle);
        $counts['cadunico'] = $count;

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportDemography(string $path, array &$counts): string
    {
        if (! Schema::hasTable('municipal_demography_snapshots')) {
            $counts['demography'] = 0;
            touch($path);

            return $path;
        }

        $handle = fopen($path, 'wb');
        $count = 0;
        MunicipalDemographySnapshot::query()->orderBy('id')->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }
        });
        fclose($handle);
        $counts['demography'] = $count;

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function exportTransfers(string $path, array &$counts): string
    {
        if (! Schema::hasTable('municipal_transfer_snapshots')) {
            $counts['transfers'] = 0;
            touch($path);

            return $path;
        }

        $handle = fopen($path, 'wb');
        $count = 0;
        MunicipalTransferSnapshot::query()->orderBy('id')->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, &$count): void {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($this->modelToArray($row), JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }
        });
        fclose($handle);
        $counts['transfers'] = $count;

        return $path;
    }

    private function importCadunico(string $path): int
    {
        if (! Schema::hasTable('cadunico_municipio_snapshots')) {
            return 0;
        }

        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row) || empty($row['ibge_municipio'])) {
                continue;
            }
            unset($row['id'], $row['created_at'], $row['updated_at']);
            CadunicoMunicipioSnapshot::query()->updateOrCreate(
                [
                    'ibge_municipio' => (string) $row['ibge_municipio'],
                    'ano_referencia' => (int) ($row['ano_referencia'] ?? 0),
                ],
                $row,
            );
            $imported++;
        }
        fclose($handle);

        return $imported;
    }

    private function importDemography(string $path): int
    {
        if (! Schema::hasTable('municipal_demography_snapshots')) {
            return 0;
        }

        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row) || empty($row['ibge_municipio'])) {
                continue;
            }
            unset($row['id'], $row['created_at'], $row['updated_at']);
            MunicipalDemographySnapshot::query()->updateOrCreate(
                [
                    'ibge_municipio' => (string) $row['ibge_municipio'],
                    'ano_referencia' => (int) ($row['ano_referencia'] ?? 0),
                    'fonte' => (string) ($row['fonte'] ?? 'ibge_sidra'),
                ],
                $row,
            );
            $imported++;
        }
        fclose($handle);

        return $imported;
    }

    private function importTransfers(string $path): int
    {
        if (! Schema::hasTable('municipal_transfer_snapshots')) {
            return 0;
        }

        $imported = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row) || empty($row['ibge_municipio'])) {
                continue;
            }
            unset($row['id'], $row['created_at'], $row['updated_at']);
            MunicipalTransferSnapshot::query()->updateOrCreate(
                [
                    'ibge_municipio' => (string) $row['ibge_municipio'],
                    'ano' => (int) ($row['ano'] ?? 0),
                    'fonte' => (string) ($row['fonte'] ?? 'unknown'),
                    'programa_id' => (string) ($row['programa_id'] ?? 'geral'),
                ],
                $row,
            );
            $imported++;
        }
        fclose($handle);

        return $imported;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelToArray(object $model): array
    {
        $array = $model->toArray();
        foreach ($array as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $array[$key] = $value->format('c');
            }
        }

        return $array;
    }

    private function countJsonlLines(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
}
