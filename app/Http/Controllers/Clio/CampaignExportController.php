<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Export\CampaignExcelExporter;
use App\Services\Clio\Export\CampaignInsightsPdfExporter;
use App\Services\Clio\Export\CampaignPdfExporter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignExportController extends Controller
{
    public function xlsx(ClioCampaign $campaign, CampaignExcelExporter $exporter): StreamedResponse
    {
        $this->authorize('export', $campaign);

        return $exporter->download($campaign);
    }

    /** @deprecated Use xlsx() — mantido como alias de compatibilidade. */
    public function csv(ClioCampaign $campaign, CampaignExcelExporter $exporter): StreamedResponse
    {
        return $this->xlsx($campaign, $exporter);
    }

    public function pdf(ClioCampaign $campaign, CampaignPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return $exporter->download($campaign);
    }

    public function pdfGestor(ClioCampaign $campaign, CampaignInsightsPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return $exporter->download($campaign);
    }
}
