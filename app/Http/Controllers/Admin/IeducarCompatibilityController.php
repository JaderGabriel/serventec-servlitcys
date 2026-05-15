<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\CityDataConnection;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IeducarCompatibilityController extends Controller
{
    public function __construct(
        private CityDataConnection $cityData,
        private FundebMunicipioReferenceRepository $fundebReferences,
        private FundebOpenDataImportService $fundebImport,
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
        $result = $this->fundebImport->importForCityYear(
            $city,
            (int) $validated['ano'],
            $request->boolean('use_nearest_year'),
        );

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $city->id,
                'fundeb_ano' => (int) ($result['imported_ano'] ?? $validated['ano']),
            ])
            ->with($result['success'] ? 'fundeb_import_success' : 'fundeb_import_error', $result['message']);
    }

    public function importFundebBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ano' => 'required|integer|min:2000|max:'.((int) date('Y') + 1),
            'use_nearest_year' => 'sometimes|boolean',
            'city_id' => 'nullable|integer|exists:cities,id',
        ]);

        $result = $this->fundebImport->importBulk(
            (int) $validated['ano'],
            $request->boolean('use_nearest_year'),
            isset($validated['city_id']) ? (int) $validated['city_id'] : null,
        );

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $validated['city_id'] ?? $request->input('city_id'),
                'fundeb_ano' => (int) $validated['ano'],
            ])
            ->with('fundeb_bulk_result', $result)
            ->with($result['success'] ? 'fundeb_import_success' : 'fundeb_import_error', $result['message']);
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

        $maxYears = max(1, (int) config('ieducar.fundeb.open_data.sync_max_years', 30));
        $cityCount = $cityIds !== null ? count($cityIds) : City::query()->count();
        $timeLimit = min(3600, max(600, 120 + (count($years) * max(1, $cityCount) * 2)));
        @set_time_limit($timeLimit);

        $result = $this->fundebImport->importBulkForYears(
            $years,
            $request->boolean('use_nearest_year'),
            $cityIds,
        );

        $syncForm = [
            'all_cities' => $request->boolean('all_cities'),
            'city_ids' => $cityIds ?? [],
            'ano_from' => (int) $validated['ano_from'],
            'ano_to' => (int) $validated['ano_to'],
        ];

        return redirect()
            ->route('admin.ieducar-compatibility.index', [
                'city_id' => $request->input('city_id'),
                'fundeb_ano' => $years[0] ?? FundebOpenDataImportService::suggestedImportYear(),
            ])
            ->with('fundeb_sync_form', $syncForm)
            ->with('fundeb_bulk_result', $result)
            ->with($result['success'] ? 'fundeb_import_success' : 'fundeb_import_error', $result['message']);
    }

    public function export(Request $request): JsonResponse
    {
        $cityId = (int) $request->input('city_id', 0);
        $city = City::query()->find($cityId);
        if ($city === null) {
            abort(Response::HTTP_NOT_FOUND, __('Cidade não encontrada.'));
        }

        $filters = $this->filtersFromRequest($request);

        try {
            $document = $this->cityData->run($city, function ($db) use ($city, $filters) {
                return IeducarCompatibilityProbe::exportDocument($db, $city, $filters);
            });
        } catch (\Throwable $e) {
            abort(Response::HTTP_BAD_GATEWAY, $e->getMessage());
        }

        $ibge = preg_replace('/\D+/', '', (string) ($city->ibge_municipio ?? '')) ?: 'city_'.$city->id;
        $filename = 'schema_probe_'.$ibge.'_'.now()->format('Y-m-d').'.json';

        return response()->json($document, Response::HTTP_OK, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
