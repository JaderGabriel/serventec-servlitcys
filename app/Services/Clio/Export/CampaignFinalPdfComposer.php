<?php

namespace App\Services\Clio\Export;

use App\Models\Bi\BiClioInclusion;
use App\Models\Bi\BiClioSchool;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\CampaignNeeCensusBuilder;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Horizonte\HorizonteMunicipioEnrollmentSeriesService;
use App\Support\Analytics\AnalyticsReportChartSvg;
use Illuminate\Support\Collection;

/**
 * Monta o payload do PDF Final: um tema educativo por página + Diagnóstico Geral com tríade.
 */
final class CampaignFinalPdfComposer
{
    public function __construct(
        private readonly CampaignParseService $parser,
        private readonly CampaignAnalysisPresenter $presenter,
        private readonly DiagnosticoGeralComposer $diagnosticoGeral,
        private readonly HorizonteMunicipioEnrollmentSeriesService $enrollmentSeries,
        private readonly CampaignActiveCensusMatrixBuilder $censusMatrixBuilder,
        private readonly CensusExposurePdfTables $censusExposureTables = new CensusExposurePdfTables,
    ) {}

    /**
     * @return array{
     *   dashboard: array<string, mixed>,
     *   themes: list<array<string, mixed>>,
     *   diagnostico_geral: array<string, mixed>,
     *   schools_triade: list<array<string, mixed>>,
     *   triade_summary: array{kpis: list<array{label: string, value: string}>, diagnosis: list<string>},
     *   coverage: array<string, mixed>
     * }
     */
    public function compose(ClioCampaign $campaign): array
    {
        $campaign->loadMissing([
            'schools.artifacts',
            'artifacts.school',
            'inferences',
            'findings.school',
            'city',
        ]);

        $coverage = $this->parser->coverage($campaign);
        $dashboard = $this->presenter->present(
            $campaign,
            $coverage,
            $campaign->inferences->keyBy('code'),
            $campaign->findings,
        );

        /** @var Collection<int, ClioCampaignFinding> $findings */
        $findings = $campaign->findings;

        $themes = array_values(array_filter([
            $this->themeSerieHistorica($campaign),
            $this->themeMatriculas($campaign, $dashboard, $findings),
            $this->themeInclusao($campaign, $dashboard, $findings),
            $this->themeDensidade($dashboard, $findings),
            $this->themeDistorcao($dashboard, $findings),
            $this->themeDemografia($dashboard, $findings),
            $this->themeTransporte($dashboard, $findings),
            $this->themeTemposEscolares($dashboard, $findings),
        ], static fn (?array $theme): bool => $theme !== null));

        $counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
        $triade = is_array($dashboard['triade'] ?? null) ? $dashboard['triade'] : [];

        $schoolsTriade = collect($dashboard['schools_active'] ?? [])
            ->map(static function (array $row): array {
                return [
                    'inep' => (string) ($row['inep'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'status' => (string) ($row['status'] ?? '—'),
                    'tone' => (string) ($row['tone'] ?? 'slate'),
                    'aluno' => (bool) ($row['aluno'] ?? false),
                    'turma' => (bool) ($row['turma'] ?? false),
                    'profissional' => (bool) ($row['profissional'] ?? false),
                    'triade' => (bool) ($row['triade'] ?? false),
                    'missing' => is_array($row['missing'] ?? null) ? $row['missing'] : [],
                    'errors' => (int) ($row['errors'] ?? 0),
                    'warnings' => (int) ($row['warnings'] ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'dashboard' => $dashboard,
            'themes' => $themes,
            'diagnostico_geral' => $this->diagnosticoGeral->compose($campaign),
            'schools_triade' => $schoolsTriade,
            'triade_summary' => [
                'kpis' => [
                    ['label' => __('Escolas ativas'), 'value' => $this->fmtInt($counters['schools_active'] ?? 0)],
                    ['label' => __('Tríade completa'), 'value' => $this->fmtInt($counters['schools_triade'] ?? 0)],
                    ['label' => __('Cobertura tríade'), 'value' => $this->fmtPct($triade['pct'] ?? null)],
                    ['label' => __('Com erros'), 'value' => $this->fmtInt($counters['schools_with_errors'] ?? 0)],
                ],
                'diagnosis' => $this->highlightSummary($dashboard, ['INF-COL', 'INF-ESC', 'INF-COE']),
            ],
            'coverage' => $coverage,
        ];
    }

    /**
     * Série histórica Censo INEP (primeiro tópico do PDF Final).
     *
     * @return array<string, mixed>|null
     */
    private function themeSerieHistorica(ClioCampaign $campaign): ?array
    {
        $ibge = (string) ($campaign->ibge_municipio ?: $campaign->city?->ibge_municipio ?? '');
        if ($ibge === '') {
            return null;
        }

        $series = $this->enrollmentSeries->forIbge($ibge, 5, 'municipal', allowConsultoriaActive: true);
        if (($series['ok'] ?? false) !== true) {
            return null;
        }

        $chart = is_array($series['chart'] ?? null) ? $series['chart'] : [];
        $labels = is_array($chart['labels'] ?? null) ? $chart['labels'] : [];
        $datasets = is_array($chart['datasets'] ?? null) ? $chart['datasets'] : [];
        if ($labels === [] || $datasets === []) {
            return null;
        }

        $chartImg = null;
        try {
            $chartImg = AnalyticsReportChartSvg::renderDataUri($chart, 520, 248);
        } catch (\Throwable) {
            $chartImg = null;
        }

        $latest = is_array($series['latest_summary'] ?? null) ? $series['latest_summary'] : [];
        $latestAno = (string) ($latest['ano'] ?? (end($labels) ?: '—'));
        $latestTotal = (int) ($latest['total'] ?? 0);
        if ($latestTotal === 0) {
            foreach ($datasets as $ds) {
                if (! is_array($ds)) {
                    continue;
                }
                $key = mb_strtolower((string) ($ds['key'] ?? $ds['label'] ?? ''));
                if ($key === 'total' || str_contains($key, 'total')) {
                    $data = is_array($ds['data'] ?? null) ? $ds['data'] : [];
                    $latestTotal = (int) (end($data) ?: 0);
                    break;
                }
            }
        }

        $prevTotal = null;
        foreach ($datasets as $ds) {
            if (! is_array($ds)) {
                continue;
            }
            $key = mb_strtolower((string) ($ds['key'] ?? $ds['label'] ?? ''));
            if ($key === 'total' || str_contains($key, 'total')) {
                $data = is_array($ds['data'] ?? null) ? array_values($ds['data']) : [];
                if (count($data) >= 2) {
                    $prevTotal = (int) $data[count($data) - 2];
                }
                break;
            }
        }

        $deltaLabel = '—';
        if ($prevTotal !== null) {
            $delta = $latestTotal - $prevTotal;
            $deltaLabel = ($delta > 0 ? '+' : '').$this->fmtInt($delta);
        }

        $kpis = [
            ['label' => __('Último ano (:y)', ['y' => $latestAno]), 'value' => $this->fmtInt($latestTotal)],
            ['label' => __('Variação vs ano anterior'), 'value' => $deltaLabel],
            ['label' => __('Anos na série'), 'value' => $this->fmtInt(count($labels))],
            ['label' => __('Recorte'), 'value' => (string) ($series['dependencia_label'] ?? __('Municipal'))],
        ];

        $headers = array_merge([__('Indicador')], array_map(static fn ($y): string => (string) $y, $labels));
        $rows = [];
        foreach ($datasets as $ds) {
            if (! is_array($ds)) {
                continue;
            }
            $row = [(string) ($ds['label'] ?? '—')];
            foreach (is_array($ds['data'] ?? null) ? $ds['data'] : [] as $val) {
                $row[] = ($val === null || $val === '') ? '—' : $this->fmtInt($val);
            }
            $rows[] = $row;
        }

        $diagnosis = [];
        $footnote = trim((string) ($series['footnote'] ?? ''));
        if ($footnote !== '') {
            $diagnosis[] = $footnote;
        }
        $diagnosis[] = __('Fonte: Censo Escolar / Educacenso (INEP), agregação municipal indexada — não substitui os totais da coleta Clio.');

        $stageItems = is_array($series['stage_counters']['items'] ?? null) ? $series['stage_counters']['items'] : [];
        if ($stageItems !== []) {
            $parts = [];
            foreach ($stageItems as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $parts[] = ((string) ($item['label'] ?? '')).': '.$this->fmtInt($item['value'] ?? 0);
            }
            if ($parts !== []) {
                $diagnosis[] = __('Último ano com dados (:y) — :p', [
                    'y' => (string) ($series['stage_counters']['ano'] ?? $latestAno),
                    'p' => implode(' · ', $parts),
                ]);
            }
        }

        $theme = $this->makeTheme(
            key: 'serie_historica',
            title: __('Série histórica de matrículas'),
            lead: __('Evolução das matrículas da rede municipal no Censo INEP (últimos anos indexados), com gráfico e tabela por segmento.'),
            kpis: $kpis,
            diagnosis: $diagnosis,
            tables: [[
                'title' => __('Matrículas por ano (Censo)'),
                'headers' => $headers,
                'rows' => $rows,
            ]],
            findings: [],
        );

        $theme['chart_img'] = $chartImg;
        $theme['chart_alt'] = __('Série histórica de matrículas');

        return $theme;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeMatriculas(ClioCampaign $campaign, array $dashboard, Collection $findings): ?array
    {
        $report = is_array($dashboard['report'] ?? null) ? $dashboard['report'] : [];
        $matrix = $this->censusMatrixBuilder->build($campaign);
        $exposureTables = $this->censusExposureTables->format($matrix);

        if (empty($report['available']) && $exposureTables === []) {
            return null;
        }

        $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
        $kpis = [];
        foreach (array_slice($totals, 0, 4) as $tile) {
            if (! is_array($tile)) {
                continue;
            }
            $kpis[] = [
                'label' => (string) ($tile['label'] ?? '—'),
                'value' => (string) ($tile['value'] ?? '—'),
            ];
        }
        if ($kpis === [] && ! empty($matrix['available'])) {
            $geralValues = is_array($matrix['geral']['values'] ?? null) ? $matrix['geral']['values'] : [];
            $kpis = [
                ['label' => __('Escolas ativas'), 'value' => $this->fmtInt($matrix['schools_active'] ?? 0)],
                ['label' => __('GERAL (regular)'), 'value' => $this->fmtInt($geralValues['geral'] ?? 0)],
                ['label' => __('Educação Especial'), 'value' => $this->fmtInt($geralValues['especial'] ?? 0)],
                ['label' => __('Ano'), 'value' => (string) ($matrix['year'] ?? $campaign->year)],
            ];
        }

        $tables = $exposureTables;

        $modalidadeRows = [];
        foreach (is_array($report['matricula_modalidade'] ?? null) ? $report['matricula_modalidade'] : [] as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $modalidadeRows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
                $this->fmtPct($bar['pct'] ?? null),
            ];
        }
        if ($modalidadeRows !== []) {
            $tables[] = [
                'title' => __('Modalidade (Acompanhamento)'),
                'headers' => [__('Tipo'), __('Qtd.'), __('%')],
                'rows' => $modalidadeRows,
            ];
        }

        $etapaBars = [];
        $outrosBar = null;
        foreach (is_array($report['matriculas_por_ano'] ?? null) ? $report['matriculas_por_ano'] : [] as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '');
            if ($label === __('Outros') || mb_strtolower($label) === 'outros') {
                $outrosBar = $bar;

                continue;
            }
            $etapaBars[] = $bar;
        }
        $etapaBars = (new EtapaLabelOrder)->sortRowsByEtapaKey($etapaBars, 'label');
        if ($outrosBar !== null) {
            $etapaBars[] = $outrosBar;
        }

        $etapaRows = [];
        foreach ($etapaBars as $bar) {
            $etapaRows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
            ];
        }
        if ($etapaRows !== []) {
            $tables[] = [
                'title' => __('Matrículas por etapa (Relação)'),
                'headers' => [__('Etapa'), __('Alunos')],
                'rows' => $etapaRows,
            ];
        }

        $notes = is_array($report['quality_notes'] ?? null) ? array_values(array_filter($report['quality_notes'])) : [];
        $lead = $exposureTables !== []
            ? __('Começa com a exposição das matrículas nas escolas ativas (etapa × jornada; células urbana / rural), seguida dos totais curriculares, AEE e coerência Acompanhamento × Relações.')
            : __('Totais curriculares, AEE e atividade complementar, com coerência entre Acompanhamento e Relações.');

        return $this->makeTheme(
            key: 'matriculas',
            title: __('Matrículas e etapas'),
            lead: $lead,
            kpis: $kpis,
            diagnosis: array_merge(
                $this->highlightSummary($dashboard, ['INF-MAT', 'INF-TUR', 'INF-DELTA', 'INF-XCHK']),
                $notes,
            ),
            tables: $tables,
            findings: $this->findingsFor($findings, ['MAT', 'TUR', 'DELTA', 'XCHK']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeInclusao(ClioCampaign $campaign, array $dashboard, Collection $findings): ?array
    {
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $reportInc = is_array(($dashboard['report']['inclusion'] ?? null)) ? $dashboard['report']['inclusion'] : [];

        if (empty($profile['available']) && ! $this->hasHighlight($dashboard, 'INF-NEE') && ! $this->hasHighlight($dashboard, 'INF-GAP')) {
            return null;
        }

        $semAee = (int) ($profile['nee_without_aee'] ?? 0);
        $aeeSemNee = (int) ($profile['nee_aee_without_condition'] ?? 0);
        $under = (int) ($profile['underreporting_flagged'] ?? 0);

        $kpis = [
            ['label' => __('Com marcador NEE'), 'value' => $this->fmtInt($profile['nee_flagged'] ?? $reportInc['flagged'] ?? 0)],
            ['label' => __('NEE sem AEE'), 'value' => $this->fmtInt($semAee)],
            ['label' => __('AEE sem NEE'), 'value' => $this->fmtInt($aeeSemNee)],
            ['label' => __('Alertas tipificação'), 'value' => $this->fmtInt($under)],
        ];

        $rows = [];
        foreach (array_slice(is_array($profile['by_nee'] ?? null) ? $profile['by_nee'] : [], 0, 10) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $rows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
            ];
        }

        $tables = $rows === [] ? [] : [[
            'title' => __('Tipificação NEE (agregado)'),
            'headers' => [__('Categoria'), __('Alunos')],
            'rows' => $rows,
        ]];

        $schoolRows = $this->inclusaoSchoolCaseRows($campaign);
        if ($schoolRows !== []) {
            $tables[] = [
                'title' => __('Escolas com NEE sem AEE ou AEE sem tipificação'),
                'headers' => [__('INEP'), __('Escola'), __('NEE sem AEE'), __('AEE sem NEE'), __('Com NEE')],
                'rows' => $schoolRows,
            ];
        }

        $diagnosis = $this->highlightSummary($dashboard, ['INF-NEE', 'INF-GAP']);
        if ($semAee > 0) {
            array_unshift($diagnosis, __('Há :n pessoa(s) com NEE/TEA/AH sem matrícula AEE identificada — revisar oferta e vínculo nas escolas listadas.', [
                'n' => $semAee,
            ]));
        }
        if ($aeeSemNee > 0) {
            array_unshift($diagnosis, __('Há :n pessoa(s) em AEE sem tipificação NEE/TEA/AH — conferir declaração das condições.', [
                'n' => $aeeSemNee,
            ]));
        }
        foreach (['nee_note_def_vs_trs', 'nee_note_sub'] as $noteKey) {
            $note = trim((string) ($profile[$noteKey] ?? ''));
            if ($note !== '') {
                $diagnosis[] = $note;
            }
        }

        $themeFindings = $this->findingsFor($findings, ['NEE', 'AEE', 'GAP']);
        $themeFindings = $this->prependInclusaoGapFindings($themeFindings, $semAee, $aeeSemNee);

        return $this->makeTheme(
            key: 'inclusao',
            title: __('Inclusão e educação especial'),
            lead: __('Sinais de NEE/TEA/AH nas Relações e coerência com turmas AEE — leitura indicativa, sem dados pessoais.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $themeFindings,
        );
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, school: string|null}>  $findings
     * @return list<array{severity: string, code: string, message: string, school: string|null}>
     */
    private function prependInclusaoGapFindings(array $findings, int $semAee, int $aeeSemNee): array
    {
        $codes = array_map(static fn (array $f): string => (string) ($f['code'] ?? ''), $findings);
        $extra = [];

        if ($semAee > 0 && ! in_array('CLIO-NEE-SEM-AEE', $codes, true)) {
            $extra[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-NEE-SEM-AEE',
                'message' => __(':n pessoa(s) com NEE/TEA/AH sem matrícula AEE', ['n' => $semAee]),
                'school' => null,
            ];
        }
        if ($aeeSemNee > 0 && ! in_array('CLIO-AEE-SEM-NEE', $codes, true)) {
            $extra[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-AEE-SEM-NEE',
                'message' => __(':n pessoa(s) em AEE sem tipificação NEE/TEA/AH', ['n' => $aeeSemNee]),
                'school' => null,
            ];
        }

        return array_merge($extra, $findings);
    }

    /**
     * @return list<list<string>>
     */
    private function inclusaoSchoolCaseRows(ClioCampaign $campaign): array
    {
        try {
            $rows = BiClioInclusion::query()
                ->where('campaign_id', $campaign->id)
                ->where(static function ($q): void {
                    $q->where('qt_without_aee', '>', 0)
                        ->orWhere('qt_aee_without_nee', '>', 0);
                })
                ->orderByDesc('qt_without_aee')
                ->orderByDesc('qt_aee_without_nee')
                ->get(['inep', 'qt_without_aee', 'qt_aee_without_nee', 'qt_nee_people']);
        } catch (\Throwable) {
            $rows = collect();
        }

        if ($rows->isEmpty()) {
            return $this->inclusaoSchoolCaseRowsFromCensus($campaign);
        }

        $ineps = $rows->pluck('inep')->filter()->map(static fn ($v) => (string) $v)->all();
        $names = $this->inclusaoSchoolNames($campaign, $ineps);

        $out = [];
        foreach ($rows as $row) {
            $inep = (string) ($row->inep ?? '');
            $out[] = [
                $inep !== '' ? $inep : '—',
                (string) ($names[$inep] ?? ($inep !== '' ? $inep : '—')),
                $this->fmtInt($row->qt_without_aee ?? 0),
                $this->fmtInt($row->qt_aee_without_nee ?? 0),
                $this->fmtInt($row->qt_nee_people ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Fallback quando o BI ainda não foi atualizado: censo por escola a partir das Relações.
     *
     * @return list<list<string>>
     */
    private function inclusaoSchoolCaseRowsFromCensus(ClioCampaign $campaign): array
    {
        try {
            $builder = app(CampaignNeeCensusBuilder::class);
        } catch (\Throwable) {
            return [];
        }

        $campaign->loadMissing('schools');
        $out = [];
        foreach ($campaign->schools as $school) {
            if (CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status ?? null)) {
                continue;
            }
            try {
                $census = $builder->build($campaign, (int) $school->id);
            } catch (\Throwable) {
                continue;
            }
            $semAee = (int) ($census['without_aee'] ?? 0);
            $aeeSem = (int) ($census['aee_without_nee'] ?? 0);
            if ($semAee === 0 && $aeeSem === 0) {
                continue;
            }
            $out[] = [
                (string) ($school->inep ?? '—'),
                (string) ($school->name ?? $school->inep ?? '—'),
                $this->fmtInt($semAee),
                $this->fmtInt($aeeSem),
                $this->fmtInt($census['flagged'] ?? 0),
                $semAee, // sort key
            ];
        }

        usort($out, static fn (array $a, array $b): int => ($b[5] ?? 0) <=> ($a[5] ?? 0));

        return array_map(static fn (array $row): array => array_slice($row, 0, 5), $out);
    }

    /**
     * @param  list<string>  $ineps
     * @return array<string, string>
     */
    private function inclusaoSchoolNames(ClioCampaign $campaign, array $ineps): array
    {
        $names = [];
        try {
            $names = BiClioSchool::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('inep', $ineps)
                ->pluck('name', 'inep')
                ->map(static fn ($v) => (string) $v)
                ->all();
        } catch (\Throwable) {
            $names = [];
        }

        if ($names !== []) {
            return $names;
        }

        $campaign->loadMissing('schools');
        foreach ($campaign->schools as $school) {
            $inep = (string) ($school->inep ?? '');
            if ($inep !== '') {
                $names[$inep] = (string) ($school->name ?? $inep);
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeDistorcao(array $dashboard, Collection $findings): ?array
    {
        $stage = is_array($dashboard['stage_metrics'] ?? null) ? $dashboard['stage_metrics'] : [];
        $dis = is_array($stage['distortion'] ?? null) ? $stage['distortion'] : [];
        if (empty($stage['available']) && ! $this->hasHighlight($dashboard, 'INF-DIS')) {
            return null;
        }

        $kpis = [
            ['label' => __('Distorção rede'), 'value' => $this->fmtPct($dis['pct'] ?? null)],
            ['label' => __('Alunos elegíveis'), 'value' => $this->fmtInt($dis['eligible'] ?? 0)],
            ['label' => __('Atraso 1 ano'), 'value' => $this->fmtInt($dis['atraso_1'] ?? 0)],
            ['label' => __('Adiantados'), 'value' => $this->fmtInt($dis['adiantado'] ?? 0)],
        ];

        $etapaBars = is_array($dis['by_etapa'] ?? null) ? $dis['by_etapa'] : [];
        $etapaBars = (new EtapaLabelOrder)->sortRowsByEtapaKey(
            array_values(array_filter($etapaBars, static fn ($row): bool => is_array($row))),
            'etapa',
        );

        $rows = [];
        foreach ($etapaBars as $info) {
            $rows[] = [
                (string) ($info['etapa'] ?? '—'),
                $this->fmtInt($info['eligible'] ?? 0),
                $this->fmtInt($info['distorcao'] ?? 0),
                $this->fmtPct($info['pct'] ?? null),
                $this->fmtInt($info['atraso_1'] ?? 0),
                $this->fmtInt($info['adiantado'] ?? 0),
            ];
        }

        $tables = $rows === [] ? [] : [[
            'title' => __('Distorção por etapa'),
            'headers' => [
                __('Etapa'),
                __('Elegíveis'),
                __('Distorção'),
                __('%'),
                __('Atraso 1 ano'),
                __('Adiantados'),
            ],
            'rows' => $rows,
        ]];

        $diagnosis = $this->highlightSummary($dashboard, ['INF-DIS']);
        $note = trim((string) ($dis['note'] ?? $dis['summary'] ?? ''));
        if ($note !== '') {
            array_unshift($diagnosis, $note);
        }
        $diagnosis[] = __('Atraso 1 ano = defasagem leve (ainda não é distorção oficial). Adiantados = idade abaixo da esperada para a série.');

        return $this->makeTheme(
            key: 'distorcao',
            title: __('Distorção idade-série'),
            lead: __('Estimativa INEP: alunos com 2 ou mais anos acima da idade esperada para a série. Todas as etapas do município na ordem pedagógica.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $this->findingsFor($findings, ['DIS']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeDemografia(array $dashboard, Collection $findings): ?array
    {
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $corBars = is_array($profile['by_cor_raca'] ?? null) ? $profile['by_cor_raca'] : [];
        $sexoBars = is_array($profile['by_sexo'] ?? null) ? $profile['by_sexo'] : [];
        if ($corBars === [] && $sexoBars === [] && ! $this->hasHighlight($dashboard, 'INF-DEM')) {
            return null;
        }

        $undeclared = 0;
        foreach ($corBars as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = mb_strtolower((string) ($bar['label'] ?? ''));
            if (str_contains($label, 'não') || str_contains($label, 'nao') || str_contains($label, 'não declar')) {
                $undeclared += (int) ($bar['count'] ?? 0);
            }
        }

        $kpis = [
            ['label' => __('Alunos lidos'), 'value' => $this->fmtInt($profile['scanned'] ?? 0)],
            ['label' => __('Cor/Raça categorias'), 'value' => $this->fmtInt(count($corBars))],
            ['label' => __('Sexo categorias'), 'value' => $this->fmtInt(count($sexoBars))],
            ['label' => __('Não declarado (Cor)'), 'value' => $this->fmtInt($undeclared)],
        ];

        $tables = [];
        $corRows = [];
        foreach (array_slice($corBars, 0, 10) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $corRows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
                $this->fmtPct($bar['pct'] ?? null),
            ];
        }
        if ($corRows !== []) {
            $tables[] = [
                'title' => __('Cor/Raça'),
                'headers' => [__('Categoria'), __('Alunos'), __('%')],
                'rows' => $corRows,
            ];
        }

        $sexoRows = [];
        foreach (array_slice($sexoBars, 0, 6) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $sexoRows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
                $this->fmtPct($bar['pct'] ?? null),
            ];
        }
        if ($sexoRows !== []) {
            $tables[] = [
                'title' => __('Sexo'),
                'headers' => [__('Categoria'), __('Alunos'), __('%')],
                'rows' => $sexoRows,
            ];
        }

        return $this->makeTheme(
            key: 'demografia',
            title: __('Perfil demográfico'),
            lead: __('Agregados de Cor/Raça e sexo a partir da Relação de alunos — sem identificação pessoal.'),
            kpis: $kpis,
            diagnosis: $this->highlightSummary($dashboard, ['INF-DEM']),
            tables: $tables,
            findings: $this->findingsFor($findings, ['DEM']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeTransporte(array $dashboard, Collection $findings): ?array
    {
        $tra = is_array($dashboard['transporte'] ?? null) ? $dashboard['transporte'] : [];
        if (empty($tra['available']) && ! $this->hasHighlight($dashboard, 'INF-TRA')) {
            return null;
        }

        $active = is_array($tra['active'] ?? null) ? $tra['active'] : [];

        $kpis = [
            ['label' => __('Usam transporte'), 'value' => $this->fmtInt($tra['flagged'] ?? 0)],
            ['label' => __('% na rede'), 'value' => $this->fmtPct($tra['pct'] ?? null)],
            ['label' => __('Ativas · usam'), 'value' => $this->fmtInt($active['flagged'] ?? 0)],
            ['label' => __('Pessoas lidas'), 'value' => $this->fmtInt($tra['scanned'] ?? 0)],
        ];

        $rows = [];
        foreach (array_slice(is_array($tra['by_transporte'] ?? null) ? $tra['by_transporte'] : [], 0, 10) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $rows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
            ];
        }

        $tables = $rows === [] ? [] : [[
            'title' => __('Tipo de transporte (amostra)'),
            'headers' => [__('Categoria'), __('Qtd.')],
            'rows' => $rows,
        ]];

        $diagnosis = $this->highlightSummary($dashboard, ['INF-TRA']);
        $summary = trim((string) ($tra['summary'] ?? ''));
        if ($summary !== '') {
            array_unshift($diagnosis, $summary);
        }

        return $this->makeTheme(
            key: 'transporte',
            title: __('Transporte escolar'),
            lead: __('Uso de transporte, localização e tipificação agregada nas escolas em atividade.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $this->findingsFor($findings, ['TRA']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeTemposEscolares(array $dashboard, Collection $findings): ?array
    {
        $jornada = is_array($dashboard['jornada'] ?? null) ? $dashboard['jornada'] : [];
        if (empty($jornada['available']) && ! $this->hasHighlight($dashboard, 'INF-JOR')) {
            return null;
        }

        $schoolTime = is_array($jornada['school_time'] ?? null) ? $jornada['school_time'] : [];
        $network = is_array($schoolTime['network'] ?? null) ? $schoolTime['network'] : [];

        $kpis = [
            ['label' => __('CH média aluno (h)'), 'value' => $this->fmtNum($network['ch_media_aluno'] ?? null)],
            ['label' => __('Horas/semana (média)'), 'value' => $this->fmtNum($network['horas_aluno_semana'] ?? null)],
            ['label' => __('Fund. + AEE contraturno'), 'value' => $this->fmtInt($jornada['fund_aee_contraturno'] ?? 0)],
            ['label' => __('Regular + AC'), 'value' => $this->fmtInt($jornada['curricular_ac'] ?? 0)],
        ];

        $tables = [];

        $segmentRows = [];
        foreach (array_slice(is_array($schoolTime['segments'] ?? null) ? $schoolTime['segments'] : [], 0, 10) as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $segmentRows[] = [
                (string) ($seg['label'] ?? $seg['key'] ?? '—'),
                $this->fmtInt($seg['turmas'] ?? 0),
                $this->fmtInt($seg['alunos'] ?? 0),
                $this->fmtNum($seg['ch_media_aluno'] ?? null),
                $this->fmtNum($seg['horas_aluno_semana'] ?? null),
            ];
        }
        if ($segmentRows !== []) {
            $tables[] = [
                'title' => __('Alunos e tempo na escola'),
                'headers' => [__('Segmento'), __('Turmas'), __('Alunos'), __('CH méd.'), __('h/sem.')],
                'rows' => $segmentRows,
            ];
        }

        $chRows = [];
        foreach (array_slice(is_array($jornada['by_ch_band'] ?? null) ? $jornada['by_ch_band'] : [], 0, 8) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $chRows[] = [
                (string) ($bar['short'] ?? $bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
                $this->fmtPct($bar['pct'] ?? null),
            ];
        }
        if ($chRows !== []) {
            $tables[] = [
                'title' => __('Turmas por carga horária'),
                'headers' => [__('Faixa'), __('Turmas'), __('%')],
                'rows' => $chRows,
            ];
        }

        $diagnosis = $this->highlightSummary($dashboard, ['INF-JOR']);
        foreach (['note_fund_aee', 'note_infantil', 'note_ch'] as $noteKey) {
            $note = trim((string) ($jornada[$noteKey] ?? ''));
            if ($note !== '') {
                $diagnosis[] = $note;
            }
        }

        return $this->makeTheme(
            key: 'tempos_escolares',
            title: __('Tempos escolares'),
            lead: __('Jornada do aluno e distribuição de turmas por carga horária — leitura da análise «Tempo de escolarização».'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $this->findingsFor($findings, ['JOR']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeDensidade(array $dashboard, Collection $findings): ?array
    {
        $stage = is_array($dashboard['stage_metrics'] ?? null) ? $dashboard['stage_metrics'] : [];
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $faixaBars = is_array($profile['by_faixa_etaria'] ?? null) ? $profile['by_faixa_etaria'] : [];

        $hasDensidade = ! empty($stage['available'])
            || $this->hasHighlight($dashboard, 'INF-DEN')
            || $this->hasHighlight($dashboard, 'INF-DOC');
        $hasFaixa = $faixaBars !== [];

        if (! $hasDensidade && ! $hasFaixa) {
            return null;
        }

        $density = is_array($stage['density'] ?? null) ? $stage['density'] : [];
        $staff = is_array($stage['staff'] ?? null) ? $stage['staff'] : [];

        $faixaTotal = 0;
        foreach ($faixaBars as $bar) {
            if (is_array($bar)) {
                $faixaTotal += (int) ($bar['count'] ?? 0);
            }
        }

        $kpis = [
            ['label' => __('Densidade média'), 'value' => $this->fmtNum($density['media'] ?? null)],
            ['label' => __('Turmas ≥ 40'), 'value' => $this->fmtInt($density['turmas_ge_40'] ?? 0)],
            ['label' => __('Vínculos profissionais'), 'value' => $this->fmtInt($staff['rows'] ?? 0)],
            ['label' => __('Alunos c/ faixa etária'), 'value' => $this->fmtInt($faixaTotal > 0 ? $faixaTotal : ($profile['scanned'] ?? 0))],
        ];

        $tables = [];
        $faixaRows = [];
        foreach (array_slice($faixaBars, 0, 12) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $faixaRows[] = [
                (string) ($bar['label'] ?? '—'),
                $this->fmtInt($bar['count'] ?? 0),
                $this->fmtPct($bar['pct'] ?? null),
            ];
        }
        if ($faixaRows !== []) {
            $tables[] = [
                'title' => __('Faixa etária'),
                'headers' => [__('Faixa'), __('Alunos'), __('%')],
                'rows' => $faixaRows,
            ];
        }

        $diagnosis = $this->highlightSummary($dashboard, ['INF-DEN', 'INF-DOC', 'INF-DEM']);
        foreach ([$density['summary'] ?? null, $staff['summary'] ?? null] as $summary) {
            $summary = trim((string) $summary);
            if ($summary !== '') {
                $diagnosis[] = $summary;
            }
        }
        if ($faixaRows !== []) {
            $diagnosis[] = __('Faixa etária calculada a partir da Data de nascimento nas Relações de alunos (agregado, sem PII).');
        }

        return $this->makeTheme(
            key: 'densidade',
            title: __('Densidade, profissionais e faixa etária'),
            lead: __('Alunos por turma, volume de profissionais e pirâmide etária agregada — pressão operacional e perfil etário da rede.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $this->findingsFor($findings, ['DEN', 'DOC']),
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $kpis
     * @param  list<string>  $diagnosis
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{severity: string, code: string, message: string, school: string|null}>  $findings
     * @return array<string, mixed>
     */
    private function makeTheme(
        string $key,
        string $title,
        string $lead,
        array $kpis,
        array $diagnosis,
        array $tables,
        array $findings,
    ): array {
        $errors = count(array_filter($findings, static fn (array $f): bool => ($f['severity'] ?? '') === ClioCampaignFinding::SEVERITY_ERROR));
        $warnings = count(array_filter($findings, static fn (array $f): bool => ($f['severity'] ?? '') === ClioCampaignFinding::SEVERITY_WARNING));

        if ($errors > 0) {
            $status = __('Com erros');
            $statusTone = 'rose';
        } elseif ($warnings > 0) {
            $status = __('Atenção');
            $statusTone = 'amber';
        } elseif ($diagnosis !== [] || $kpis !== []) {
            $status = __('Estável');
            $statusTone = 'emerald';
        } else {
            $status = __('Sem dados');
            $statusTone = 'slate';
        }

        return [
            'key' => $key,
            'title' => mb_strtoupper($title, 'UTF-8'),
            'lead' => $lead,
            'status' => $status,
            'status_tone' => $statusTone,
            'kpis' => $kpis,
            'diagnosis' => array_slice($diagnosis, 0, 6),
            'tables' => $tables,
            'findings' => array_slice($findings, 0, 12),
            'error_count' => $errors,
            'warning_count' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function highlightSummary(array $dashboard, array $codes): array
    {
        $out = [];
        foreach (is_array($dashboard['highlights'] ?? null) ? $dashboard['highlights'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! in_array((string) ($row['code'] ?? ''), $codes, true)) {
                continue;
            }
            $summary = trim((string) ($row['summary'] ?? ''));
            if ($summary !== '') {
                $out[] = $summary;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    private function hasHighlight(array $dashboard, string $code): bool
    {
        foreach (is_array($dashboard['highlights'] ?? null) ? $dashboard['highlights'] : [] as $row) {
            if (is_array($row) && (string) ($row['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @param  list<string>  $tokens
     * @return list<array{severity: string, code: string, message: string, school: string|null}>
     */
    private function findingsFor(Collection $findings, array $tokens): array
    {
        return $findings
            ->filter(function (ClioCampaignFinding $f) use ($tokens): bool {
                $code = strtoupper((string) $f->code);
                foreach ($tokens as $token) {
                    if (str_contains($code, strtoupper($token))) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy(static fn (ClioCampaignFinding $f): int => match ($f->severity) {
                ClioCampaignFinding::SEVERITY_ERROR => 0,
                ClioCampaignFinding::SEVERITY_WARNING => 1,
                default => 2,
            })
            ->take(12)
            ->map(static fn (ClioCampaignFinding $f): array => [
                'severity' => (string) $f->severity,
                'code' => (string) $f->code,
                'message' => (string) $f->message,
                'school' => $f->school?->name,
            ])
            ->values()
            ->all();
    }

    private function fmtInt(mixed $n): string
    {
        return number_format((int) ($n ?? 0), 0, ',', '.');
    }

    private function fmtNum(mixed $n): string
    {
        if ($n === null || $n === '' || ! is_numeric($n)) {
            return '—';
        }

        return number_format((float) $n, 1, ',', '.');
    }

    private function fmtPct(mixed $n): string
    {
        if ($n === null || $n === '' || ! is_numeric($n)) {
            return '—';
        }

        return number_format((float) $n, 1, ',', '.').'%';
    }
}
