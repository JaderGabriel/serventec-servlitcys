<?php

namespace App\Services\Clio\Export;

use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioEnrollmentStage;
use App\Models\Bi\BiClioInsight;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignSchoolTimeComposer;
use App\Services\Clio\Bi\ClioBiDashboardComposer;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Horizonte\HorizonteMunicipioEnrollmentSeriesService;
use App\Support\Analytics\AnalyticsReportChartSvg;
use App\Support\Dashboard\ChartPayload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * PDF gerencial (estilo BI): indicadores, gráficos e diagnóstico geral por escola.
 */
final class CampaignInsightsPdfExporter
{
    public function __construct(
        private readonly CampaignParseService $parser,
        private readonly ClioBiDashboardComposer $dashboard,
        private readonly CampaignSchoolTimeComposer $schoolTime,
        private readonly HorizonteMunicipioEnrollmentSeriesService $enrollmentSeries,
    ) {}

    public function download(ClioCampaign $campaign): Response
    {
        $campaign->load(['artifacts', 'inferences', 'city', 'schools.artifacts', 'findings']);

        $bi = BiClioCampaign::query()->where('campaign_id', $campaign->id)->first();
        $coverage = $this->parser->coverage($campaign);

        $inferences = [];
        foreach ($campaign->inferences as $inf) {
            if (is_array($inf->payload)) {
                $inferences[(string) $inf->code] = $inf->payload;
            }
        }

        $charts = $bi instanceof BiClioCampaign
            ? $this->dashboard->charts((int) $campaign->id, $bi, $inferences)
            : [];

        $chartSvgs = $this->chartSvgs($charts, $bi);

        $insights = BiClioInsight::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(static fn (BiClioInsight $row): bool => (string) ($row->severity ?? '') !== 'error')
            ->values();

        $etapas = BiClioEnrollmentStage::query()
            ->where('campaign_id', $campaign->id)
            ->whereNull('inep')
            ->orderByDesc('qt_alunos')
            ->limit(15)
            ->get();

        $ibge = (string) ($campaign->ibge_municipio ?: $campaign->city?->ibge_municipio ?? '');
        $series = $this->enrollmentSeries->forIbge($ibge, 5, 'municipal', allowConsultoriaActive: true);
        $schoolTime = $this->schoolTime->compose($campaign);
        $diagnosticoGeral = app(DiagnosticoGeralComposer::class)->compose($campaign);

        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.gestor', [
            'campaign' => $campaign,
            'bi' => $bi,
            'coverage' => $coverage,
            'insights' => $insights,
            'etapas' => $etapas,
            'chartSvgs' => $chartSvgs,
            'series' => $series,
            'schoolTime' => $schoolTime,
            'diagnosticoGeral' => $diagnosticoGeral,
            'generated_at' => $generatedAt,
            'colors' => [
                'primary' => '#0f172a',
                'secondary' => '#0f766e',
                'primary_light' => '#e2e8f0',
            ],
        ])->setPaper('a4');

        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibgeSlug = preg_replace('/\D+/', '', $ibge) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_gestor_%s_%s_%s.pdf', $citySlug, $ibgeSlug, $refDate);

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, array<string, mixed>>  $charts
     * @return list<array{title: string, svg: string}>
     */
    private function chartSvgs(array $charts, ?BiClioCampaign $bi): array
    {
        $candidates = [];

        if ($bi instanceof BiClioCampaign) {
            $matLabels = [];
            $matValues = [];
            foreach ([
                [__('Curricular'), (int) $bi->mat_curricular],
                [__('AEE'), (int) $bi->mat_aee],
                [__('Ativ. complementar'), (int) $bi->mat_ac],
            ] as [$label, $n]) {
                if ($n > 0) {
                    $matLabels[] = $label;
                    $matValues[] = $n;
                }
            }
            if ($matValues !== []) {
                $candidates[] = ChartPayload::bar(__('Matrículas por tipo'), __('Alunos'), $matLabels, $matValues);
            }
        }

        foreach ([
            'etapas',
            'localizacao',
            'triade_parts',
            'qualidade',
            'inclusao',
            'densidade',
            'distorcao_etapas',
            'turmas_tipo',
            'jornada_turno',
            'docentes',
        ] as $key) {
            if (isset($charts[$key]) && is_array($charts[$key])) {
                $candidates[] = $this->asBarChart($charts[$key]);
            }
        }

        $out = [];
        foreach ($candidates as $chart) {
            if (! is_array($chart)) {
                continue;
            }
            $svg = AnalyticsReportChartSvg::render($chart);
            if ($svg === null) {
                continue;
            }
            $out[] = [
                'title' => (string) ($chart['title'] ?? __('Gráfico')),
                'svg' => $svg,
            ];
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>|null
     */
    private function asBarChart(array $chart): ?array
    {
        $labels = is_array($chart['labels'] ?? null) ? array_values($chart['labels']) : [];
        $datasets = is_array($chart['datasets'] ?? null) ? $chart['datasets'] : [];
        $first = $datasets[0] ?? null;
        if ($labels === [] || ! is_array($first) || ! is_array($first['data'] ?? null)) {
            return null;
        }

        $values = array_map(static fn ($v): float => (float) ($v ?? 0), array_values($first['data']));
        if (array_sum($values) <= 0) {
            return null;
        }

        $title = (string) ($chart['title'] ?? __('Indicador'));
        $horizontal = ($chart['options']['indexAxis'] ?? '') === 'y'
            || count($labels) > 8;

        return $horizontal
            ? ChartPayload::barHorizontal($title, (string) ($first['label'] ?? __('Valor')), $labels, $values)
            : ChartPayload::bar($title, (string) ($first['label'] ?? __('Valor')), $labels, $values);
    }

    private function slugPart(string $value): string
    {
        $ascii = Str::ascii($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/i', '_', $ascii);
        $slug = trim($slug, '_');

        return mb_strtolower($slug);
    }
}
