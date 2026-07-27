<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Export\CampaignExcelExporter;
use App\Services\Clio\Export\CampaignFinalPdfExporter;
use App\Services\Clio\Export\CampaignInsightsPdfExporter;
use App\Services\Clio\Export\CampaignMapaColetaPdfExporter;
use App\Services\Clio\Export\CampaignPdfExporter;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignExportController extends Controller
{
    public function xlsx(ClioCampaign $campaign, CampaignExcelExporter $exporter): StreamedResponse
    {
        $this->authorize('export', $campaign);

        return PulseOperationRecorder::measure(
            'clio:export:xlsx',
            fn (): StreamedResponse => $exporter->download($campaign),
        );
    }

    /** @deprecated Use xlsx() — mantido como alias de compatibilidade. */
    public function csv(ClioCampaign $campaign, CampaignExcelExporter $exporter): StreamedResponse
    {
        return $this->xlsx($campaign, $exporter);
    }

    public function pdf(ClioCampaign $campaign, CampaignPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return PulseOperationRecorder::measure(
            'clio:export:pdf',
            fn (): Response => $exporter->download($campaign),
        );
    }

    public function pdfGestor(ClioCampaign $campaign, CampaignInsightsPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return PulseOperationRecorder::measure(
            'clio:export:pdf-gestor',
            fn (): Response => $exporter->download($campaign),
        );
    }

    public function pdfFinal(ClioCampaign $campaign, CampaignFinalPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return PulseOperationRecorder::measure(
            'clio:export:pdf-final',
            fn (): Response => $exporter->download($campaign),
        );
    }

    public function pdfMapaColeta(ClioCampaign $campaign, CampaignMapaColetaPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return PulseOperationRecorder::measure(
            'clio:export:pdf-mapa-coleta',
            fn (): Response => $exporter->download($campaign),
        );
    }
}
