<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Parse\CampaignParseService;
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
    ) {}

    /**
     * @return array{
     *   dashboard: array<string, mixed>,
     *   themes: list<array<string, mixed>>,
     *   diagnostico_geral: array<string, mixed>,
     *   schools_triade: list<array<string, mixed>>,
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
            $this->themeRede($dashboard, $findings),
            $this->themeMatriculas($dashboard, $findings),
            $this->themeInclusao($dashboard, $findings),
            $this->themeDistorcao($dashboard, $findings),
            $this->themeDemografia($dashboard, $findings),
            $this->themeTransporte($dashboard, $findings),
            $this->themeTemposEscolares($dashboard, $findings),
            $this->themeDensidade($dashboard, $findings),
        ], static fn (?array $theme): bool => $theme !== null));

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
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeRede(array $dashboard, Collection $findings): ?array
    {
        $counters = is_array($dashboard['counters'] ?? null) ? $dashboard['counters'] : [];
        $triade = is_array($dashboard['triade'] ?? null) ? $dashboard['triade'] : [];
        $buckets = is_array($dashboard['collection_buckets'] ?? null) ? $dashboard['collection_buckets'] : [];

        $kpis = [
            ['label' => __('Escolas ativas'), 'value' => $this->fmtInt($counters['schools_active'] ?? 0)],
            ['label' => __('Tríade completa'), 'value' => $this->fmtInt($counters['schools_triade'] ?? 0)],
            ['label' => __('Cobertura tríade'), 'value' => $this->fmtPct($triade['pct'] ?? null)],
            ['label' => __('Com erros'), 'value' => $this->fmtInt($counters['schools_with_errors'] ?? 0)],
        ];

        $table = [
            'headers' => [__('Indicador'), __('Qtd.')],
            'rows' => [
                [__('Em andamento'), $this->fmtInt($buckets['em_andamento'] ?? 0)],
                [__('Não iniciou'), $this->fmtInt($buckets['nao_iniciou'] ?? 0)],
                [__('Fechada'), $this->fmtInt($buckets['fechada'] ?? 0)],
                [__('Bloqueada'), $this->fmtInt($buckets['bloqueada'] ?? 0)],
                [__('Incompletas (tríade)'), $this->fmtInt($counters['schools_incomplete'] ?? 0)],
                [__('Completas sem erro'), $this->fmtInt($counters['schools_ok'] ?? 0)],
            ],
        ];

        return $this->makeTheme(
            key: 'rede',
            title: __('Rede e cobertura da tríade'),
            lead: __('Retrato da coleta: escolas em atividade, completude aluno+turma+profissional e andamento declarado.'),
            kpis: $kpis,
            diagnosis: $this->highlightSummary($dashboard, ['INF-COL', 'INF-ESC', 'INF-COE']),
            tables: [$table],
            findings: $this->findingsFor($findings, ['TRIAD', 'COL', 'COE', 'DUP']),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function themeMatriculas(array $dashboard, Collection $findings): ?array
    {
        $report = is_array($dashboard['report'] ?? null) ? $dashboard['report'] : [];
        if (empty($report['available'])) {
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

        $tables = [];
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

        $etapaRows = [];
        foreach (array_slice(is_array($report['matriculas_por_ano'] ?? null) ? $report['matriculas_por_ano'] : [], 0, 12) as $bar) {
            if (! is_array($bar)) {
                continue;
            }
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

        return $this->makeTheme(
            key: 'matriculas',
            title: __('Matrículas e etapas'),
            lead: __('Totais curriculares, AEE e atividade complementar, com coerência entre Acompanhamento e Relações.'),
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
    private function themeInclusao(array $dashboard, Collection $findings): ?array
    {
        $profile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
        $reportInc = is_array(($dashboard['report']['inclusion'] ?? null)) ? $dashboard['report']['inclusion'] : [];

        if (empty($profile['available']) && ! $this->hasHighlight($dashboard, 'INF-NEE') && ! $this->hasHighlight($dashboard, 'INF-GAP')) {
            return null;
        }

        $kpis = [
            ['label' => __('Com marcador NEE'), 'value' => $this->fmtInt($profile['nee_flagged'] ?? $reportInc['flagged'] ?? 0)],
            ['label' => __('Alunos lidos'), 'value' => $this->fmtInt($profile['scanned'] ?? $reportInc['scanned'] ?? 0)],
            ['label' => __('Deficiências'), 'value' => $this->fmtInt($profile['deficiency_flagged'] ?? 0)],
            ['label' => __('Transtornos'), 'value' => $this->fmtInt($profile['disorder_flagged'] ?? 0)],
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

        $diagnosis = $this->highlightSummary($dashboard, ['INF-NEE', 'INF-GAP']);
        foreach (['nee_note_def_vs_trs', 'nee_note_sub'] as $noteKey) {
            $note = trim((string) ($profile[$noteKey] ?? ''));
            if ($note !== '') {
                $diagnosis[] = $note;
            }
        }

        return $this->makeTheme(
            key: 'inclusao',
            title: __('Inclusão e educação especial'),
            lead: __('Sinais de NEE/TEA/AH nas Relações e coerência com turmas AEE — leitura indicativa, sem dados pessoais.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: $tables,
            findings: $this->findingsFor($findings, ['NEE', 'AEE', 'GAP']),
        );
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

        $density = is_array($stage['density'] ?? null) ? $stage['density'] : [];

        $kpis = [
            ['label' => __('Distorção rede'), 'value' => $this->fmtPct($dis['pct'] ?? null)],
            ['label' => __('Alunos elegíveis'), 'value' => $this->fmtInt($dis['eligible'] ?? 0)],
            ['label' => __('Com distorção'), 'value' => $this->fmtInt($dis['distorcao'] ?? 0)],
            ['label' => __('Densidade média'), 'value' => $this->fmtNum($density['media'] ?? null)],
        ];

        $rows = [];
        foreach (array_slice(is_array($dis['by_etapa'] ?? null) ? $dis['by_etapa'] : [], 0, 12) as $info) {
            if (! is_array($info)) {
                continue;
            }
            $rows[] = [
                (string) ($info['etapa'] ?? '—'),
                $this->fmtInt($info['eligible'] ?? 0),
                $this->fmtInt($info['distorcao'] ?? 0),
                $this->fmtPct($info['pct'] ?? null),
            ];
        }

        $tables = $rows === [] ? [] : [[
            'title' => __('Distorção por etapa'),
            'headers' => [__('Etapa'), __('Elegíveis'), __('Distorção'), __('%')],
            'rows' => $rows,
        ]];

        $diagnosis = $this->highlightSummary($dashboard, ['INF-DIS']);
        $note = trim((string) ($dis['note'] ?? $dis['summary'] ?? ''));
        if ($note !== '') {
            array_unshift($diagnosis, $note);
        }

        return $this->makeTheme(
            key: 'distorcao',
            title: __('Distorção idade-série'),
            lead: __('Estimativa INEP: alunos com 2 ou mais anos acima da idade esperada para a série.'),
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
        if (empty($stage['available']) && ! $this->hasHighlight($dashboard, 'INF-DEN') && ! $this->hasHighlight($dashboard, 'INF-DOC')) {
            return null;
        }

        $density = is_array($stage['density'] ?? null) ? $stage['density'] : [];
        $staff = is_array($stage['staff'] ?? null) ? $stage['staff'] : [];

        $kpis = [
            ['label' => __('Densidade média'), 'value' => $this->fmtNum($density['media'] ?? null)],
            ['label' => __('Turmas ≥ 40'), 'value' => $this->fmtInt($density['turmas_ge_40'] ?? 0)],
            ['label' => __('Turmas sem aluno'), 'value' => $this->fmtInt($density['turmas_sem_aluno'] ?? 0)],
            ['label' => __('Vínculos profissionais'), 'value' => $this->fmtInt($staff['rows'] ?? 0)],
        ];

        $diagnosis = $this->highlightSummary($dashboard, ['INF-DEN', 'INF-DOC']);
        foreach ([$density['summary'] ?? null, $staff['summary'] ?? null] as $summary) {
            $summary = trim((string) $summary);
            if ($summary !== '') {
                $diagnosis[] = $summary;
            }
        }

        return $this->makeTheme(
            key: 'densidade',
            title: __('Densidade e profissionais'),
            lead: __('Alunos por turma e volume de profissionais nas relações — pressão operacional da rede.'),
            kpis: $kpis,
            diagnosis: array_slice(array_values(array_unique($diagnosis)), 0, 6),
            tables: [],
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
            'title' => $title,
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
