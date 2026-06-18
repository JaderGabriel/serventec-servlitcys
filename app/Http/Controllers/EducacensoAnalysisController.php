<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\Educacenso\EducacensoAnalysisCache;
use App\Services\Educacenso\EducacensoStage1ConferenceService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EducacensoAnalysisController extends Controller
{
    public function store(Request $request, EducacensoStage1ConferenceService $service): RedirectResponse
    {
        if (! filter_var(config('educacenso.enabled', true), FILTER_VALIDATE_BOOL)) {
            return back()->with('educacenso_error', __('Módulo Educacenso desactivado.'));
        }

        $maxKb = max(1024, (int) config('educacenso.upload_max_mb', 64) * 1024);

        $validated = $request->validate([
            'city_id' => ['required', 'integer'],
            'educacenso_file' => ['required', 'file', 'mimes:txt,csv', 'max:'.$maxKb],
            'ano_letivo' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $city = UserCityAccess::citiesQuery($request->user())
            ->whereKey((int) $validated['city_id'])
            ->first();

        if (! $city instanceof City) {
            abort(403);
        }

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected() && filled($validated['ano_letivo'] ?? null)) {
            $filters = $filters->withAnoLetivoOverride((int) $validated['ano_letivo']);
        }

        if (! $filters->hasYearSelected()) {
            return back()->with('educacenso_error', __('Seleccione o ano letivo antes de analisar o arquivo Educacenso.'));
        }

        $upload = $request->file('educacenso_file');
        $dir = storage_path('app/educacenso/uploads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeName = 'educacenso_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.txt';
        $absolute = $dir.'/'.$safeName;
        $upload->move($dir, $safeName);

        $report = $service->analyze($city, $filters, $absolute, (string) $upload->getClientOriginalName());

        EducacensoAnalysisCache::put($request->user(), $city, $report);

        @unlink($absolute);

        return redirect()
            ->route('dashboard.analytics', [
                'city_id' => $city->getKey(),
                'ano_letivo' => $filters->ano_letivo,
                'tab' => 'work_done',
                'educacenso_analyzed' => 1,
            ])
            ->with('educacenso_success', __('Análise do arquivo Educacenso concluída.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $cityId = (int) $request->input('city_id');
        $city = UserCityAccess::citiesQuery($request->user())->whereKey($cityId)->first();
        if (! $city instanceof City) {
            abort(403);
        }
        $this->authorize('viewAnalytics', $city);

        EducacensoAnalysisCache::forget($request->user(), $city);

        return back()->with('educacenso_success', __('Análise Educacenso removida do painel.'));
    }

    public function exportFindings(Request $request): StreamedResponse|Response
    {
        $cityId = (int) $request->query('city_id');
        $city = UserCityAccess::citiesQuery($request->user())->whereKey($cityId)->first();
        if (! $city instanceof City) {
            abort(403);
        }
        $this->authorize('viewAnalytics', $city);

        $report = EducacensoAnalysisCache::get($request->user(), $city);
        if (! is_array($report)) {
            abort(404);
        }

        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $filename = 'educacenso-achados-'.$city->getKey().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($findings): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['codigo', 'severidade', 'linha', 'registro', 'inep_escola', 'escola', 'mensagem', 'sugestao'], ';');
            foreach ($findings as $f) {
                fputcsv($out, [
                    $f['code'] ?? '',
                    $f['severity'] ?? '',
                    $f['line'] ?? '',
                    $f['record_type'] ?? '',
                    $f['school_inep'] ?? '',
                    $f['school_name'] ?? '',
                    $f['message'] ?? '',
                    $f['suggestion'] ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
