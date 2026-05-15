<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\CityDataConnection;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IeducarCompatibilityController extends Controller
{
    public function __construct(
        private CityDataConnection $cityData,
        private FundebMunicipioReferenceRepository $fundebReferences,
        private FundebOpenDataImportService $fundebImport,
        private AdminSyncQueueService $syncQueue,
    ) {}

    public function index(Request $request): View
    {
        $cities = City::query()->orderBy('name')->get();
        $cityId = (int) $request->input('city_id', 0);
        $city = $cityId > 0 ? $cities->firstWhere('id', $cityId) : $cities->first();

        $report = null;
        $error = null;
        $filters = null;
        $fundebStored = [];
        $fundebResolved = null;
        $fundebImportYear = (int) $request->input('fundeb_ano', FundebOpenDataImportService::suggestedImportYear());

        if ($city !== null) {
            $filters = $this->filtersFromRequest($request);
            $fundebStored = $this->fundebReferences->listForCity($city)
                ->map(static fn ($r) => [
                    'ano' => (int) $r->ano,
                    'vaaf' => (float) $r->vaaf,
                    'vaat' => $r->vaat !== null ? (float) $r->vaat : null,
                    'complementacao_vaar' => $r->complementacao_vaar !== null ? (float) $r->complementacao_vaar : null,
                    'fonte' => $r->fonte,
                    'imported_at' => $r->imported_at?->format('d/m/Y H:i'),
                ])
                ->all();

            $resolveFilters = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
                ? $filters
                : new IeducarFilterState(
                    ano_letivo: (string) max($fundebImportYear, 2000),
                    escola_id: null,
                    curso_id: null,
                    turno_id: null,
                );
            $fundebResolved = FundebMunicipalReferenceResolver::resolve($city, $resolveFilters);

            try {
                $report = $this->runReport($city, $filters);
                if (is_array($report['routines'] ?? null)) {
                    $report['routines'] = $this->enrichRoutineRows($report['routines']);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $fundebApiDiagnostics = $this->fundebImport->apiDiagnostics();
        $fundebSyncYears = $this->fundebImport->resolveSyncYears();
        $fundebConfiguredYears = FundebOpenDataImportService::configuredSyncYears();
        $fundebSyncFrom = (int) config('ieducar.fundeb.open_data.sync_from_year', 2020);
        $fundebSyncTo = (int) config('ieducar.fundeb.open_data.sync_to_year', 0);
        if ($fundebSyncTo <= 0) {
            $fundebSyncTo = (int) date('Y') - 1;
        }
        $fundebCoverage = $this->fundebImport->localCoverageForYears($fundebSyncYears);
        $fundebNationalFloor = (bool) config('ieducar.fundeb.open_data.national_floor.enabled', false);
        $fundebCityChoices = $cities->map(static function (City $c) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($c->ibge_municipio);

            return [
                'id' => (int) $c->id,
                'name' => $c->name,
                'uf' => $c->uf,
                'ibge' => $ibge,
                'has_ibge' => $ibge !== null,
            ];
        })->all();
        $syncForm = session('fundeb_sync_form', []);
        $fundebSelectedCityIds = is_array($syncForm['city_ids'] ?? null)
            ? array_map('intval', $syncForm['city_ids'])
            : array_values(array_filter(array_map(
                static fn (array $row): ?int => ($row['has_ibge'] ?? false) ? (int) $row['id'] : null,
                $fundebCityChoices,
            )));
        $fundebSelectAllCities = ! array_key_exists('all_cities', $syncForm)
            ? true
            : (bool) $syncForm['all_cities'];

        return view('admin.ieducar-compatibility.index', [
            'cities' => $cities,
            'city' => $city,
            'report' => $report,
            'error' => $error,
            'filters' => $filters,
            'fundebStored' => $fundebStored,
            'fundebResolved' => $fundebResolved,
            'fundebImportYear' => $fundebImportYear,
            'fundebApiDiagnostics' => $fundebApiDiagnostics,
            'fundebCoverage' => $fundebCoverage,
            'fundebSyncYears' => $fundebSyncYears,
            'fundebConfiguredYears' => $fundebConfiguredYears,
            'fundebSyncFrom' => $fundebSyncFrom,
            'fundebSyncTo' => $fundebSyncTo,
            'fundebNationalFloor' => $fundebNationalFloor,
            'fundebSuggestedYear' => FundebOpenDataImportService::suggestedImportYear(),
            'fundebCityChoices' => $fundebCityChoices,
            'fundebSelectedCityIds' => $fundebSelectedCityIds,
            'fundebSelectAllCities' => $fundebSelectAllCities,
        ]);
    }

    public function importFundeb(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'ano' => 'required|integer|min:2000|max:'.((int) date('Y') + 1),
            'use_nearest_year' => 'sometimes|boolean',
        ]);

        $city = City::query()->findOrFail((int) $validated['city_id']);
        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Fundeb,
            'import_city_year',
            __('FUNDEB — :city, ano :ano', ['city' => $city->name, 'ano' => (string) $validated['ano']]),
            [
                'city_id' => $city->id,
                'ano' => (int) $validated['ano'],
                'use_nearest_year' => $request->boolean('use_nearest_year'),
            ],
            $city->id,
        );

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $city->id,
                'fundeb_ano' => (int) $validated['ano'],
            ])
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    public function importFundebBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ano' => 'required|integer|min:2000|max:'.((int) date('Y') + 1),
            'use_nearest_year' => 'sometimes|boolean',
            'city_id' => 'nullable|integer|exists:cities,id',
        ]);

        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;
        $city = $cityId !== null ? City::query()->find($cityId) : null;
        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Fundeb,
            'import_bulk_year',
            $city !== null
                ? __('FUNDEB em lote — :city, ano :ano', ['city' => $city->name, 'ano' => (string) $validated['ano']])
                : __('FUNDEB em lote — todas as cidades, ano :ano', ['ano' => (string) $validated['ano']]),
            [
                'ano' => (int) $validated['ano'],
                'use_nearest_year' => $request->boolean('use_nearest_year'),
                'city_id' => $cityId,
            ],
            $cityId,
        );

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $validated['city_id'] ?? $request->input('city_id'),
                'fundeb_ano' => (int) $validated['ano'],
            ])
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    public function syncFundebAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'use_nearest_year' => 'sometimes|boolean',
            'ano_from' => 'required|integer|min:2000|max:'.((int) date('Y') + 1),
            'ano_to' => 'required|integer|min:2000|max:'.((int) date('Y') + 1),
            'include_cached_years' => 'sometimes|boolean',
            'include_database_years' => 'sometimes|boolean',
            'all_cities' => 'sometimes|boolean',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'integer|exists:cities,id',
            'city_id' => 'nullable|integer|exists:cities,id',
        ]);

        if ((int) $validated['ano_from'] > (int) $validated['ano_to']) {
            return redirect()
                ->route('admin.ieducar-compatibility.index', ['city_id' => $request->input('city_id')])
                ->with('fundeb_import_error', __('O ano inicial não pode ser maior que o ano final.'));
        }

        $cityIds = null;
        if (! $request->boolean('all_cities')) {
            $cityIds = array_values(array_unique(array_map('intval', $validated['city_ids'] ?? [])));
            if ($cityIds === []) {
                return redirect()
                    ->route('admin.ieducar-compatibility.index', ['city_id' => $request->input('city_id')])
                    ->with('fundeb_import_error', __('Selecione ao menos um município ou marque «Todas as cidades».'));
            }
        }

        $years = $this->fundebImport->resolveSyncYears(
            (int) $validated['ano_from'],
            (int) $validated['ano_to'],
            $request->boolean('include_cached_years', true),
            $request->boolean('include_database_years', true),
        );

        if ($years === []) {
            return redirect()
                ->route('admin.ieducar-compatibility.index', ['city_id' => $request->input('city_id')])
                ->with('fundeb_import_error', __('Nenhum ano elegível para sincronização. Ajuste o intervalo ou IEDUCAR_FUNDEB_SYNC_YEARS.'));
        }

        $syncForm = [
            'all_cities' => $request->boolean('all_cities'),
            'city_ids' => $cityIds ?? [],
            'ano_from' => (int) $validated['ano_from'],
            'ano_to' => (int) $validated['ano_to'],
        ];

        $cityIdForLabel = $request->input('city_id');
        $cityLabel = $cityIds !== null && count($cityIds) === 1
            ? (City::query()->find((int) $cityIds[0])?->name ?? __('1 município'))
            : ($cityIds !== null ? __(':n municípios', ['n' => (string) count($cityIds)]) : __('Todas as cidades'));

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Fundeb,
            'sync_all_years',
            __('FUNDEB — sincronização :cities, anos :from–:to', [
                'cities' => $cityLabel,
                'from' => (string) $validated['ano_from'],
                'to' => (string) $validated['ano_to'],
            ]),
            [
                'years' => $years,
                'ano_from' => (int) $validated['ano_from'],
                'ano_to' => (int) $validated['ano_to'],
                'use_nearest_year' => $request->boolean('use_nearest_year'),
                'include_cached_years' => $request->boolean('include_cached_years', true),
                'include_database_years' => $request->boolean('include_database_years', true),
                'city_ids' => $cityIds,
            ],
            $cityIdForLabel !== null && $cityIdForLabel !== '' ? (int) $cityIdForLabel : null,
        );

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $request->input('city_id'),
                'fundeb_ano' => $years[0] ?? FundebOpenDataImportService::suggestedImportYear(),
            ])
            ->with('fundeb_sync_form', $syncForm)
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    public function export(Request $request): RedirectResponse
    {
        $cityId = (int) $request->input('city_id', 0);
        $city = City::query()->find($cityId);
        if ($city === null) {
            return redirect()
                ->route('admin.ieducar-compatibility.index')
                ->with('fundeb_import_error', __('Cidade não encontrada.'));
        }

        $filters = $this->filtersFromRequest($request);

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Ieducar,
            'schema_probe',
            __('Export schema probe — :city', ['city' => $city->name]),
            [
                'city_id' => $city->id,
                'ano_letivo' => $filters->ano_letivo,
                'escola_id' => $filters->escola_id,
                'curso_id' => $filters->curso_id,
                'turno_id' => $filters->turno_id,
            ],
            $city->id,
        );

        return redirect()
            ->route('admin.sync-queue.show', $task)
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runReport(City $city, IeducarFilterState $filters): array
    {
        return $this->cityData->run($city, function ($db) use ($city, $filters) {
            return IeducarCompatibilityProbe::report($db, $city, $filters);
        });
    }

    private function filtersFromRequest(Request $request): IeducarFilterState
    {
        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected()) {
            return new IeducarFilterState(
                ano_letivo: 'all',
                escola_id: $filters->escola_id,
                curso_id: $filters->curso_id,
                turno_id: $filters->turno_id,
            );
        }

        return $filters;
    }

    /**
     * @param  list<array<string, mixed>>  $routines
     * @return list<array<string, mixed>>
     */
    private function enrichRoutineRows(array $routines): array
    {
        foreach ($routines as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $st = (string) ($row['status'] ?? $row['availability'] ?? 'unavailable');
            if ((bool) ($row['has_issue'] ?? false) && $st === 'ok') {
                $st = 'warning';
                $routines[$i]['status'] = $st;
            }
            $routines[$i]['ui_status_class'] = match ($st) {
                'danger' => 'text-red-700 dark:text-red-300',
                'warning' => 'text-amber-700 dark:text-amber-300',
                'ok' => 'text-emerald-700 dark:text-emerald-300',
                'no_data' => 'text-sky-700 dark:text-sky-300',
                default => 'text-gray-500 dark:text-gray-400',
            };
            if (! filled($row['status_label'] ?? null)) {
                $routines[$i]['status_label'] = match ($st) {
                    'danger', 'warning' => __('Com pendência'),
                    'ok' => __('Sem pendência'),
                    'no_data' => __('Sem dados para analisar'),
                    default => __('Indisponível'),
                };
            }
        }

        return $routines;
    }
}
