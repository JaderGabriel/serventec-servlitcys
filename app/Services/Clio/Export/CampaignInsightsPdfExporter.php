<?php

namespace App\Services\Clio\Export;

use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioEnrollmentStage;
use App\Models\Bi\BiClioInsight;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignSchoolTimeComposer;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Analysis\RelationCsvAggregator;
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
        private readonly EtapaLabelOrder $etapaOrder,
        private readonly RelationCsvAggregator $aggregator = new RelationCsvAggregator,
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

        $corRacaTable = $this->corRacaTable($inferences['INF-DEM'] ?? []);
        $chartTables = $this->chartTables($charts, $bi, $corRacaTable);

        $insights = BiClioInsight::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->sortBy([
                static fn (BiClioInsight $row): int => match ((string) ($row->severity ?? '')) {
                    'error' => 0,
                    'warning' => 1,
                    default => 2,
                },
                static fn (BiClioInsight $row): int => (int) ($row->sort ?? 0),
            ])
            ->values();

        $etapaRows = BiClioEnrollmentStage::query()
            ->where('campaign_id', $campaign->id)
            ->whereNull('inep')
            ->get(['etapa', 'qt_alunos', 'qt_turmas'])
            ->map(static fn ($r): array => [
                'etapa' => (string) $r->etapa,
                'alunos' => (int) $r->qt_alunos,
                'turmas' => (int) $r->qt_turmas,
            ])
            ->all();
        $etapaGroups = $this->enrichEtapaGroupsWithDistortion(
            $this->etapaOrder->groupEnrollmentRows($etapaRows),
            $inferences['INF-DIS'] ?? [],
        );

        $ibge = (string) ($campaign->ibge_municipio ?: $campaign->city?->ibge_municipio ?? '');
        $series = $this->enrollmentSeries->forIbge($ibge, 5, 'municipal', allowConsultoriaActive: true);
        $seriesChartImg = null;
        if (($series['ok'] ?? false) === true && is_array($series['chart'] ?? null)) {
            $seriesChartImg = AnalyticsReportChartSvg::renderDataUri($series['chart'], 520, 248);
        }
        $schoolTime = $this->schoolTime->compose($campaign);
        $diagnosticoGeral = app(DiagnosticoGeralComposer::class)->compose($campaign);

        $generatedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.clio-campaign.gestor', [
            'campaign' => $campaign,
            'bi' => $bi,
            'coverage' => $coverage,
            'insights' => $insights,
            'etapaGroups' => $etapaGroups,
            'chartTables' => $chartTables,
            'series' => $series,
            'seriesChartImg' => $seriesChartImg,
            'schoolTime' => $schoolTime,
            'diagnosticoGeral' => $diagnosticoGeral,
            'generated_at' => $generatedAt,
            'colors' => [
                'primary' => '#0f172a',
                'secondary' => '#0f766e',
                'primary_light' => '#e2e8f0',
            ],
        ])->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $citySlug = $this->slugPart((string) $campaign->municipality_name) ?: 'municipio';
        $ibgeSlug = preg_replace('/\D+/', '', $ibge) ?: 'ibge';
        $refDate = $campaign->reference_date
            ? $campaign->reference_date->format('Y-m-d')
            : (string) ((int) $campaign->year);
        $filename = sprintf('clio_gestor_%s_%s_%s.pdf', $citySlug, $ibgeSlug, $refDate);

        return $pdf->download($filename);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array<string, mixed>  $disPayload
     * @return list<array<string, mixed>>
     */
    private function enrichEtapaGroupsWithDistortion(array $groups, array $disPayload): array
    {
        $byEtapa = is_array($disPayload['by_etapa'] ?? null) ? $disPayload['by_etapa'] : [];

        foreach ($groups as &$group) {
            $rows = is_array($group['rows'] ?? null) ? $group['rows'] : [];
            foreach ($rows as &$row) {
                $etapa = (string) ($row['etapa'] ?? '');
                $info = is_array($byEtapa[$etapa] ?? null) ? $byEtapa[$etapa] : [];
                $eligible = (int) ($info['eligible'] ?? 0);
                $dist = (int) ($info['distorcao'] ?? 0);
                $pct = $info['pct_distorcao'] ?? null;
                if ($pct === null && $eligible > 0) {
                    $pct = round(100 * $dist / $eligible, 1);
                }
                $row['eligible'] = $eligible;
                $row['distorcao'] = $dist;
                $row['pct_distorcao'] = is_numeric($pct) ? (float) $pct : null;
                $row['has_distortion_scope'] = $eligible > 0;
            }
            unset($row);
            $group['rows'] = $rows;
        }
        unset($group);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $demPayload
     * @return array{available: bool, scanned: int, rows: list<array{label: string, value: int, pct: float|null}>}
     */
    private function corRacaTable(array $demPayload): array
    {
        $byCor = is_array($demPayload['by_cor_raca'] ?? null) ? $demPayload['by_cor_raca'] : [];
        $scanned = (int) ($demPayload['scanned'] ?? 0);
        $bars = $this->aggregator->toBars($byCor, 12);
        $rows = [];
        foreach ($bars as $bar) {
            $value = (int) ($bar['count'] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $rows[] = [
                'label' => (string) ($bar['label'] ?? '—'),
                'value' => $value,
                'pct' => isset($bar['pct']) && is_numeric($bar['pct']) ? (float) $bar['pct'] : null,
            ];
        }

        return [
            'available' => $rows !== [],
            'scanned' => $scanned,
            'rows' => $rows,
        ];
    }

    /**
     * Tabelas para DomPDF (SVG denso fica ilegível com muitas categorias).
     * Etapas/distorção ficam na secção consolidada «Matrículas por etapa».
     * Cor/Raça é inserida imediatamente após a tipificação NEE.
     *
     * @param  array<string, array<string, mixed>>  $charts
     * @param  array{available: bool, scanned: int, rows: list<array{label: string, value: int, pct: float|null}>}  $corRaca
     * @return list<array<string, mixed>>
     */
    private function chartTables(array $charts, ?BiClioCampaign $bi, array $corRaca): array
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
                $candidates[] = [
                    'key' => 'matriculas_tipo',
                    'chart' => ChartPayload::bar(__('Matrículas por tipo'), __('Alunos'), $matLabels, $matValues),
                ];
            }
        }

        foreach ([
            'localizacao',
            'triade_parts',
            'qualidade',
            'inclusao',
            'aee_gap',
            'densidade',
            'turmas_tipo',
            'jornada_turno',
            'docentes',
        ] as $key) {
            if (isset($charts[$key]) && is_array($charts[$key])) {
                $asBar = $this->asBarChart($charts[$key]);
                if ($asBar !== null) {
                    $candidates[] = ['key' => $key, 'chart' => $asBar];
                }
            }
        }

        $out = [];
        $corInserted = false;
        foreach ($candidates as $item) {
            $chart = $item['chart'] ?? null;
            $key = (string) ($item['key'] ?? '');
            if (! is_array($chart)) {
                continue;
            }
            $rows = $this->chartToRows($chart, 12, false);
            if ($rows === []) {
                continue;
            }
            $out[] = [
                'title' => (string) ($chart['title'] ?? __('Indicador')),
                'rows' => $rows,
            ];

            if ($key === 'inclusao' && ($corRaca['available'] ?? false) && ! $corInserted) {
                $out[] = [
                    'title' => __('Distribuição de alunos por Cor/Raça'),
                    'note' => __('Agregado da Relação de alunos (:n pessoas lidas), sem identificação pessoal.', [
                        'n' => number_format((int) ($corRaca['scanned'] ?? 0), 0, ',', '.'),
                    ]),
                    'show_pct' => true,
                    'rows' => array_map(static fn (array $r): array => [
                        'label' => $r['label'],
                        'value' => $r['value'],
                        'pct' => $r['pct'],
                    ], $corRaca['rows']),
                ];
                $corInserted = true;
            }

            if (count($out) >= 10) {
                break;
            }
        }

        if (! $corInserted && ($corRaca['available'] ?? false)) {
            $out[] = [
                'title' => __('Distribuição de alunos por Cor/Raça'),
                'note' => __('Agregado da Relação de alunos (:n pessoas lidas), sem identificação pessoal.', [
                    'n' => number_format((int) ($corRaca['scanned'] ?? 0), 0, ',', '.'),
                ]),
                'show_pct' => true,
                'rows' => array_map(static fn (array $r): array => [
                    'label' => $r['label'],
                    'value' => $r['value'],
                    'pct' => $r['pct'],
                ], $corRaca['rows']),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return list<array{label: string, value: int|float}>
     */
    private function chartToRows(array $chart, int $limit, bool $preserveOrder = false): array
    {
        $labels = is_array($chart['labels'] ?? null) ? array_values($chart['labels']) : [];
        $datasets = is_array($chart['datasets'] ?? null) ? $chart['datasets'] : [];
        $first = $datasets[0] ?? null;
        if ($labels === [] || ! is_array($first) || ! is_array($first['data'] ?? null)) {
            return [];
        }

        $values = array_map(static fn ($v): float => (float) ($v ?? 0), array_values($first['data']));
        $pairs = [];
        foreach ($labels as $i => $label) {
            $pairs[] = [
                'label' => mb_substr(trim((string) $label), 0, 96),
                'value' => $values[$i] ?? 0.0,
            ];
        }
        if (! $preserveOrder) {
            usort($pairs, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        }

        return array_slice($pairs, 0, max(1, $limit));
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

        return ChartPayload::bar($title, (string) ($first['label'] ?? __('Valor')), $labels, $values);
    }

    private function slugPart(string $value): string
    {
        $ascii = Str::ascii($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/i', '_', $ascii);
        $slug = trim($slug, '_');

        return mb_strtolower($slug);
    }
}
