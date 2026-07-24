<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

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
            'schools.artifacts',
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

        $inactiveSchoolIds = $campaign->schools
            ->filter(fn (ClioCampaignSchool $school): bool => CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status))
            ->pluck('id')
            ->all();

        $errors = $campaign->findings->where('severity', ClioCampaignFinding::SEVERITY_ERROR);
        $warnings = $campaign->findings->where('severity', ClioCampaignFinding::SEVERITY_WARNING);

        [$toCorrect, $toCorrectOther] = $this->partitionFindings($errors, $inactiveSchoolIds);
        [$toReview, $toReviewOther] = $this->partitionFindings($warnings, $inactiveSchoolIds);

        $toCorrect = $toCorrect
            ->sortBy(fn (ClioCampaignFinding $f): int => $f->school_id === null ? 1 : 0)
            ->take(40)
            ->values();
        $toReview = $toReview
            ->sortBy(fn (ClioCampaignFinding $f): int => $f->school_id === null ? 1 : 0)
            ->take(40)
            ->values();
        $toCorrectOther = $toCorrectOther
            ->sortBy(fn (ClioCampaignFinding $f): string => (string) ($f->school?->name ?? ''))
            ->values();
        $toReviewOther = $toReviewOther
            ->sortBy(fn (ClioCampaignFinding $f): string => (string) ($f->school?->name ?? ''))
            ->values();

        $pdfTables = $this->detailBuilder->build($campaign);
        $diagnosticoGeral = app(DiagnosticoGeralComposer::class)->compose($campaign);

        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.document', [
            'campaign' => $campaign,
            'coverage' => $coverage,
            'dashboard' => $dashboard,
            'counters' => $dashboard['counters'] ?? [],
            'inferences' => $campaign->inferences->keyBy('code'),
            'toCorrect' => $toCorrect,
            'toReview' => $toReview,
            'toCorrectOther' => $toCorrectOther,
            'toReviewOther' => $toReviewOther,
            'criticalFindings' => $toCorrect,
            'pdfTables' => $pdfTables,
            'diagnosticoGeral' => $diagnosticoGeral,
            'generated_at' => $generatedAt,
            'colors' => [
                'primary' => '#0f172a',
                'secondary' => '#1d4ed8',
                'primary_light' => '#e2e8f0',
            ],
        ])->setPaper('a4');

        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibge = preg_replace('/\D+/', '', (string) ($campaign->ibge_municipio ?? '')) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_%s_%s_%s.pdf', $citySlug, $ibge, $refDate);

        return $pdf->download($filename);
    }

    private function slugPart(string $value): string
    {
        $ascii = \Illuminate\Support\Str::ascii($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/i', '_', $ascii);
        $slug = trim($slug, '_');

        return mb_strtolower($slug);
    }

    /**
     * Mesmo escopo do Tempo de escolarização: escolas em atividade (+ itens da Rede).
     * Extinta/paralisada/reforma vão para o bloco final do PDF.
     *
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @param  list<int|string>  $inactiveSchoolIds
     * @return array{0: Collection<int, ClioCampaignFinding>, 1: Collection<int, ClioCampaignFinding>}
     */
    public function partitionFindings(Collection $findings, array $inactiveSchoolIds): array
    {
        $inactiveLookup = array_fill_keys(array_map('intval', $inactiveSchoolIds), true);

        $operational = $findings->filter(function (ClioCampaignFinding $finding) use ($inactiveLookup): bool {
            if ($finding->school_id === null) {
                return true;
            }

            return ! isset($inactiveLookup[(int) $finding->school_id]);
        })->values();

        $other = $findings->filter(function (ClioCampaignFinding $finding) use ($inactiveLookup): bool {
            return $finding->school_id !== null
                && isset($inactiveLookup[(int) $finding->school_id]);
        })->values();

        return [$operational, $other];
    }
}
