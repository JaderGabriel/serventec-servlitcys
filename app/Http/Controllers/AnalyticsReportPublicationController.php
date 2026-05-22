<?php

namespace App\Http\Controllers;

use App\Enums\AnalyticsReportExportStatus;
use App\Models\AnalyticsReportExport;
use App\Support\Analytics\AnalyticsReportBibliography;
use App\Support\Analytics\AnalyticsReportQrCodeBuilder;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Página pública de verificação do relatório (QR code no PDF).
 */
class AnalyticsReportPublicationController extends Controller
{
    public function show(Request $request, string $publicId): View
    {
        $export = AnalyticsReportExport::query()
            ->with(['city', 'user'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        $user = $request->user();
        if ($user !== null) {
            $this->authorize('download', $export);
        }

        $city = $export->city;
        $filters = IeducarFilterState::fromStoredParams(is_array($export->filters) ? $export->filters : []);
        $bibliography = AnalyticsReportBibliography::forExport($export, $city);
        $publicUrl = route('analytics.report.public', ['publicId' => $publicId]);
        $qrDataUri = AnalyticsReportQrCodeBuilder::forUrl($publicUrl, 220);

        return view('analytics.report-publication', [
            'export' => $export,
            'city' => $city,
            'filters' => $filters,
            'bibliography' => $bibliography,
            'public_url' => $publicUrl,
            'qr_data_uri' => $qrDataUri,
            'download_url' => $export->isDownloadable()
                ? route('analytics.report.public.download', ['publicId' => $publicId])
                : null,
            'auth_download_url' => ($user !== null && $export->isDownloadable())
                ? route('dashboard.analytics.pdf.download', $export)
                : null,
            'status_label' => $export->statusEnum()->value,
            'is_ready' => $export->status === AnalyticsReportExportStatus::Completed->value,
            'analytics_url' => $city !== null
                ? route('dashboard.analytics', $filters->toQueryParamsWithCity((int) $city->id))
                : route('dashboard.analytics'),
        ]);
    }

    public function download(string $publicId): StreamedResponse
    {
        $export = AnalyticsReportExport::query()
            ->with('city')
            ->where('public_id', $publicId)
            ->firstOrFail();

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
}
