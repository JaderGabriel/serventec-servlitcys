<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class CampaignPdfExporter
{
    public function __construct(
        private CampaignParseService $parser,
        private CampaignAnalysisPresenter $presenter,
        private CampaignPdfDetailBuilder $detailBuilder,
    ) {}

    public function download(ClioCampaign $campaign): Response
    {
        $campaign->load([
            'schools',
            'artifacts.school',
            'inferences',
            'findings.school',
        ]);
        $coverage = $this->parser->coverage($campaign);
        $dashboard = $this->presenter->present(
            $campaign,
            $coverage,
            $campaign->inferences->keyBy('code'),
            $campaign->findings,
        );

        $toCorrect = $campaign->findings
            ->where('severity', ClioCampaignFinding::SEVERITY_ERROR)
            ->sortBy(fn (ClioCampaignFinding $f): int => $f->school_id === null ? 1 : 0)
            ->take(40)
            ->values();
        // Escolas primeiro; «Rede» (sem escola) por último.
        $toReview = $campaign->findings
            ->where('severity', ClioCampaignFinding::SEVERITY_WARNING)
            ->sortBy(fn (ClioCampaignFinding $f): int => $f->school_id === null ? 1 : 0)
            ->values()
            ->take(40);

        $pdfTables = $this->detailBuilder->build($campaign);

        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.document', [
            'campaign' => $campaign,
            'coverage' => $coverage,
            'dashboard' => $dashboard,
            'counters' => $dashboard['counters'] ?? [],
            'inferences' => $campaign->inferences->keyBy('code'),
            'toCorrect' => $toCorrect,
            'toReview' => $toReview,
            'criticalFindings' => $toCorrect,
            'pdfTables' => $pdfTables,
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
