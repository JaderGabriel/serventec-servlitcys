<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsReportExport;
use App\Services\Analytics\AnalyticsReportExportService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsReportExportController extends Controller
{
    public function store(
        Request $request,
        AnalyticsReportExportService $exportService,
    ): JsonResponse|RedirectResponse {
        $request->validate([
            'city_id' => ['required', 'integer'],
        ]);

        $this->authorize('create', AnalyticsReportExport::class);

        $city = UserCityAccess::citiesQuery($request->user())
            ->whereKey($request->integer('city_id'))
            ->firstOrFail();

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected()) {
            $message = __('Seleccione o ano letivo e aplique os filtros antes de exportar o PDF.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['pdf' => $message]);
        }

        $result = $exportService->dispatch($request->user(), $city, $filters);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['message'],
                'export' => $this->exportPayload($result['export']),
            ]);
        }

        $redirectParams = array_merge(
            $filters->toQueryParamsWithCity((int) $city->id),
            ['tab' => 'municipality_health'],
        );

        return redirect()
            ->route('dashboard.analytics', $redirectParams)
            ->with('status', $result['message'])
            ->with('pdf_export_id', $result['export']->id);
    }

    public function status(Request $request, AnalyticsReportExport $export): JsonResponse
    {
        $this->authorize('download', $export);

        return response()->json($this->exportPayload($export));
    }

    public function download(AnalyticsReportExport $export): StreamedResponse
    {
        $this->authorize('download', $export);

        if (! $export->isDownloadable()) {
            abort(404, __('PDF ainda não disponível.'));
        }

        $disk = Storage::disk($export->file_disk);
        if (! $disk->exists((string) $export->file_path)) {
            abort(404, __('Ficheiro PDF não encontrado.'));
        }

        $cityName = $export->city?->name ?? 'municipio';
        $filename = 'serventec-'.str($cityName)->slug().'-'.($export->completed_at?->format('Y-m-d') ?? 'relatorio').'.pdf';

        return $disk->download((string) $export->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportPayload(AnalyticsReportExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'error_message' => $export->error_message,
            'page_count' => $export->page_count,
            'created_at' => $export->created_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'download_url' => $export->isDownloadable()
                ? route('dashboard.analytics.pdf.download', $export)
                : null,
        ];
    }
}
