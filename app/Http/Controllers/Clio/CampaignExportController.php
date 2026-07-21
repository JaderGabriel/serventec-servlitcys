<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Export\CampaignCsvExporter;
use App\Services\Clio\Export\CampaignPdfExporter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignExportController extends Controller
{
    public function csv(ClioCampaign $campaign, CampaignCsvExporter $exporter): StreamedResponse
    {
        $this->authorize('export', $campaign);

        return $exporter->download($campaign);
    }

    public function pdf(ClioCampaign $campaign, CampaignPdfExporter $exporter): Response
    {
        $this->authorize('export', $campaign);

        return $exporter->download($campaign);
    }
}
