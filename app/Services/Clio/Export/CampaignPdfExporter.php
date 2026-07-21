<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Parse\CampaignParseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class CampaignPdfExporter
{
    public function __construct(
        private CampaignParseService $parser,
    ) {}

    public function download(ClioCampaign $campaign): Response
    {
        $campaign->load(['inferences', 'findings' => fn ($q) => $q->where('severity', 'error')->limit(40)]);
        $coverage = $this->parser->coverage($campaign);
        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.document', [
            'campaign' => $campaign,
            'coverage' => $coverage,
            'inferences' => $campaign->inferences->keyBy('code'),
            'criticalFindings' => $campaign->findings,
            'generated_at' => $generatedAt,
            'colors' => [
                'navy' => '#0f2744',
                'accent' => '#1d4ed8',
            ],
        ])->setPaper('a4');

        $filename = sprintf(
            'clio_%s_%d.pdf',
            preg_replace('/[^a-z0-9_-]+/i', '_', (string) $campaign->ibge_municipio) ?: 'mun',
            $campaign->year
        );

        return $pdf->download($filename);
    }
}
