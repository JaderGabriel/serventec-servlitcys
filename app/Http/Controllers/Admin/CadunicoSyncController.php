<?php

namespace App\Http\Controllers\Admin;

use App\Authorization\PublicDataHub;
use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\CadunicoMunicipioSnapshot;
use App\Models\CadunicoTerritorioSnapshot;
use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Cadunico\CadunicoCkanDiscovery;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Services\Cadunico\CadunicoSagiMisocialClient;
use App\Services\Cadunico\CadunicoTerritorioCsvImportService;
use App\Support\Cadunico\CadunicoCecadUpload;
use App\Support\Cadunico\CadunicoStoragePaths;
use App\Http\Requests\Admin\CadunicoSyncIndexRequest;
use App\Http\Requests\Admin\CadunicoSyncRunRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CadunicoSyncController extends Controller
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
        private CadunicoOpenDataImportService $import,
        private CadunicoMunicipioSnapshotRepository $snapshots,
        private CadunicoSagiMisocialClient $misocial,
        private CadunicoCkanDiscovery $ckanDiscovery,
        private CadunicoTerritorioCsvImportService $territorioImport,
    ) {}

    public function index(CadunicoSyncIndexRequest $request): View
    {
        $cities = City::query()->forAnalytics()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio']);
        $refYear = CadunicoOpenDataImportService::suggestedImportYear();
        $maxYear = (int) date('Y');

        $matrixDefaults = CadunicoMunicipioSnapshotRepository::defaultMatrixYearRange();
        $matrixRange = CadunicoMunicipioSnapshotRepository::normalizeMatrixYearRange(
            $request->integer('cadunico_matrix_from') ?: null,
            $request->integer('cadunico_matrix_to') ?: null,
        );
        $cadunicoYearlyMatrix = $this->snapshots->yearlyMatrix($matrixRange['from'], $matrixRange['to']);

        $filterCityId = $request->integer('city_id') ?: null;
        $filterCity = $filterCityId !== null ? $cities->firstWhere('id', $filterCityId) : null;
        $cadunicoStored = [];
        if ($filterCity instanceof City) {
            $cadunicoStored = $this->snapshots->listForCity($filterCity)
                ->map(static fn (CadunicoMunicipioSnapshot $row): array => [
                    'ano' => (int) $row->ano_referencia,
                    'pop_escolar' => $row->totalCriancasEscolaridade(),
                    'pessoas' => (int) $row->pessoas_cadastradas,
                    'familias' => (int) $row->familias_cadastradas,
                    'criancas_4_5' => (int) $row->criancas_4_5,
                    'criancas_6_10' => (int) $row->criancas_6_10,
                    'criancas_11_14' => (int) $row->criancas_11_14,
                    'criancas_15_17' => (int) $row->criancas_15_17,
                    'fonte' => $row->fonte,
                    'imported_at' => $row->imported_at?->format('d/m/Y H:i'),
                ])
                ->all();
        }

        $ibgeList = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge !== null) {
                $ibgeList[] = $ibge;
            }
        }
        $ibgeList = array_values(array_unique($ibgeList));

        $snapshotRows = CadunicoMunicipioSnapshot::query()
            ->forIbges($ibgeList)
            ->get(['ibge_municipio', 'ano_referencia', 'imported_at']);

        $municipiosComDados = $snapshotRows->pluck('ibge_municipio')->unique()->count();
        $latestImport = $snapshotRows->max('imported_at');

        $territorioRows = CadunicoTerritorioSnapshot::query()
            ->when($ibgeList !== [], fn ($q) => $q->whereIn('ibge_municipio', $ibgeList))
            ->where('ano_referencia', $refYear)
            ->get(['ibge_municipio', 'territorio_codigo', 'imported_at']);
        $territorioMunicipiosComDados = $territorioRows->pluck('ibge_municipio')->unique()->count();
        $territorioRegistos = $territorioRows->count();
        $territorioLatestImport = $territorioRows->max('imported_at');

        $apiTemplate = trim((string) config('ieducar.cadunico.open_data.api_url_template', ''));
        $ckanId = trim((string) config('ieducar.cadunico.open_data.resource_id', ''));
        $nacionalUrl = trim((string) config('ieducar.cadunico.auto_sync.nacional_csv_url_template', ''));
        $scheduleEnabled = filter_var(config('ieducar.cadunico.auto_sync.schedule.enabled', true), FILTER_VALIDATE_BOOL);
        $territorioScheduleEnabled = filter_var(config('ieducar.cadunico.territorio.schedule.enabled', true), FILTER_VALIDATE_BOOL);
        $territorioScheduleTime = trim((string) config('ieducar.cadunico.territorio.schedule.time', '04:30')) ?: '04:30';
        $autoYears = \App\Services\Cadunico\CadunicoAutoSyncService::yearsToSync();
        $misocialProbe = $this->misocial->probe();
        $ckanDiscovered = $this->ckanDiscovery->discover();

        return view('admin.cadunico-sync.index', [
            'cities' => $cities,
            'cityCount' => $cities->count(),
            'defaultYear' => $refYear,
            'yearOptions' => range($maxYear, max(2000, $maxYear - 8)),
            'storageRoot' => CadunicoStoragePaths::storageRoot(),
            'storageFiles' => CadunicoStoragePaths::listStorageCsvFiles(),
            'territorioStorageFiles' => CadunicoStoragePaths::listTerritorioCsvFiles(),
            'territorioRoot' => CadunicoStoragePaths::territorioRoot(),
            'municipiosComDados' => $municipiosComDados,
            'territorioMunicipiosComDados' => $territorioMunicipiosComDados,
            'territorioRegistos' => $territorioRegistos,
            'territorioLatestImport' => $territorioLatestImport,
            'territorioRefYear' => $refYear,
            'municipiosIbge' => count($ibgeList),
            'snapshotsTotal' => $snapshotRows->count(),
            'latestImport' => $latestImport,
            'cadunicoYearlyMatrix' => $cadunicoYearlyMatrix,
            'cadunicoMatrixFrom' => $matrixRange['from'],
            'cadunicoMatrixTo' => $matrixRange['to'],
            'filterCity' => $filterCity,
            'cadunicoStored' => $cadunicoStored,
            'apiConfigured' => $apiTemplate !== '' && str_contains($apiTemplate, '{ibge}'),
            'ckanConfigured' => $ckanId !== '' || $ckanDiscovered !== null,
            'ckanDiscovered' => $ckanDiscovered,
            'misocialEnabled' => CadunicoSagiMisocialClient::enabled(),
            'misocialProbe' => $misocialProbe,
            'misocialBaseUrl' => CadunicoSagiMisocialClient::baseUrl(),
            'cacheDir' => CadunicoStoragePaths::apiCacheDir(),
            'nacionalUrlConfigured' => $nacionalUrl !== '',
            'nacionalUrlTemplate' => $nacionalUrl,
            'scheduleEnabled' => $scheduleEnabled,
            'territorioScheduleEnabled' => $territorioScheduleEnabled,
            'territorioScheduleTime' => $territorioScheduleTime,
            'autoSyncYears' => $autoYears,
        ]);
    }

    public function run(CadunicoSyncRunRequest $request): RedirectResponse
    {
        $validated = $request->validatedPayload();
        $action = $request->action();
        $ano = $request->year();

        if (in_array($action, ['import_city_year', 'upload_territorio', 'sync_territorio_flow_city', 'sync_territorio_city'], true) && empty($validated['city_id'])) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->withInput()
                ->with('cadunico_sync_error', __('Selecione um município.'));
        }

        if ($action === 'upload_territorio') {
            return $this->handleUploadTerritorio($request, $ano, $validated);
        }

        if ($action === 'import_all_cities_year') {
            return $this->dispatchAllCities($ano);
        }

        if (in_array($action, ['sync_territorio_flow_city', 'sync_territorio_city', 'sync_territorio_all'], true)) {
            return $this->dispatchTerritorioSync($action, $ano, isset($validated['city_id']) ? (int) $validated['city_id'] : null);
        }

        if ($action === 'auto_sync') {
            $allYears = $request->boolean('all_configured_years', true);
            $task = $this->syncQueue->dispatch(
                AdminSyncDomain::Cadastro,
                'auto_sync',
                $allYears
                    ? __('CadÚnico — sincronização automática (anos :anos)', ['anos' => implode(', ', \App\Services\Cadunico\CadunicoAutoSyncService::yearsToSync())])
                    : __('CadÚnico — sincronização automática (:ano)', ['ano' => (string) $ano]),
                [
                    'ano' => $ano,
                    'all_years' => $allYears,
                    'fill_gaps' => true,
                ],
                null,
            );

            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('admin_sync_queued', [
                    'task_id' => $task->id,
                    'message' => AdminSyncQueueService::flashQueuedMessage($task),
                ]);
        }

        if ($action === 'upload_cecad') {
            return $this->handleUploadCecad($request, $ano, $validated);
        }

        $payload = ['ano' => $ano];
        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;
        if ($cityId !== null) {
            $payload['city_id'] = $cityId;
        }

        $taskKey = match ($action) {
            'import_city_year' => 'import_city_year',
            'import_storage_year' => 'import_storage_year',
            'import_csv' => 'import_csv',
            default => $action,
        };

        $label = match ($action) {
            'import_city_year' => __('CadÚnico — sincronizar município/ano'),
            'import_storage_year' => __('CadÚnico — CSV em storage (ano :ano)', ['ano' => (string) $ano]),
            'import_csv' => __('CadÚnico — upload CSV'),
            default => __('CadÚnico'),
        };

        if ($action === 'import_csv') {
            $stored = $this->storeUploadedCsv($request, $ano, $cityId);
            if ($stored === null) {
                return redirect()
                    ->route('admin.cadunico-sync.index')
                    ->with('cadunico_sync_error', __('Ficheiro CSV inválido.'));
            }
            $payload['csv_path'] = $stored['path'];
        }

        $city = $cityId !== null ? City::query()->find($cityId) : null;
        if ($city !== null) {
            $label .= ' — '.$city->name;
        }
        $label .= ' ('.$ano.')';

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Cadastro,
            $taskKey,
            $label,
            $payload,
            $cityId,
        );

        return redirect()
            ->route('admin.cadunico-sync.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function handleUploadCecad(Request $request, int $ano, array $validated): RedirectResponse
    {
        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;
        $city = $cityId !== null ? City::query()->find($cityId) : null;

        $stored = $this->storeUploadedCsv($request, $ano, $cityId);
        if ($stored === null) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('cadunico_sync_error', __('Ficheiro CSV inválido ou em falta.'));
        }

        $message = __('Ficheiro guardado em storage: :name', ['name' => $stored['filename']]);

        if (! $request->boolean('auto_import')) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('cadunico_upload_ok', $message);
        }

        if ($city !== null) {
            $task = $this->syncQueue->dispatch(
                AdminSyncDomain::Cadastro,
                'import_city_year',
                __('CadÚnico — importar após upload (:city)', ['city' => $city->name]).' ('.$ano.')',
                ['city_id' => $city->id, 'ano' => $ano],
                $city->id,
            );
        } else {
            $task = $this->syncQueue->dispatch(
                AdminSyncDomain::Cadastro,
                'import_storage_year',
                __('CadÚnico — importar CSV nacional (:ano)', ['ano' => (string) $ano]),
                ['ano' => $ano],
                null,
            );
        }

        return redirect()
            ->route('admin.cadunico-sync.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => $message.' '.AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function handleUploadTerritorio(Request $request, int $ano, array $validated): RedirectResponse
    {
        $cityId = (int) $validated['city_id'];
        $city = City::query()->find($cityId);
        if ($city === null) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('cadunico_sync_error', __('Município inválido.'));
        }

        $stored = $this->storeTerritorioCsv($request, $ano, $city);
        if ($stored === null) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('cadunico_sync_error', __('Ficheiro CSV territorial inválido.'));
        }

        $result = $this->territorioImport->importFile($stored['path'], $ano, $city);

        return redirect()
            ->route('admin.cadunico-sync.index', ['city_id' => $city->id])
            ->with(
                ($result['success'] ?? false) ? 'cadunico_upload_ok' : 'cadunico_sync_error',
                $result['message'] ?? __('Importação territorial concluída.'),
            );
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    private function storeTerritorioCsv(Request $request, int $ano, City $city): ?array
    {
        $upload = $request->file('csv_file');
        if ($upload === null || ! $upload->isValid()) {
            return null;
        }

        $ext = strtolower((string) $upload->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return null;
        }

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return null;
        }

        $root = CadunicoStoragePaths::territorioRoot();
        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $filename = 'territorio_'.$ibge.'_'.$ano.'.'.$ext;
        $absolute = $root.'/'.$filename;
        $upload->move($root, $filename);

        return ['path' => $absolute, 'filename' => $filename];
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    private function storeUploadedCsv(Request $request, int $ano, ?int $cityId): ?array
    {
        $upload = $request->file('csv_file');
        if ($upload === null || ! $upload->isValid()) {
            return null;
        }

        $ext = strtolower((string) $upload->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return null;
        }

        $city = $cityId !== null ? City::query()->find($cityId) : null;

        return CadunicoCecadUpload::store($upload, $ano, $city);
    }

    private function dispatchTerritorioSync(string $action, int $ano, ?int $cityId): RedirectResponse
    {
        if ($action === 'sync_territorio_all') {
            $task = $this->syncQueue->dispatch(
                AdminSyncDomain::Cadastro,
                'sync_territorio_all',
                __('CadÚnico — mapa territorial IBGE (:ano)', ['ano' => (string) $ano]),
                ['ano' => $ano],
                null,
            );

            return redirect()
                ->route('admin.cadunico-sync.index')
                ->with('admin_sync_queued', [
                    'task_id' => $task->id,
                    'message' => AdminSyncQueueService::flashQueuedMessage($task),
                ]);
        }

        $city = City::query()->findOrFail((int) $cityId);
        $taskKey = $action === 'sync_territorio_flow_city'
            ? 'sync_territorio_flow_city'
            : 'sync_territorio_city';
        $label = $action === 'sync_territorio_flow_city'
            ? __('CadÚnico — fluxo mapa (:city, :ano)', ['city' => $city->name, 'ano' => (string) $ano])
            : __('CadÚnico — território IBGE (:city, :ano)', ['city' => $city->name, 'ano' => (string) $ano]);

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Cadastro,
            $taskKey,
            $label,
            ['city_id' => $city->id, 'ano' => $ano],
            $city->id,
        );

        return redirect()
            ->route('admin.cadunico-sync.index', ['city_id' => $city->id])
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    private function dispatchAllCities(int $ano): RedirectResponse
    {
        $cities = City::query()->forAnalytics()->get();
        $queued = 0;
        $skipped = 0;

        foreach ($cities as $city) {
            if (FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) === null) {
                $skipped++;

                continue;
            }

            $this->syncQueue->dispatch(
                AdminSyncDomain::Cadastro,
                'import_city_year',
                __('CadÚnico — :city (:ano)', ['city' => $city->name, 'ano' => (string) $ano]),
                ['city_id' => $city->id, 'ano' => $ano],
                $city->id,
            );
            $queued++;
        }

        $message = $queued > 0
            ? trans_choice(':count tarefa CadÚnico enfileirada.|:count tarefas CadÚnico enfileiradas.', $queued, ['count' => $queued])
            : __('Nenhuma tarefa enfileirada.');

        if ($skipped > 0) {
            $message .= ' '.trans_choice(':count sem IBGE ignorado.|:count sem IBGE ignorados.', $skipped, ['count' => $skipped]);
        }

        return redirect()
            ->route('admin.cadunico-sync.index')
            ->with('cadunico_bulk_queued', ['queued' => $queued, 'message' => $message]);
    }
}
