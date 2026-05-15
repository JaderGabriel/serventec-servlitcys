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
        $fundebCoverage = $this->fundebImport->localCoverageForYear($fundebImportYear);

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
            'fundebSuggestedYear' => FundebOpenDataImportService::suggestedImportYear(),
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
