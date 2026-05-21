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

        $progress = $this->beginTaskLog($task);

        try {
            $result = match ($task->domain.'::'.$task->task_key) {
                'fundeb::import_city_year' => $this->runFundebImportCity($task, $progress),
                'fundeb::import_bulk_year' => $this->runFundebImportBulk($task, $progress),
                'fundeb::sync_all_years' => $this->runFundebSyncAll($task, $progress),
                'fundeb::new_city_auto' => $this->runFundebNewCity($task, $progress),
                'geo::ieducar', 'geo::microdados', 'geo::official', 'geo::pipeline', 'geo::probe' => $this->runGeoArtisan($task, $progress),
                'pedagogical::import_official' => $this->runPedagogicalOfficial($task, $progress),
                'pedagogical::import_urls' => $this->runPedagogicalUrls($task, $progress),
                'pedagogical::import_csv' => $this->runPedagogicalCsv($task, $progress),
                'pedagogical::import_microdados' => $this->runPedagogicalMicrodados($task, $progress),
                'ieducar::schema_probe' => $this->runIeducarSchemaProbe($task, $progress),
                default => throw new InvalidArgumentException(__('Tarefa não suportada: :key', ['key' => $task->domain.'::'.$task->task_key])),
            };

            $this->finishTaskLog($progress, $result);

            return $result;
        } catch (\Throwable $e) {
            $progress->error($e->getMessage());
            throw $e;
        }
    }

    private function beginTaskLog(AdminSyncTask $task): AdminSyncTaskProgress
    {
        $progress = AdminSyncTaskProgress::forTask($task);
        $progress->info(__('Tarefa #:id — :label', ['id' => (string) $task->id, 'label' => $task->label]));
        $progress->explain(AdminSyncTaskExplainer::summary($task));
        foreach (AdminSyncTaskExplainer::payloadHints($task) as $hint) {
            $progress->detail($hint);
        }

        return $progress;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function finishTaskLog(AdminSyncTaskProgress $progress, array $result): void
    {
        $success = (bool) ($result['success'] ?? $result['ok'] ?? false);
        $message = (string) ($result['message'] ?? '');

        if ($success) {
            $progress->success($message !== '' ? $message : __('Concluído com sucesso.'));
        } else {
            $progress->error($message !== '' ? $message : __('Concluído com falha (ver mensagem/resultado).'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebImportCity(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $city = City::query()->findOrFail((int) ($payload['city_id'] ?? $task->city_id));
        $ano = (int) ($payload['ano'] ?? 0);
        $useNearest = (bool) ($payload['use_nearest_year'] ?? false);

        $progress->step(1, 2, __('A importar FUNDEB para :city, ano :ano…', [
            'city' => $city->name,
            'ano' => (string) $ano,
        ]));

        $fundebLog = $this->fundebProgress($task);
        $result = $this->fundebImport->importForCityYear($city, $ano, $useNearest, $fundebLog);

        $progress->step(2, 2, __('Importação por município terminada.'));

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'import' => $result,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebImportBulk(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $ano = (int) ($payload['ano'] ?? 0);
        $progress->step(1, 2, __('Importação FUNDEB em lote — ano :ano.', ['ano' => (string) $ano]));

        $fundebLog = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulk(
            $ano,
            (bool) ($payload['use_nearest_year'] ?? false),
            isset($payload['city_id']) ? (int) $payload['city_id'] : null,
            $fundebLog,
        );

        $progress->step(2, 2, __('Lote FUNDEB terminado.'));

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebSyncAll(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
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

        $progress->step(1, 2, __('Sincronização multi-ano — :n ano(s).', ['n' => (string) count($years)]));

        $fundebLog = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulkForYears(
            array_map('intval', $years),
            (bool) ($payload['use_nearest_year'] ?? false),
            $cityIds !== null ? array_map('intval', $cityIds) : null,
            $fundebLog,
        );

        $progress->step(2, 2, __('Sincronização multi-ano terminada.'));

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runFundebNewCity(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $cityId = (int) ($payload['city_id'] ?? $task->city_id);
        $years = $payload['years'] ?? FundebOpenDataImportService::yearsForNewCitySync();

        $progress->step(1, 2, __('FUNDEB automático — cidade :id, anos :anos.', [
            'id' => (string) $cityId,
            'anos' => implode(', ', array_map('strval', (array) $years)),
        ]));

        $fundebLog = $this->fundebProgress($task);
        $result = $this->fundebImport->importBulkForYears(
            array_map('intval', (array) $years),
            false,
            [$cityId],
            $fundebLog,
        );

        $progress->step(2, 2, __('FUNDEB automático terminado.'));

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'bulk' => $result,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runGeoArtisan(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $command = (string) ($payload['artisan_command'] ?? '');
        $args = is_array($payload['artisan_args'] ?? null) ? $payload['artisan_args'] : [];

        if ($command === '') {
            throw new InvalidArgumentException(__('Comando Artisan em falta no payload geo.'));
        }

        $progress->step(1, 3, __('A executar comando Artisan…'));
        $progress->detail('php artisan '.$command.' '.json_encode($args, JSON_UNESCAPED_UNICODE));

        $exitCode = Artisan::call($command, $args);
        $output = Artisan::output();

        $progress->step(2, 3, __('Comando terminado (código :code).', ['code' => (string) $exitCode]));

        if (trim($output) !== '') {
            $progress->step(3, 3, __('Saída do comando:'));
            $progress->appendBlock($output);
        } else {
            $progress->step(3, 3, __('Sem saída textual do comando.'));
        }

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => (string) ($task->output_log ?? ''),
            'step' => $payload['step'] ?? $task->task_key,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalOfficial(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $override = isset($payload['official_url_override']) && $payload['official_url_override'] !== ''
            ? (string) $payload['official_url_override']
            : null;

        $progress->step(1, 3, __('A obter JSON SAEB por município (IBGE)…'));
        if ($override !== null) {
            $progress->detail(__('URL modelo personalizada (formulário).'));
        }

        $result = $this->saebOfficial->importFromOfficialTemplate($override);

        $progress->step(2, 3, __('Resposta da importação oficial recebida.'));
        $this->logPedagogicalDetails($progress, $result);

        return $this->pedagogicalResult($result, $task);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalUrls(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $urls = trim((string) config('ieducar.saeb.import_urls', ''));
        $progress->step(1, 2, __('A tentar URLs em IEDUCAR_SAEB_IMPORT_URLS…'));
        if ($urls === '') {
            $progress->warn(__('Nenhuma URL configurada no .env.'));
        } else {
            $progress->detail($urls);
        }

        $result = $this->saebImport->importFromConfiguredSources();
        $progress->step(2, 2, __('Tentativas de URL concluídas.'));
        $this->logPedagogicalDetails($progress, $result);

        return $this->pedagogicalResult($result, $task);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalCsv(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $path = (string) ($payload['csv_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            throw new InvalidArgumentException(__('Ficheiro CSV não encontrado para importação.'));
        }

        $progress->step(1, 3, __('A ler CSV :file…', ['file' => basename($path)]));
        $progress->detail(__('Fundir: :m | INEP→escola: :i', [
            'm' => ($payload['csv_merge'] ?? true) ? __('sim') : __('não'),
            'i' => ($payload['csv_resolve_inep'] ?? true) ? __('sim') : __('não'),
        ]));

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

        $progress->step(2, 3, __('CSV processado.'));
        $this->logPedagogicalDetails($progress, $result);

        return $this->pedagogicalResult($result, $task);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPedagogicalMicrodados(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
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
            $progress->step(1, 4, __('Fonte remota (URL/CSV/ZIP)…'));
            $progress->detail($url);
            $result = $this->saebMicrodados->syncFromMicrodadosFormUrl($url, $merge, $resolveInep, $purgeExtract, $fallbackYear);
        } else {
            $zipUrl = str_replace('{year}', (string) $fallbackYear, (string) config(
                'ieducar.saeb.microdados_inep_zip_url_template',
                'https://download.inep.gov.br/microdados/microdados_saeb_{year}.zip'
            ));
            $progress->step(1, 4, __('A descarregar microdados INEP (ano :year)…', ['year' => (string) $fallbackYear]));
            $progress->explain(__('O ZIP é grande; o passo inclui download SSL, extracção, escolha do CSV e gravação na base.'));
            $progress->detail($zipUrl);
            $result = $this->saebMicrodados->syncFromInepZip($fallbackYear, $merge, $resolveInep, $purgeExtract, null);
        }

        $progress->step(2, 4, __('Processamento de microdados terminado.'));
        $this->logPedagogicalDetails($progress, $result);

        return $this->pedagogicalResult($result, $task);
    }

    /**
     * @return array<string, mixed>
     */
    private function runIeducarSchemaProbe(AdminSyncTask $task, AdminSyncTaskProgress $progress): array
    {
        $payload = $task->payload ?? [];
        $city = City::query()->findOrFail((int) ($payload['city_id'] ?? $task->city_id));

        $progress->step(1, 4, __('A ligar à base i-Educar de :city…', ['city' => $city->name]));

        $filters = new IeducarFilterState(
            ano_letivo: (string) ($payload['ano_letivo'] ?? 'all'),
            escola_id: isset($payload['escola_id']) ? (int) $payload['escola_id'] : null,
            curso_id: isset($payload['curso_id']) ? (int) $payload['curso_id'] : null,
            turno_id: isset($payload['turno_id']) ? (int) $payload['turno_id'] : null,
        );

        $progress->step(2, 4, __('A analisar schema e tabelas usadas pelo painel…'));

        $document = $this->cityData->run($city, function ($db) use ($city, $filters) {
            return IeducarCompatibilityProbe::exportDocument($db, $city, $filters);
        });

        $progress->step(3, 4, __('A gravar export JSON…'));

        $dir = storage_path('app/admin_sync/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $filename = 'schema_probe_'.$ibge.'_'.$task->id.'_'.now()->format('Y-m-d_His').'.json';
        $absolute = $dir.'/'.$filename;
        file_put_contents($absolute, json_encode($document, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $progress->step(4, 4, __('Export gravado: :file', ['file' => $filename]));

        return [
            'success' => true,
            'message' => __('Export de compatibilidade gerado.'),
            'export_path' => $absolute,
            'export_filename' => $filename,
            'city_id' => $city->id,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function logPedagogicalDetails(AdminSyncTaskProgress $progress, array $result): void
    {
        if (isset($result['fonte_efetiva']) && is_string($result['fonte_efetiva']) && $result['fonte_efetiva'] !== '') {
            $progress->detail(__('Fonte: :f', ['f' => $result['fonte_efetiva']]));
        }

        $detalhes = $result['detalhes'] ?? null;
        if (is_array($detalhes)) {
            if (isset($detalhes['erros']) && is_array($detalhes['erros'])) {
                foreach (array_slice($detalhes['erros'], 0, 15) as $err) {
                    if (is_string($err) && $err !== '') {
                        $progress->warn($err);
                    }
                }
                if (count($detalhes['erros']) > 15) {
                    $progress->warn(__('… e mais :n avisos/erros.', ['n' => (string) (count($detalhes['erros']) - 15)]));
                }
            }
            if (isset($detalhes['urls']) && is_array($detalhes['urls'])) {
                foreach (array_slice($detalhes['urls'], 0, 5) as $u) {
                    if (is_string($u) && $u !== '') {
                        $progress->detail($u);
                    }
                }
            }
        }

        $avisos = $result['avisos'] ?? null;
        if (is_array($avisos)) {
            foreach ($avisos as $aviso) {
                if (is_string($aviso) && $aviso !== '') {
                    $progress->warn($aviso);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function pedagogicalResult(array $result, AdminSyncTask $task): array
    {
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'avisos' => $result['avisos'] ?? [],
            'details' => $result,
            'output' => (string) ($task->output_log ?? ''),
        ];
    }

    private function fundebProgress(AdminSyncTask $task): FundebImportProgress
    {
        return new FundebImportProgress(function (string $level, string $message) use ($task): void {
            $line = '['.now()->format('H:i:s').'] ['.$level.'] '.$message;
            $existing = (string) ($task->output_log ?? '');
            $task->output_log = $existing === '' ? $line : $existing."\n".$line;
            $task->saveQuietly();
        });
    }
}
