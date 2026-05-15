<?php

namespace App\Services\AdminSync;

use App\Models\AdminSyncTask;
use App\Models\City;
use App\Services\CityDataConnection;
use App\Services\Fundeb\FundebImportProgress;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Inep\SaebCsvPedagogicalImportService;
use App\Services\Inep\SaebMicrodadosOpenDataImportService;
use App\Services\Inep\SaebOfficialMunicipalImportService;
use App\Services\Inep\SaebPedagogicalImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

final class AdminSyncTaskRunner
{
    public function __construct(
        private FundebOpenDataImportService $fundebImport,
        private SaebPedagogicalImportService $saebImport,
        private SaebOfficialMunicipalImportService $saebOfficial,
        private SaebCsvPedagogicalImportService $saebCsv,
        private SaebMicrodadosOpenDataImportService $saebMicrodados,
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(AdminSyncTask $task): array
    {
        @set_time_limit(max(60, (int) config('ieducar.admin_sync.job_timeout', 3600)));

        return match ($task->domain.'::'.$task->task_key) {
            'fundeb::import_city_year' => $this->runFundebImportCity($task),
            'fundeb::import_bulk_year' => $this->runFundebImportBulk($task),
            'fundeb::sync_all_years' => $this->runFundebSyncAll($task),
            'fundeb::new_city_auto' => $this->runFundebNewCity($task),
            'geo::ieducar', 'geo::microdados', 'geo::official', 'geo::pipeline', 'geo::probe' => $this->runGeoArtisan($task),
            'pedagogical::import_official' => $this->runPedagogicalOfficial($task),
            'pedagogical::import_urls' => $this->runPedagogicalUrls($task),
            'pedagogical::import_csv' => $this->runPedagogicalCsv($task),
            'pedagogical::import_microdados' => $this->runPedagogicalMicrodados($task),
            'ieducar::schema_probe' => $this->runIeducarSchemaProbe($task),
            default => throw new InvalidArgumentException(__('Tarefa não suportada: :key', ['key' => $task->domain.'::'.$task->task_key])),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebImportCity(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $city = City::query()->findOrFail((int) ($payload['city_id'] ?? $task->city_id));
        $ano = (int) ($payload['ano'] ?? 0);
        $useNearest = (bool) ($payload['use_nearest_year'] ?? false);

        $progress = $this->fundebProgress($task);
        $result = $this->fundebImport->importForCityYear($city, $ano, $useNearest, $progress);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'import' => $result,
            'output' => $progress->formatForDisplay(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebImportBulk(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $result = $this->fundebImport->importBulk(
            (int) ($payload['ano'] ?? 0),
            (bool) ($payload['use_nearest_year'] ?? false),
            isset($payload['city_id']) ? (int) $payload['city_id'] : null,
            $this->fundebProgress($task),
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebSyncAll(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $years = $payload['years'] ?? [];
        if (! is_array($years) || $years === []) {
            $years = $this->fundebImport->resolveSyncYears(
                (int) ($payload['ano_from'] ?? 0),
                (int) ($payload['ano_to'] ?? 0),
                (bool) ($payload['include_cached_years'] ?? true),
                (bool) ($payload['include_database_years'] ?? true),
            );
        }

        $cityIds = $payload['city_ids'] ?? null;
        if ($cityIds !== null && ! is_array($cityIds)) {
            $cityIds = null;
        }

        $progress = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulkForYears(
            array_map('intval', $years),
            (bool) ($payload['use_nearest_year'] ?? false),
            $cityIds !== null ? array_map('intval', $cityIds) : null,
            $progress,
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
            'output' => $progress->formatForDisplay(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebNewCity(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $cityId = (int) ($payload['city_id'] ?? $task->city_id);
        $years = $payload['years'] ?? FundebOpenDataImportService::yearsForNewCitySync();

        $progress = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulkForYears(
            array_map('intval', (array) $years),
            false,
            [$cityId],
            $progress,
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
            'output' => $progress->formatForDisplay(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runGeoArtisan(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $command = (string) ($payload['artisan_command'] ?? '');
        $args = is_array($payload['artisan_args'] ?? null) ? $payload['artisan_args'] : [];

        if ($command === '') {
            throw new InvalidArgumentException(__('Comando Artisan em falta no payload geo.'));
        }

        $exitCode = Artisan::call($command, $args);
        $output = Artisan::output();

        $task->update(['output_log' => $output]);

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
            'step' => $payload['step'] ?? $task->task_key,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalOfficial(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $override = isset($payload['official_url_override']) && $payload['official_url_override'] !== ''
            ? (string) $payload['official_url_override']
            : null;

        $result = $this->saebOfficial->importFromOfficialTemplate($override);

        return $this->pedagogicalResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalUrls(AdminSyncTask $task): array
    {
        $result = $this->saebImport->importFromConfiguredSources();

        return $this->pedagogicalResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalCsv(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $path = (string) ($payload['csv_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            throw new InvalidArgumentException(__('Ficheiro CSV não encontrado para importação.'));
        }

        try {
            $result = $this->saebCsv->importFromCsvFile(
                $path,
                (bool) ($payload['csv_merge'] ?? true),
                (bool) ($payload['csv_resolve_inep'] ?? true),
            );
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->pedagogicalResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalMicrodados(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $merge = (bool) ($payload['md_merge'] ?? true);
        $resolveInep = (bool) ($payload['md_resolve_inep'] ?? true);
        $purgeExtract = ! (bool) ($payload['md_keep_cache'] ?? false);
        $fallbackYear = max(2000, min(2100, (int) ($payload['md_year'] ?? max(2000, (int) date('Y') - 1))));
        $url = trim((string) ($payload['md_url'] ?? ''));

        if ($url === '') {
            $configured = trim((string) config('ieducar.saeb.microdados_opendata_csv_url', ''));
            if ($configured !== '') {
                $url = $configured;
            }
        }

        if ($url !== '') {
            $result = $this->saebMicrodados->syncFromMicrodadosFormUrl($url, $merge, $resolveInep, $purgeExtract, $fallbackYear);
        } else {
            $result = $this->saebMicrodados->syncFromInepZip($fallbackYear, $merge, $resolveInep, $purgeExtract, null);
        }

        return $this->pedagogicalResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function runIeducarSchemaProbe(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        $city = City::query()->findOrFail((int) ($payload['city_id'] ?? $task->city_id));
        $filters = new IeducarFilterState(
            ano_letivo: (string) ($payload['ano_letivo'] ?? 'all'),
            escola_id: isset($payload['escola_id']) ? (int) $payload['escola_id'] : null,
            curso_id: isset($payload['curso_id']) ? (int) $payload['curso_id'] : null,
            turno_id: isset($payload['turno_id']) ? (int) $payload['turno_id'] : null,
        );

        $document = $this->cityData->run($city, function ($db) use ($city, $filters) {
            return IeducarCompatibilityProbe::exportDocument($db, $city, $filters);
        });

        $dir = storage_path('app/admin_sync/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $filename = 'schema_probe_'.$ibge.'_'.$task->id.'_'.now()->format('Y-m-d_His').'.json';
        $absolute = $dir.'/'.$filename;
        file_put_contents($absolute, json_encode($document, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return [
            'success' => true,
            'export_path' => $absolute,
            'export_filename' => $filename,
            'city_id' => $city->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function pedagogicalResult(array $result): array
    {
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'avisos' => $result['avisos'] ?? [],
            'details' => $result,
        ];
    }

    private function fundebProgress(AdminSyncTask $task): FundebImportProgress
    {
        return new FundebImportProgress(function (string $level, string $message) use ($task): void {
            $line = '['.$level.'] '.$message;
            $existing = (string) ($task->output_log ?? '');
            $task->output_log = $existing === '' ? $line : $existing."\n".$line;
            $task->saveQuietly();
        });
    }
}
