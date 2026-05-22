<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Support\Analytics\AnalyticsReportBibliography;
use App\Support\Dashboard\IeducarFilterState;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AnalyticsReportPdfService
{
    public function __construct(
        private AnalyticsFullReportAssembler $assembler,
    ) {}

    /**
     * @return array{path: string, disk: string, page_count: ?int}
     */
    public function generate(AnalyticsReportExport $export, City $city, IeducarFilterState $filters): array
    {
        AnalyticsReportBibliography::assignPublicId($export);

        $data = $this->assembler->assemble($city, $filters, $export);
        $data['export_id'] = $export->id;
        $data['public_id'] = $export->public_id;

        if (is_array($data['publication'] ?? null)) {
            $data['publication']['public_url'] = route('analytics.report.public', ['publicId' => $export->public_id]);
            $data['publication']['qr_data_uri'] = \App\Support\Analytics\AnalyticsReportQrCodeBuilder::forUrl(
                (string) $data['publication']['public_url']
            );
        }

        $pdf = Pdf::loadView('pdf.analytics-report.document', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isPhpEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $disk = (string) config('analytics.pdf_report.disk', 'local');
        $prefix = trim((string) config('analytics.pdf_report.path_prefix', 'analytics-reports'), '/');
        $slug = Str::slug($city->name.'-'.($filters->ano_letivo ?? 'relatorio'));
        $filename = $slug.'-'.now()->format('Ymd-His').'.pdf';
        $relative = $prefix.'/'.$export->id.'/'.$filename;

        Storage::disk($disk)->makeDirectory($prefix.'/'.$export->id);
        $binary = $pdf->output();
        Storage::disk($disk)->put($relative, $binary);

        $pageCount = null;
        try {
            $pageCount = $pdf->getDomPDF()->getCanvas()->get_page_count();
        } catch (\Throwable) {
            $pageCount = null;
        }

        return [
            'path' => $relative,
            'disk' => $disk,
            'page_count' => $pageCount,
        ];
    }
}
