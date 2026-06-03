<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Support\Cadunico\CadunicoCecadUpload;
use App\Support\Cadunico\CadunicoStoragePaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CadunicoSyncController extends Controller
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
        private CadunicoOpenDataImportService $import,
        private CadunicoMunicipioSnapshotRepository $snapshots,
    ) {}

    public function index(Request $request): View
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
            ->when($ibgeList !== [], fn ($q) => $q->whereIn('ibge_municipio', $ibgeList))
            ->get(['ibge_municipio', 'ano_referencia', 'imported_at']);

        $municipiosComDados = $snapshotRows->pluck('ibge_municipio')->unique()->count();
        $latestImport = $snapshotRows->max('imported_at');

        $apiTemplate = trim((string) config('ieducar.cadunico.open_data.api_url_template', ''));
        $ckanId = trim((string) config('ieducar.cadunico.open_data.resource_id', ''));
        $nacionalUrl = trim((string) config('ieducar.cadunico.auto_sync.nacional_csv_url_template', ''));
        $scheduleEnabled = filter_var(config('ieducar.cadunico.auto_sync.schedule.enabled', true), FILTER_VALIDATE_BOOL);
        $autoYears = \App\Services\Cadunico\CadunicoAutoSyncService::yearsToSync();

        return view('admin.cadunico-sync.index', [
            'cities' => $cities,
            'cityCount' => $cities->count(),
            'defaultYear' => $refYear,
            'yearOptions' => range($maxYear, max(2000, $maxYear - 8)),
            'storageRoot' => CadunicoStoragePaths::storageRoot(),
            'storageFiles' => CadunicoStoragePaths::listStorageCsvFiles(),
            'municipiosComDados' => $municipiosComDados,
            'municipiosIbge' => count($ibgeList),
            'snapshotsTotal' => $snapshotRows->count(),
            'latestImport' => $latestImport,
            'cadunicoYearlyMatrix' => $cadunicoYearlyMatrix,
            'cadunicoMatrixFrom' => $matrixRange['from'],
            'cadunicoMatrixTo' => $matrixRange['to'],
            'filterCity' => $filterCity,
            'cadunicoStored' => $cadunicoStored,
            'apiConfigured' => $apiTemplate !== '' && str_contains($apiTemplate, '{ibge}'),
            'ckanConfigured' => $ckanId !== '',
            'cacheDir' => CadunicoStoragePaths::apiCacheDir(),
            'nacionalUrlConfigured' => $nacionalUrl !== '',
            'nacionalUrlTemplate' => $nacionalUrl,
            'scheduleEnabled' => $scheduleEnabled,
            'autoSyncYears' => $autoYears,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:auto_sync,import_city_year,import_storage_year,import_csv,upload_cecad,import_all_cities_year',
            'city_id' => 'nullable|integer|exists:cities,id',
            'ano' => 'nullable|integer|min:2000|max:'.((int) date('Y') + 1),
            'csv_file' => 'required_if:action,import_csv,upload_cecad|file|max:20480',
            'auto_import' => 'sometimes|boolean',
        ]);

        $action = $validated['action'];
        $ano = isset($validated['ano']) ? (int) $validated['ano'] : CadunicoOpenDataImportService::suggestedImportYear();

        if ($action === 'import_city_year' && empty($validated['city_id'])) {
            return redirect()
                ->route('admin.cadunico-sync.index')
                ->withInput()
                ->with('cadunico_sync_error', __('Selecione um município.'));
        }

        if ($action === 'import_all_cities_year') {
            return $this->dispatchAllCities($ano);
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
