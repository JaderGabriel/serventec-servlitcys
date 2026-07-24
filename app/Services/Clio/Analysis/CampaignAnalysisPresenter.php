<?php

namespace App\Services\Clio\Analysis;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use App\Services\Clio\Support\ClioUserCopy;
use Illuminate\Support\Collection;

/**
 * Consolida cobertura, inferências e achados numa visão legível para o painel analítico.
 */
final class CampaignAnalysisPresenter
{
    /**
     * Situação de funcionamento que tira a escola do escopo operacional da coleta
     * (não deve parecer «incompleta» / dados em aberto).
     */
    public static function isInactiveFunctioning(?string $status): bool
    {
        $s = mb_strtolower(trim((string) $status));
        if ($s === '') {
            return false;
        }

        return str_contains($s, 'extint')
            || str_contains($s, 'paralis')
            || str_contains($s, 'paraliz')
            || str_contains($s, 'reforma')
            || str_contains($s, 'cessad')
            || str_contains($s, 'desativad')
            || str_contains($s, 'fora de atividade')
            || str_contains($s, 'não em atividade')
            || str_contains($s, 'nao em atividade');
    }

    /**
     * Rótulo curto para o chip (usa o texto do Acomp quando reconhecível).
     */
    public static function inactiveStatusLabel(?string $functioning): string
    {
        $raw = trim((string) $functioning);
        $s = mb_strtolower($raw);
        if (str_contains($s, 'extint')) {
            return __('Extinta');
        }
        if (str_contains($s, 'paralis') || str_contains($s, 'paraliz')) {
            return __('Paralisada');
        }
        if (str_contains($s, 'reforma')) {
            return __('Em reforma');
        }
        if ($raw !== '') {
            return $raw;
        }

        return __('Fora de atividade');
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>
     */
    public function present(ClioCampaign $campaign, array $coverage, Collection $inferences, Collection $findings): array
    {
        $schools = collect($coverage['schools'] ?? []);
        $totalSchools = (int) ($coverage['schools_total'] ?? $schools->count());

        $errors = $findings->where('severity', ClioCampaignFinding::SEVERITY_ERROR);
        $warnings = $findings->where('severity', ClioCampaignFinding::SEVERITY_WARNING);
        $infos = $findings->where('severity', ClioCampaignFinding::SEVERITY_INFO);

        $schoolRows = $schools->map(function (array $row) use ($campaign, $errors, $warnings) {
            $school = $campaign->schools->firstWhere('inep_code', $row['inep']);
            $schoolErrors = $school
                ? $errors->where('school_id', $school->id)->count()
                : 0;
            $schoolWarnings = $school
                ? $warnings->where('school_id', $school->id)->count()
                : 0;

            $missing = [];
            if (! ($row['aluno'] ?? false)) {
                $missing[] = __('Alunos');
            }
            if (! ($row['turma'] ?? false)) {
                $missing[] = __('Turmas');
            }
            if (! ($row['profissional'] ?? false)) {
                $missing[] = __('Profissionais');
            }

            $functioning = $school?->functioning_status ?: __('Não informado');
            $inactive = self::isInactiveFunctioning($school?->functioning_status);
            $statusNote = null;

            if ($inactive) {
                $status = self::inactiveStatusLabel($school?->functioning_status);
                $tone = 'slate';
                $filter = 'inactive';
                $statusNote = __('Fora de atividade — a falta de arquivos não é pendência de coleta.');
                $missing = [];
            } elseif ($schoolErrors > 0) {
                $status = __('Com erros');
                $tone = 'rose';
                $filter = 'errors';
            } elseif ($row['triade'] ?? false) {
                $status = __('Completa');
                $tone = 'emerald';
                $filter = 'complete';
            } elseif ($missing !== []) {
                $status = __('Incompleta');
                $tone = 'amber';
                $filter = 'incomplete';
            } else {
                $status = __('Sem arquivos');
                $tone = 'slate';
                $filter = 'empty';
            }

            return [
                'inep' => $row['inep'],
                'name' => $row['name'],
                'collection_form' => $school?->collection_form ?: ($school?->functioning_status ?: '—'),
                'dependency' => $school?->dependency ?: __('Não informado'),
                'functioning' => $functioning,
                'location' => is_array($school?->meta) ? (string) ($school->meta['location'] ?? '') : '',
                'acomp_curricular' => is_array($school?->meta) && is_numeric($school->meta['total_curricular'] ?? null)
                    ? (int) $school->meta['total_curricular']
                    : null,
                'acomp_aee' => is_array($school?->meta) && is_numeric($school->meta['total_aee'] ?? null)
                    ? (int) $school->meta['total_aee']
                    : null,
                'acomp_ac' => is_array($school?->meta) && is_numeric($school->meta['total_ac'] ?? null)
                    ? (int) $school->meta['total_ac']
                    : null,
                'blocked' => $this->isSchoolBlocked($school),
                'inactive' => $inactive,
                'status_note' => $statusNote,
                'triade' => (bool) ($row['triade'] ?? false),
                'aluno' => (bool) ($row['aluno'] ?? false),
                'turma' => (bool) ($row['turma'] ?? false),
                'profissional' => (bool) ($row['profissional'] ?? false),
                'missing' => $missing,
                'status' => $status,
                'tone' => $tone,
                'filter' => $filter,
                'errors' => $schoolErrors,
                'warnings' => $schoolWarnings,
            ];
        })->sort(function (array $a, array $b): int {
            // Ativas com problema primeiro; fora de atividade por último.
            $rank = [
                'errors' => 0,
                'incomplete' => 1,
                'empty' => 2,
                'complete' => 3,
                'inactive' => 4,
            ];
            $ra = $rank[$a['filter'] ?? ''] ?? 9;
            $rb = $rank[$b['filter'] ?? ''] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        })->values();

        $schoolsActive = $schoolRows->where('inactive', false)->values();
        $schoolsOther = $schoolRows->where('inactive', true)->values();
        $activeTotal = $schoolsActive->count();
        $otherTotal = $schoolsOther->count();
        $activeTriadeComplete = $schoolsActive->where('triade', true)->count();
        $activeTriadePct = $activeTotal > 0 ? round(100 * $activeTriadeComplete / $activeTotal, 1) : 0.0;
        $activeWithAluno = $schoolsActive->where('aluno', true)->count();
        $activeWithTurma = $schoolsActive->where('turma', true)->count();
        $activeWithProf = $schoolsActive->where('profissional', true)->count();
        $activeOk = $schoolsActive->filter(function (array $row) {
            return ($row['triade'] ?? false) && (int) ($row['errors'] ?? 0) === 0;
        })->count();

        $mat = $inferences->get('INF-MAT');
        $matPayload = is_array($mat?->payload) ? $mat->payload : [];
        $col = $inferences->get('INF-COL');
        $colBuckets = is_array($col?->payload['buckets'] ?? null) ? $col->payload['buckets'] : [];

        $kpis = [
            [
                'label' => __('Escolas em atividade'),
                'value' => number_format($activeTotal),
                'hint' => $otherTotal > 0
                    ? __('+:n fora de atividade (extinta/paralisada/reforma)', ['n' => $otherTotal])
                    : ($coverage['has_acomp'] ?? false
                        ? __('Com relatório de acompanhamento')
                        : __('Sem Acomp municipal ainda')),
                'tone' => 'sky',
            ],
            [
                'label' => __('Tríade completa'),
                'value' => number_format($activeTriadePct, 1, ',', '.').'%',
                'hint' => __(':ok de :total escolas ativas com aluno, turma e profissional', [
                    'ok' => $activeTriadeComplete,
                    'total' => $activeTotal,
                ]),
                'tone' => $activeTriadePct >= 80 ? 'emerald' : ($activeTriadePct >= 40 ? 'amber' : 'rose'),
            ],
            [
                'label' => __('Erros a corrigir'),
                'value' => number_format($errors->count()),
                'hint' => $errors->isEmpty()
                    ? __('Nenhum erro crítico listado')
                    : __('Cada item pede correção antes de fechar a coleta'),
                'tone' => $errors->isEmpty() ? 'emerald' : 'rose',
            ],
            [
                'label' => __('Pontos de atenção'),
                'value' => number_format($warnings->count()),
                'hint' => $warnings->isEmpty()
                    ? __('Nenhum aviso pendente')
                    : __('Revisar — podem não bloquear, mas merecem conferência'),
                'tone' => $warnings->isEmpty() ? 'emerald' : 'amber',
            ],
            [
                'label' => __('Matrículas (Acomp)'),
                'value' => number_format((int) ($matPayload['acomp_curricular_sum'] ?? 0)),
                'hint' => __('Curriculares no Acompanhamento · Relação aluno: :n', [
                    'n' => number_format((int) ($matPayload['relacao_aluno_rows'] ?? 0)),
                ]),
                'tone' => 'sky',
            ],
            [
                'label' => __('Escolas em boa forma'),
                'value' => number_format($activeOk),
                'hint' => __('Ativas com tríade completa e sem erro associado'),
                'tone' => $activeOk > 0 ? 'emerald' : 'slate',
            ],
        ];

        $schoolFilters = [
            [
                'key' => 'all',
                'label' => __('Todas (ativas)'),
                'hint' => __('Escolas em atividade nesta coleta.'),
                'count' => $activeTotal,
            ],
            [
                'key' => 'errors',
                'label' => __('Com erros'),
                'hint' => __('Escolas ativas com pelo menos um erro a corrigir.'),
                'count' => $schoolsActive->where('filter', 'errors')->count(),
            ],
            [
                'key' => 'incomplete',
                'label' => __('Incompletas'),
                'hint' => __('Falta arquivo da tríade (alunos, turmas ou profissionais) — só escolas em atividade.'),
                'count' => $schoolsActive->where('filter', 'incomplete')->count(),
            ],
            [
                'key' => 'complete',
                'label' => __('Completas'),
                'hint' => __('Tríade ok e sem erro associado.'),
                'count' => $schoolsActive->where('filter', 'complete')->count(),
            ],
            [
                'key' => 'attention',
                'label' => __('Com avisos'),
                'hint' => __('Têm pontos de atenção (avisos), com ou sem erro.'),
                'count' => $schoolsActive->filter(fn (array $r) => ($r['warnings'] ?? 0) > 0)->count(),
            ],
        ];

        $highlights = [];
        foreach (['INF-COL', 'INF-ESC', 'INF-MAT', 'INF-TUR', 'INF-DOC', 'INF-NEE', 'INF-TRA', 'INF-JOR', 'INF-DEM', 'INF-DIS', 'INF-DEN', 'INF-COE', 'INF-DUP', 'INF-DELTA', 'INF-XCHK', 'INF-GAP'] as $code) {
            $inf = $inferences->get($code);
            if ($inf === null) {
                continue;
            }
            $highlights[] = [
                'code' => $code,
                'title' => $this->inferenceTitle($code),
                'summary' => $inf->summary,
                'hint' => $this->inferenceHint($code),
            ];
        }

        $report = $this->buildMunicipalReport($inferences, $findings);
        $inactiveIneps = $schoolsOther->pluck('inep')->filter()->all();
        $reportSchools = collect($report['schools'] ?? []);
        $report['schools_active'] = $reportSchools
            ->reject(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->values()
            ->all();
        $report['schools_other'] = $reportSchools
            ->filter(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->values()
            ->all();
        $acomp = $this->buildAcompSection($campaign, $coverage, $inferences);
        $schoolsOverview = $this->buildSchoolsOverview($campaign, $schoolRows);
        $crossChecks = $this->buildCrossChecks($inferences, $findings);
        $neeCensus = app(CampaignNeeCensusBuilder::class)->build($campaign);
        $profile = $this->buildProfileSection($inferences, $neeCensus);
        $stageMetrics = $this->buildStageMetricsSection($inferences);
        $jornada = $this->buildJornadaSection($inferences, $inactiveIneps);
        $transporte = $this->buildTransporteSection($inferences, $inactiveIneps);

        $counters = [
            'errors' => $errors->count(),
            'warnings' => $warnings->count(),
            'infos' => $infos->count(),
            'schools_total' => $totalSchools,
            'schools_active' => $activeTotal,
            'schools_other' => $otherTotal,
            'schools_triade' => $activeTriadeComplete,
            'schools_ok' => $activeOk,
            'schools_with_errors' => $schoolsActive->where('filter', 'errors')->count(),
            'schools_incomplete' => $schoolsActive->where('filter', 'incomplete')->count(),
            'schools_inactive' => $otherTotal,
        ];

        return [
            'kpis' => $kpis,
            'triade' => [
                'pct' => $activeTriadePct,
                'complete' => $activeTriadeComplete,
                'total' => $activeTotal,
                'aluno_pct' => $activeTotal > 0 ? round(100 * $activeWithAluno / $activeTotal, 1) : 0,
                'turma_pct' => $activeTotal > 0 ? round(100 * $activeWithTurma / $activeTotal, 1) : 0,
                'profissional_pct' => $activeTotal > 0 ? round(100 * $activeWithProf / $activeTotal, 1) : 0,
                'aluno' => $activeWithAluno,
                'turma' => $activeWithTurma,
                'profissional' => $activeWithProf,
                'scope' => 'active',
            ],
            'collection_buckets' => [
                'em_andamento' => (int) ($colBuckets['em_andamento'] ?? 0),
                'nao_iniciou' => (int) ($colBuckets['nao_iniciou'] ?? 0),
                'fechada' => (int) ($colBuckets['fechada'] ?? 0),
                'bloqueada' => (int) ($colBuckets['bloqueada'] ?? 0),
            ],
            'acomp' => $acomp,
            'schools_overview' => $schoolsOverview,
            'cross_checks' => $crossChecks,
            'profile' => $profile,
            'stage_metrics' => $stageMetrics,
            'jornada' => $jornada,
            'transporte' => $transporte,
            'report' => $report,
            'highlights' => $highlights,
            'schools' => $schoolRows,
            'schools_active' => $schoolsActive,
            'schools_other' => $schoolsOther,
            'school_filters' => $schoolFilters,
            'glossary' => ClioUserCopy::glossary(),
            'severity_legend' => ClioUserCopy::severityLegend(),
            'counters' => $counters,
            'findings' => [
                'errors' => $errors->values(),
                'warnings' => $warnings->values(),
                'infos' => $infos->values(),
                'error_count' => $errors->count(),
                'warning_count' => $warnings->count(),
                'info_count' => $infos->count(),
            ],
            'has_analysis' => $inferences->isNotEmpty(),
            'reference_date' => $coverage['reference_date'] ?? null,
        ];
    }

    /**
     * Relatório municipal para decisão (turmas, etapas, AEE/AC, escolas).
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>
     */
    private function buildMunicipalReport(Collection $inferences, Collection $findings): array
    {
        $mat = $inferences->get('INF-MAT');
        $tur = $inferences->get('INF-TUR');
        $nee = $inferences->get('INF-NEE');
        $delta = $inferences->get('INF-DELTA');
        $matPayload = is_array($mat?->payload) ? $mat->payload : [];
        $turPayload = is_array($tur?->payload) ? $tur->payload : [];
        $neePayload = is_array($nee?->payload) ? $nee->payload : [];
        $deltaPayload = is_array($delta?->payload) ? $delta->payload : [];
        $agg = new RelationCsvAggregator;

        $turmaBuckets = is_array($turPayload['by_tipo_bucket'] ?? null) ? $turPayload['by_tipo_bucket'] : [
            'curricular' => 0,
            'aee' => 0,
            'atividade_complementar' => 0,
            'outra' => 0,
        ];

        $turmasTotal = (int) ($turPayload['relacao_turma_rows'] ?? 0);
        $alunosTotal = (int) ($matPayload['relacao_aluno_rows'] ?? 0);
        $curricular = (int) ($matPayload['acomp_curricular_sum'] ?? 0);
        $aee = (int) ($matPayload['acomp_aee_sum'] ?? 0);
        $ac = (int) ($matPayload['acomp_ac_sum'] ?? 0);
        $hasAeeCol = (bool) ($matPayload['has_acomp_aee_column'] ?? false);
        $hasAcCol = (bool) ($matPayload['has_acomp_ac_column'] ?? false);

        $turmaSchools = is_array($turPayload['schools'] ?? null) ? $turPayload['schools'] : [];
        $alunoSchools = is_array($matPayload['schools'] ?? null) ? $matPayload['schools'] : [];
        $byInep = [];

        foreach ($turmaSchools as $row) {
            if (! is_array($row) || empty($row['inep'])) {
                continue;
            }
            $byInep[$row['inep']] = [
                'inep' => $row['inep'],
                'name' => $row['name'] ?? $row['inep'],
                'turmas' => (int) ($row['turmas'] ?? 0),
                'turmas_curricular' => (int) ($row['curricular'] ?? 0),
                'turmas_aee' => (int) ($row['aee'] ?? 0),
                'turmas_ac' => (int) ($row['atividade_complementar'] ?? 0),
                'alunos' => 0,
                'acomp_curricular' => null,
                'acomp_aee' => null,
                'acomp_ac' => null,
            ];
        }

        foreach ($alunoSchools as $row) {
            if (! is_array($row) || empty($row['inep'])) {
                continue;
            }
            $inep = $row['inep'];
            if (! isset($byInep[$inep])) {
                $byInep[$inep] = [
                    'inep' => $inep,
                    'name' => $row['name'] ?? $inep,
                    'turmas' => 0,
                    'turmas_curricular' => 0,
                    'turmas_aee' => 0,
                    'turmas_ac' => 0,
                    'alunos' => 0,
                    'acomp_curricular' => null,
                    'acomp_aee' => null,
                    'acomp_ac' => null,
                ];
            }
            $byInep[$inep]['alunos'] = (int) ($row['alunos'] ?? 0);
            $byInep[$inep]['acomp_curricular'] = $row['acomp_curricular'] ?? null;
            $byInep[$inep]['acomp_aee'] = $row['acomp_aee'] ?? null;
            $byInep[$inep]['acomp_ac'] = $row['acomp_ac'] ?? null;
            if (! empty($row['name'])) {
                $byInep[$inep]['name'] = $row['name'];
            }
        }

        $schoolRows = collect($byInep)->map(function (array $row) {
            $deltaCurr = null;
            if ($row['acomp_curricular'] !== null) {
                $deltaCurr = (int) $row['alunos'] - (int) $row['acomp_curricular'];
            }
            $flags = [];
            if ($deltaCurr !== null && $deltaCurr !== 0) {
                $flags[] = __('Diferença curricular');
            }
            if ((int) ($row['acomp_aee'] ?? 0) > 0 && (int) $row['turmas_aee'] === 0) {
                $flags[] = __('AEE sem turma na Relação');
            }
            if ((int) ($row['acomp_ac'] ?? 0) > 0 && (int) $row['turmas_ac'] === 0) {
                $flags[] = __('AC sem turma na Relação');
            }
            if ((int) $row['turmas'] === 0 && (int) $row['alunos'] > 0) {
                $flags[] = __('Alunos sem turma vinculada');
            }

            return [
                ...$row,
                'delta_curricular' => $deltaCurr,
                'flags' => $flags,
                'tone' => $flags === [] ? 'emerald' : 'amber',
            ];
        })->sort(function (array $a, array $b): int {
            $fa = count($a['flags']);
            $fb = count($b['flags']);
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }

            return ($b['alunos'] + $b['turmas']) <=> ($a['alunos'] + $a['turmas']);
        })->values()->take(40)->all();

        $reportCodes = [
            'CLIO-TUR-SEM-CURRICULAR',
            'CLIO-TUR-AEE-AUSENTE',
            'CLIO-TUR-SEM-ETAPA',
            'CLIO-MAT-SEM-ETAPA',
            'CLIO-MAT-SEM-TURMA',
            'CLIO-DELTA-MAT',
            'CLIO-DELTA-AC',
            'CLIO-DELTA-REDE',
            'CLIO-XCHK-ETAPA',
        ];
        $apontamentos = $findings
            ->filter(fn (ClioCampaignFinding $f) => in_array($f->code, $reportCodes, true))
            ->take(25)
            ->map(fn (ClioCampaignFinding $f) => [
                'code' => $f->code,
                'severity' => $f->severity,
                'severity_label' => $f->severityLabel(),
                'message' => $f->message,
                'action' => $f->actionHint(),
                'school' => $f->school?->name,
                'inep' => $f->school?->inep_code,
            ])
            ->values()
            ->all();

        $qualityNotes = [];
        if ($turmasTotal === 0) {
            $qualityNotes[] = __('Ainda não há Relação de turmas interpretada — o quadro por ano/AEE/AC fica limitado.');
        } elseif (empty($turPayload['by_etapa_ensino'])) {
            $qualityNotes[] = __('As turmas não trouxeram Etapa de ensino utilizável para a pirâmide por ano.');
        }
        if (! $hasAeeCol) {
            $qualityNotes[] = __('O Acomp municipal não trouxe coluna de matrículas AEE — usamos o Tipo de turma da Relação quando existir.');
        }
        if (! $hasAcCol) {
            $qualityNotes[] = __('O Acomp municipal não trouxe coluna de Atividade Complementar — usamos o Tipo de turma da Relação quando existir.');
        }
        if ((int) ($neePayload['flagged'] ?? 0) === 0 && (int) ($neePayload['scanned'] ?? 0) > 0) {
            $qualityNotes[] = __('Sem marcadores NEE/TEA/AH detectáveis nas colunas da Relação de alunos.');
        }

        $modalityBars = [
            ['label' => __('Curricular (Acomp)'), 'count' => $curricular, 'tone' => 'sky'],
            ['label' => __('AEE (Acomp)'), 'count' => $aee, 'tone' => 'emerald'],
            ['label' => __('Ativ. complementar (Acomp)'), 'count' => $ac, 'tone' => 'amber'],
        ];
        $modalityMax = max(1, $curricular, $aee, $ac);

        $tipoBars = [
            ['label' => __('Curricular'), 'count' => (int) ($turmaBuckets['curricular'] ?? 0), 'tone' => 'sky'],
            ['label' => __('AEE'), 'count' => (int) ($turmaBuckets['aee'] ?? 0), 'tone' => 'emerald'],
            ['label' => __('Ativ. complementar'), 'count' => (int) ($turmaBuckets['atividade_complementar'] ?? 0), 'tone' => 'amber'],
            ['label' => __('Outras'), 'count' => (int) ($turmaBuckets['outra'] ?? 0), 'tone' => 'slate'],
        ];
        $tipoMax = max(1, ...array_column($tipoBars, 'count'));

        return [
            'available' => $mat !== null || $tur !== null,
            'totals' => [
                [
                    'label' => __('Turmas'),
                    'value' => number_format($turmasTotal),
                    'hint' => __('Linhas na Relação turma'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('Alunos matriculados'),
                    'value' => number_format($alunosTotal),
                    'hint' => __('Linhas na Relação aluno'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('Curricular (Acomp)'),
                    'value' => number_format($curricular),
                    'hint' => __('Total matrículas - Curricular'),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('AEE'),
                    'value' => number_format(max($aee, (int) ($turmaBuckets['aee'] ?? 0))),
                    'hint' => $hasAeeCol
                        ? __('Acomp :a · turmas AEE :t', ['a' => $aee, 't' => (int) ($turmaBuckets['aee'] ?? 0)])
                        : __('Turmas classificadas como AEE: :t', ['t' => (int) ($turmaBuckets['aee'] ?? 0)]),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('Ativ. complementar'),
                    'value' => number_format(max($ac, (int) ($turmaBuckets['atividade_complementar'] ?? 0))),
                    'hint' => $hasAcCol
                        ? __('Acomp :a · turmas AC :t', ['a' => $ac, 't' => (int) ($turmaBuckets['atividade_complementar'] ?? 0)])
                        : __('Turmas classificadas como AC: :t', ['t' => (int) ($turmaBuckets['atividade_complementar'] ?? 0)]),
                    'tone' => 'amber',
                ],
                [
                    'label' => __('Diferenças a revisar'),
                    'value' => number_format((int) ($deltaPayload['divergent_schools'] ?? 0)),
                    'hint' => __('Escolas em que Acomp curricular e Relação de alunos não batem'),
                    'tone' => ((int) ($deltaPayload['divergent_schools'] ?? 0)) > 0 ? 'amber' : 'emerald',
                ],
            ],
            'turmas_por_ano' => $agg->toBars(is_array($turPayload['by_etapa_ensino'] ?? null) ? $turPayload['by_etapa_ensino'] : []),
            'turmas_por_etapa_agregada' => $agg->toBars(is_array($turPayload['by_etapa_agregada'] ?? null) ? $turPayload['by_etapa_agregada'] : []),
            'matriculas_por_ano' => $agg->toBars(is_array($matPayload['by_etapa_ensino'] ?? null) ? $matPayload['by_etapa_ensino'] : []),
            'composicao_turmas' => array_map(fn (array $b) => [
                ...$b,
                'pct' => round(100 * $b['count'] / $tipoMax, 1),
            ], $tipoBars),
            'matricula_modalidade' => array_map(fn (array $b) => [
                ...$b,
                'pct' => round(100 * $b['count'] / $modalityMax, 1),
            ], $modalityBars),
            'mediacao' => $agg->toBars(is_array($turPayload['by_mediacao'] ?? null) ? $turPayload['by_mediacao'] : [], 6),
            'schools' => $schoolRows,
            'apontamentos' => $apontamentos,
            'quality_notes' => $qualityNotes,
            'inclusion' => [
                'flagged' => (int) ($neePayload['flagged'] ?? 0),
                'scanned' => (int) ($neePayload['scanned'] ?? 0),
                'summary' => $nee?->summary,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $coverageRow
     * @param  Collection<int, \App\Models\Clio\ClioCampaignFinding>  $findings
     * @return array<string, mixed>
     */
    /**
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @param  Collection<string, ClioCampaignInference>|null  $inferences
     * @return array<string, mixed>
     */
    public function presentSchool(
        \App\Models\Clio\ClioCampaignSchool $school,
        ?array $coverageRow,
        Collection $findings,
        ?Collection $inferences = null,
    ): array {
        $coverageRow = $coverageRow ?? [
            'inep' => $school->inep_code,
            'name' => $school->name,
            'aluno' => false,
            'turma' => false,
            'profissional' => false,
            'triade' => false,
        ];

        $aluno = (bool) ($coverageRow['aluno'] ?? false);
        $turma = (bool) ($coverageRow['turma'] ?? false);
        $profissional = (bool) ($coverageRow['profissional'] ?? false);
        $triade = (bool) ($coverageRow['triade'] ?? false);
        $partsOk = (int) $aluno + (int) $turma + (int) $profissional;
        $triadePct = round(100 * $partsOk / 3, 1);

        $errors = $findings->where('severity', ClioCampaignFinding::SEVERITY_ERROR);
        $warnings = $findings->where('severity', ClioCampaignFinding::SEVERITY_WARNING);
        $infos = $findings->where('severity', ClioCampaignFinding::SEVERITY_INFO);

        $missing = [];
        if (! $aluno) {
            $missing[] = __('Alunos');
        }
        if (! $turma) {
            $missing[] = __('Turmas');
        }
        if (! $profissional) {
            $missing[] = __('Profissionais');
        }

        $inactive = self::isInactiveFunctioning($school->functioning_status);
        if ($inactive) {
            $status = self::inactiveStatusLabel($school->functioning_status);
            $tone = 'slate';
            $statusHint = __('Fora de atividade — a falta de arquivos não é pendência de coleta.');
            $missing = [];
        } elseif ($errors->isNotEmpty()) {
            $status = __('Com erros');
            $tone = 'rose';
            $statusHint = __('Há apontamentos que pedem correção nesta escola.');
        } elseif ($triade) {
            $status = __('Completa');
            $tone = 'emerald';
            $statusHint = __('Tríade presente e sem erro associado.');
        } elseif ($missing !== []) {
            $status = __('Incompleta');
            $tone = 'amber';
            $statusHint = __('Falta: :m', ['m' => implode(', ', $missing)]);
        } else {
            $status = __('Sem arquivos');
            $tone = 'slate';
            $statusHint = __('Ainda não há relações ligadas a esta escola.');
        }

        $meta = is_array($school->meta) ? $school->meta : [];
        $matAcomp = is_numeric($meta['total_curricular'] ?? null) ? (int) $meta['total_curricular'] : null;
        $aeeAcomp = is_numeric($meta['total_aee'] ?? null) ? (int) $meta['total_aee'] : null;
        $acAcomp = is_numeric($meta['total_ac'] ?? null) ? (int) $meta['total_ac'] : null;
        $confirmar = is_numeric($meta['matriculas_a_confirmar'] ?? null) ? (int) $meta['matriculas_a_confirmar'] : null;
        $blocked = $this->isSchoolBlocked($school);
        $location = trim((string) ($meta['location'] ?? ''));

        $artifacts = $school->artifacts ?? collect();
        $rowsAluno = (int) $artifacts->where('kind', 'relacao_aluno_escola')->sum('row_count');
        $rowsTurma = (int) $artifacts->where('kind', 'relacao_turma_escola')->sum('row_count');
        $rowsProf = (int) $artifacts->where('kind', 'relacao_profissional_escola')->sum('row_count');

        $deltaCurr = $matAcomp !== null ? $rowsAluno - $matAcomp : null;

        $kpis = [
            [
                'label' => __('Situação geral'),
                'value' => $status,
                'hint' => $statusHint,
                'tone' => $tone,
            ],
            [
                'label' => __('Tríade de arquivos'),
                'value' => number_format($triadePct, 0).'%',
                'hint' => __(':n de 3 arquivos presentes', ['n' => $partsOk]),
                'tone' => $triade ? 'emerald' : ($partsOk > 0 ? 'amber' : 'rose'),
            ],
            [
                'label' => __('Erros a corrigir'),
                'value' => number_format($errors->count()),
                'hint' => $errors->isEmpty() ? __('Nenhum erro nesta escola') : __('Priorize estes pontos'),
                'tone' => $errors->isEmpty() ? 'emerald' : 'rose',
            ],
            [
                'label' => __('Pontos de atenção'),
                'value' => number_format($warnings->count()),
                'hint' => __('Avisos que merecem revisão'),
                'tone' => $warnings->isEmpty() ? 'emerald' : 'amber',
            ],
            [
                'label' => __('Alunos (Relação)'),
                'value' => number_format($rowsAluno),
                'hint' => $matAcomp !== null
                    ? __('Acomp curricular: :n', ['n' => number_format($matAcomp)])
                    : __('Na relação de alunos enviada'),
                'tone' => $deltaCurr !== null && $deltaCurr !== 0 ? 'amber' : 'sky',
            ],
            [
                'label' => __('Turmas / profissionais'),
                'value' => number_format($rowsTurma).' / '.number_format($rowsProf),
                'hint' => __(':n arquivo(s) nesta escola', ['n' => $artifacts->count()]),
                'tone' => 'sky',
            ],
        ];

        $triadeParts = [
            [
                'key' => 'aluno',
                'label' => __('Alunos'),
                'ok' => $aluno,
                'rows' => $rowsAluno,
                'hint' => $inactive
                    ? __('Não exigido — escola fora de atividade')
                    : ($aluno ? __('Arquivo presente') : __('Arquivo em falta')),
            ],
            [
                'key' => 'turma',
                'label' => __('Turmas'),
                'ok' => $turma,
                'rows' => $rowsTurma,
                'hint' => $inactive
                    ? __('Não exigido — escola fora de atividade')
                    : ($turma ? __('Arquivo presente') : __('Arquivo em falta')),
            ],
            [
                'key' => 'profissional',
                'label' => __('Profissionais'),
                'ok' => $profissional,
                'rows' => $rowsProf,
                'hint' => $inactive
                    ? __('Não exigido — escola fora de atividade')
                    : ($profissional ? __('Arquivo presente') : __('Arquivo em falta')),
            ],
        ];

        $files = $artifacts->map(function ($artifact) {
            $status = match ($artifact->parse_status) {
                'ok', 'parsed' => __('Interpretado'),
                'warning' => __('Com avisos'),
                'failed' => __('Falhou'),
                'pending' => __('Aguardando'),
                default => $artifact->parse_status ?: __('—'),
            };
            $tone = match ($artifact->parse_status) {
                'ok', 'parsed' => 'emerald',
                'warning' => 'amber',
                'failed' => 'rose',
                default => 'slate',
            };

            return [
                'kind_label' => $artifact->kindLabel(),
                'original_name' => $artifact->original_name,
                'rows' => $artifact->row_count,
                'status' => $status,
                'tone' => $tone,
            ];
        })->values();

        $analytics = $this->buildSchoolAnalytics($school, $inferences ?? collect(), $school->campaign);

        return [
            'kpis' => $kpis,
            'status' => $status,
            'tone' => $tone,
            'status_hint' => $statusHint,
            'inactive' => $inactive,
            'triade' => [
                'ok' => $triade,
                'pct' => $triadePct,
                'parts' => $triadeParts,
                'missing' => $missing,
            ],
            'context' => [
                'inep' => $school->inep_code,
                'name' => $school->name,
                'functioning' => $school->functioning_status ?: __('Não informado'),
                'collection_form' => $school->collection_form ?: __('Não informado'),
                'dependency' => $school->dependency ?: __('Não informado'),
                'location' => $location !== '' ? $location : __('Não informado'),
                'blocked' => $blocked,
                'blocked_label' => $blocked ? __('Sim') : __('Não'),
                'acomp_curricular' => $matAcomp,
                'acomp_aee' => $aeeAcomp,
                'acomp_ac' => $acAcomp,
                'matriculas_a_confirmar' => $confirmar,
                'relacao_alunos' => $rowsAluno,
                'relacao_turmas' => $rowsTurma,
                'relacao_profissionais' => $rowsProf,
                'delta_curricular' => $deltaCurr,
            ],
            'analytics' => $analytics,
            'files' => $files,
            'glossary' => ClioUserCopy::glossary(),
            'severity_legend' => ClioUserCopy::severityLegend(),
            'findings' => [
                'errors' => $errors->values(),
                'warnings' => $warnings->values(),
                'infos' => $infos->values(),
                'error_count' => $errors->count(),
                'warning_count' => $warnings->count(),
                'info_count' => $infos->count(),
            ],
            'summary' => $triade && $errors->isEmpty()
                ? __('Esta escola está em boa forma na coleta: tríade completa e sem erros.')
                : ($errors->isNotEmpty()
                    ? __('Há erros a corrigir antes de considerar esta escola concluída.')
                    : __('Ainda faltam arquivos ou há pontos de atenção nesta escola.')),
        ];
    }

    /**
     * Quadro analítico da escola a partir dos aggregates das relações e fatias das inferências municipais.
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @return array<string, mixed>
     */
    private function buildSchoolAnalytics(
        \App\Models\Clio\ClioCampaignSchool $school,
        Collection $inferences,
        ?ClioCampaign $campaign = null,
    ): array {
        $agg = new RelationCsvAggregator;
        $inep = (string) $school->inep_code;
        $alunoAgg = $this->mergeArtifactAggregates($school, 'relacao_aluno_escola');
        $turmaAgg = $this->mergeArtifactAggregates($school, 'relacao_turma_escola');
        $profAgg = $this->mergeArtifactAggregates($school, 'relacao_profissional_escola');

        $matSchool = $this->findInferenceSchoolRow($inferences->get('INF-MAT'), $inep);
        $turSchool = $this->findInferenceSchoolRow($inferences->get('INF-TUR'), $inep);
        $traSchool = $this->findInferenceSchoolRow($inferences->get('INF-TRA'), $inep);
        $jorSchool = $this->findInferenceSchoolRow($inferences->get('INF-JOR'), $inep);

        $meta = is_array($school->meta) ? $school->meta : [];
        $acompCurr = is_numeric($meta['total_curricular'] ?? null) ? (int) $meta['total_curricular'] : null;
        $acompAee = is_numeric($meta['total_aee'] ?? null) ? (int) $meta['total_aee'] : null;
        $acompAc = is_numeric($meta['total_ac'] ?? null) ? (int) $meta['total_ac'] : null;
        $alunos = (int) ($alunoAgg['total'] ?? 0);
        $turmas = (int) ($turmaAgg['total'] ?? 0);
        $profissionais = (int) ($profAgg['total'] ?? 0);
        $delta = $acompCurr !== null ? $alunos - $acompCurr : null;

        $turmaBuckets = is_array($turmaAgg['by_tipo_bucket'] ?? null) ? $turmaAgg['by_tipo_bucket'] : [];
        $tipoBars = [
            ['label' => __('Curricular'), 'count' => (int) ($turmaBuckets['curricular'] ?? 0), 'tone' => 'sky'],
            ['label' => __('AEE'), 'count' => (int) ($turmaBuckets['aee'] ?? 0), 'tone' => 'emerald'],
            ['label' => __('Ativ. complementar'), 'count' => (int) ($turmaBuckets['atividade_complementar'] ?? 0), 'tone' => 'amber'],
            ['label' => __('Outras'), 'count' => (int) ($turmaBuckets['outra'] ?? 0), 'tone' => 'slate'],
        ];
        $tipoMax = max(1, ...array_column($tipoBars, 'count'));
        $tipoBars = array_map(static fn (array $b): array => [
            ...$b,
            'pct' => round(100 * $b['count'] / $tipoMax, 1),
        ], $tipoBars);

        $ageGrade = is_array($alunoAgg['age_grade'] ?? null) ? $alunoAgg['age_grade'] : [];
        $eligible = (int) ($ageGrade['eligible'] ?? 0);
        $distorcao = (int) ($ageGrade['distorcao'] ?? 0);
        $pctDis = $eligible > 0 ? round(100 * $distorcao / $eligible, 1) : null;
        $etapaDis = [];
        foreach (is_array($ageGrade['by_etapa'] ?? null) ? $ageGrade['by_etapa'] : [] as $etapa => $row) {
            if (! is_array($row)) {
                continue;
            }
            $etapaDis[] = [
                'etapa' => (string) $etapa,
                'eligible' => (int) ($row['eligible'] ?? 0),
                'distorcao' => (int) ($row['distorcao'] ?? 0),
                'atraso_1' => (int) ($row['atraso_1'] ?? 0),
                'adequado' => (int) ($row['adequado'] ?? 0),
                'pct' => $row['pct_distorcao'] ?? null,
            ];
        }
        $etapaDis = (new EtapaLabelOrder)->sortRowsByEtapaKey($etapaDis, 'etapa');

        $byTurmaAluno = is_array($alunoAgg['by_turma'] ?? null) ? $alunoAgg['by_turma'] : [];
        $byTurmaProf = is_array($profAgg['by_turma'] ?? null) ? $profAgg['by_turma'] : [];
        $turmaProfiles = is_array($turmaAgg['turma_profiles'] ?? null) ? $turmaAgg['turma_profiles'] : [];
        $curricularCodes = [];
        foreach ($turmaProfiles as $code => $profile) {
            $bucket = is_array($profile) ? (string) ($profile['bucket'] ?? '') : '';
            if ($bucket === RelationCsvAggregator::BUCKET_CURRICULAR) {
                $curricularCodes[(string) $code] = true;
            }
        }
        if ($curricularCodes === [] && is_array($turmaAgg['turma_codes'] ?? null)) {
            foreach ($turmaAgg['turma_codes'] as $code) {
                $curricularCodes[(string) $code] = true;
            }
        }

        $turmasComAluno = 0;
        $turmasGe40 = 0;
        $maxAlunosTurma = 0;
        $alunosCurricular = 0;
        foreach (array_keys($curricularCodes) as $code) {
            $n = (int) ($byTurmaAluno[$code] ?? 0);
            $alunosCurricular += $n;
            if ($n > 0) {
                $turmasComAluno++;
            }
            if ($n >= 40) {
                $turmasGe40++;
            }
            if ($n > $maxAlunosTurma) {
                $maxAlunosTurma = $n;
            }
        }
        $mediaAlunos = $turmasComAluno > 0
            ? round($alunosCurricular / $turmasComAluno, 1)
            : null;
        $turmasComDocente = count(array_filter($byTurmaProf, static fn ($n) => (int) $n > 0));
        $turmasSemDocente = max(0, count($curricularCodes) - $turmasComDocente);

        $neeCensus = null;
        if ($campaign !== null) {
            if (! $campaign->relationLoaded('artifacts')) {
                $campaign->load(['artifacts']);
            }
            $neeCensus = app(CampaignNeeCensusBuilder::class)->build($campaign, (int) $school->id);
        }
        $liveNee = is_array($neeCensus) && ! empty($neeCensus['available']);

        $cols = is_array($alunoAgg['columns'] ?? null) ? $alunoAgg['columns'] : [];
        $hasTra = (bool) ($cols['transporte'] ?? false)
            || ! empty($alunoAgg['by_transporte'])
            || ! empty($traSchool);

        $jorTurnoSource = (is_array($jorSchool) && is_array($jorSchool['by_turno'] ?? null) && $this->isCountMap($jorSchool['by_turno']))
            ? $jorSchool['by_turno']
            : (is_array($turmaAgg['by_turno'] ?? null) ? $turmaAgg['by_turno'] : []);
        $jorChSource = (is_array($jorSchool) && is_array($jorSchool['by_ch_band'] ?? null) && $this->isCountMap($jorSchool['by_ch_band']))
            ? $jorSchool['by_ch_band']
            : (is_array($turmaAgg['by_ch_band'] ?? null) ? $turmaAgg['by_ch_band'] : []);
        $jorTurno = $agg->enrichTurnoBars($agg->toBars($jorTurnoSource, 8));
        $jorCh = $agg->enrichCargaBars($agg->toBars($jorChSource, 8));

        $traSource = (is_array($traSchool) && is_array($traSchool['by_transporte'] ?? null) && $this->isCountMap($traSchool['by_transporte']))
            ? $traSchool['by_transporte']
            : (is_array($alunoAgg['by_transporte'] ?? null) ? $alunoAgg['by_transporte'] : []);
        $poderSource = (is_array($traSchool) && is_array($traSchool['by_poder_publico'] ?? null) && $this->isCountMap($traSchool['by_poder_publico']))
            ? $traSchool['by_poder_publico']
            : (is_array($alunoAgg['by_poder_publico_transporte'] ?? null) ? $alunoAgg['by_poder_publico_transporte'] : []);
        $veiculoSource = (is_array($traSchool) && is_array($traSchool['by_veiculo'] ?? null) && $this->isCountMap($traSchool['by_veiculo']))
            ? $traSchool['by_veiculo']
            : (is_array($alunoAgg['by_veiculo_transporte'] ?? null) ? $alunoAgg['by_veiculo_transporte'] : []);

        $hasAnyAgg = $alunoAgg !== [] || $turmaAgg !== [] || $profAgg !== [];

        return [
            'available' => $hasAnyAgg || $matSchool !== null || $turSchool !== null,
            'matricula' => [
                'available' => $alunos > 0 || $acompCurr !== null || $matSchool !== null,
                'totals' => [
                    [
                        'label' => __('Alunos (Relação)'),
                        'value' => number_format($alunos),
                        'hint' => __('Linhas na Relação de alunos'),
                        'tone' => 'sky',
                    ],
                    [
                        'label' => __('Curricular (Acomp)'),
                        'value' => $acompCurr === null ? '—' : number_format($acompCurr),
                        'hint' => __('Total matrículas - Curricular'),
                        'tone' => 'emerald',
                    ],
                    [
                        'label' => __('AEE (Acomp)'),
                        'value' => $acompAee === null ? '—' : number_format($acompAee),
                        'hint' => __('Total matrículas - AEE'),
                        'tone' => 'emerald',
                    ],
                    [
                        'label' => __('AC (Acomp)'),
                        'value' => $acompAc === null ? '—' : number_format($acompAc),
                        'hint' => __('Atividade complementar'),
                        'tone' => 'amber',
                    ],
                    [
                        'label' => __('Diferença curricular'),
                        'value' => $delta === null
                            ? '—'
                            : (($delta === 0) ? __('Bate') : (($delta > 0 ? '+' : '').number_format($delta))),
                        'hint' => __('Relação − Acomp curricular'),
                        'tone' => $delta === null ? 'slate' : ($delta === 0 ? 'emerald' : 'amber'),
                    ],
                    [
                        'label' => __('Sem turma / sem etapa'),
                        'value' => number_format((int) ($alunoAgg['without_turma'] ?? 0))
                            .' / '.number_format((int) ($alunoAgg['without_etapa'] ?? 0)),
                        'hint' => __('Linhas sem vínculo de turma ou etapa'),
                        'tone' => ((int) ($alunoAgg['without_turma'] ?? 0) + (int) ($alunoAgg['without_etapa'] ?? 0)) > 0
                            ? 'amber'
                            : 'emerald',
                    ],
                ],
                'por_ano' => $agg->toBars(is_array($alunoAgg['by_etapa_ensino'] ?? null) ? $alunoAgg['by_etapa_ensino'] : [], 16),
            ],
            'turmas' => [
                'available' => $turmas > 0 || $turSchool !== null,
                'totals' => [
                    [
                        'label' => __('Turmas'),
                        'value' => number_format($turmas),
                        'hint' => __('Linhas na Relação de turmas'),
                        'tone' => 'sky',
                    ],
                    [
                        'label' => __('Curricular'),
                        'value' => number_format((int) ($turmaBuckets['curricular'] ?? (is_array($turSchool) ? ($turSchool['curricular'] ?? 0) : 0))),
                        'hint' => __('Tipo de turma curricular'),
                        'tone' => 'sky',
                    ],
                    [
                        'label' => __('AEE'),
                        'value' => number_format((int) ($turmaBuckets['aee'] ?? (is_array($turSchool) ? ($turSchool['aee'] ?? 0) : 0))),
                        'hint' => __('Atendimento educacional especializado'),
                        'tone' => 'emerald',
                    ],
                    [
                        'label' => __('Ativ. complementar'),
                        'value' => number_format((int) ($turmaBuckets['atividade_complementar'] ?? (is_array($turSchool) ? ($turSchool['atividade_complementar'] ?? 0) : 0))),
                        'hint' => __('Turmas de AC'),
                        'tone' => 'amber',
                    ],
                ],
                'composicao' => $tipoBars,
                'por_ano' => $agg->toBars(is_array($turmaAgg['by_etapa_ensino'] ?? null) ? $turmaAgg['by_etapa_ensino'] : [], 16),
                'por_etapa_agregada' => $agg->toBars(is_array($turmaAgg['by_etapa_agregada'] ?? null) ? $turmaAgg['by_etapa_agregada'] : [], 10),
                'por_mediacao' => $agg->toBars(is_array($turmaAgg['by_mediacao'] ?? null) ? $turmaAgg['by_mediacao'] : [], 8),
            ],
            'profile' => [
                'available' => $alunos > 0,
                'scanned' => $liveNee ? (int) ($neeCensus['people_scanned'] ?? 0) : $alunos,
                'privacy_note' => __('Identificadores nas amostras de achados aparecem por completo nesta tela (acesso autenticado). Contagens NEE abaixo são por pessoa.'),
                'by_cor_raca' => $agg->toBars(is_array($alunoAgg['by_cor_raca'] ?? null) ? $alunoAgg['by_cor_raca'] : [], 10),
                'by_sexo' => $agg->toBars(is_array($alunoAgg['by_sexo'] ?? null) ? $alunoAgg['by_sexo'] : [], 6),
                'by_faixa_etaria' => $agg->toBars(is_array($alunoAgg['by_faixa_etaria'] ?? null) ? $alunoAgg['by_faixa_etaria'] : [], 8),
                'by_nee' => $agg->toBars($liveNee ? (array) ($neeCensus['by_nee'] ?? []) : (is_array($alunoAgg['by_nee'] ?? null) ? $alunoAgg['by_nee'] : []), 8),
                'nee_flagged' => $liveNee ? (int) ($neeCensus['flagged'] ?? 0) : (int) ($alunoAgg['nee_flagged'] ?? 0),
                'nee_unit' => $liveNee ? 'people' : 'rows',
                'nee_without_aee' => $liveNee ? (int) ($neeCensus['without_aee'] ?? 0) : null,
                'nee_aee_without_condition' => $liveNee ? (int) ($neeCensus['aee_without_nee'] ?? 0) : null,
                'by_deficiency' => $agg->toBars($liveNee ? (array) ($neeCensus['by_deficiency'] ?? []) : (is_array($alunoAgg['by_deficiency'] ?? null) ? $alunoAgg['by_deficiency'] : []), 10),
                'by_disorder' => $agg->toBars($liveNee ? (array) ($neeCensus['by_disorder'] ?? []) : (is_array($alunoAgg['by_disorder'] ?? null) ? $alunoAgg['by_disorder'] : []), 8),
                'by_ah' => $agg->toBars($liveNee ? (array) ($neeCensus['by_ah'] ?? []) : (is_array($alunoAgg['by_ah'] ?? null) ? $alunoAgg['by_ah'] : []), 4),
                'deficiency_flagged' => $liveNee ? (int) ($neeCensus['deficiency_flagged'] ?? 0) : (int) ($alunoAgg['deficiency_flagged'] ?? 0),
                'disorder_flagged' => $liveNee ? (int) ($neeCensus['disorder_flagged'] ?? 0) : (int) ($alunoAgg['disorder_flagged'] ?? 0),
                'ah_flagged' => $liveNee ? (int) ($neeCensus['ah_flagged'] ?? 0) : (int) ($alunoAgg['ah_flagged'] ?? 0),
                'by_underreporting' => $agg->toBars($liveNee ? (array) ($neeCensus['by_underreporting'] ?? []) : (is_array($alunoAgg['by_underreporting'] ?? null) ? $alunoAgg['by_underreporting'] : []), 8),
                'underreporting_flagged' => $liveNee ? (int) ($neeCensus['underreporting_flagged'] ?? 0) : (int) ($alunoAgg['underreporting_flagged'] ?? 0),
                'columns' => $cols,
            ],
            'jornada' => [
                'available' => $jorSchool !== null || ! empty($turmaAgg['by_turno']) || ! empty($turmaAgg['by_ch_band']),
                'people' => (int) ((is_array($jorSchool) ? ($jorSchool['people'] ?? null) : null) ?? $alunos),
                'fund_aee_contraturno' => (int) (is_array($jorSchool) ? ($jorSchool['fund_aee_contraturno'] ?? 0) : 0),
                'curricular_ac' => (int) (is_array($jorSchool) ? ($jorSchool['curricular_ac'] ?? 0) : 0),
                'infantil_turma_estendida' => (int) (is_array($jorSchool) ? ($jorSchool['infantil_turma_estendida'] ?? 0) : 0),
                'multi_enrollment' => (int) (is_array($jorSchool) ? ($jorSchool['multi_enrollment'] ?? 0) : 0),
                'by_turno' => $jorTurno,
                'by_ch_band' => $jorCh,
            ],
            'transporte' => [
                'available' => $hasTra,
                'flagged' => (int) ((is_array($traSchool) ? ($traSchool['flagged'] ?? null) : null) ?? ($alunoAgg['transporte_flagged'] ?? 0)),
                'without' => (int) ((is_array($traSchool) ? ($traSchool['without'] ?? null) : null) ?? ($alunoAgg['without_transporte'] ?? 0)),
                'pct' => (is_array($traSchool) ? ($traSchool['pct'] ?? null) : null) ?? (
                    $alunos > 0
                        ? round(100 * ((int) ($alunoAgg['transporte_flagged'] ?? 0)) / max(1, $alunos), 1)
                        : null
                ),
                'by_transporte' => $agg->toBars($traSource, 6),
                'by_poder_publico' => $agg->toBars($poderSource, 8),
                'by_veiculo' => $agg->toBars($veiculoSource, 8),
            ],
            'metrics' => [
                'available' => $eligible > 0 || $turmasComAluno > 0 || $profissionais > 0,
                'distortion' => [
                    'pct' => $pctDis,
                    'eligible' => $eligible,
                    'distorcao' => $distorcao,
                    'atraso_1' => (int) ($ageGrade['atraso_1'] ?? 0),
                    'adequado' => (int) ($ageGrade['adequado'] ?? 0),
                    'adiantado' => (int) ($ageGrade['adiantado'] ?? 0),
                    'tone' => $pctDis === null ? 'slate' : ($pctDis >= 20 ? 'rose' : ($pctDis >= 10 ? 'amber' : 'emerald')),
                    'by_etapa' => $etapaDis,
                ],
                'density' => [
                    'media' => $mediaAlunos,
                    'turmas_com_aluno' => $turmasComAluno,
                    'turmas_ge_40' => $turmasGe40,
                    'max' => $maxAlunosTurma,
                    'tone' => $turmasGe40 > 0 ? 'amber' : 'emerald',
                    'scope' => 'curricular',
                    'note' => __('Média e ≥40 apenas em turmas curriculares (AEE/AC fora).'),
                ],
                'staff' => [
                    'rows' => $profissionais,
                    'without_turma' => (int) ($profAgg['without_turma'] ?? 0),
                    'turmas_com_docente' => $turmasComDocente,
                    'turmas_sem_docente' => $turmasSemDocente,
                    'tone' => $turmasSemDocente > 0 || (int) ($profAgg['without_turma'] ?? 0) > 0 ? 'amber' : 'emerald',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeArtifactAggregates(\App\Models\Clio\ClioCampaignSchool $school, string $kind): array
    {
        $merged = [];
        foreach ($school->artifacts ?? [] as $artifact) {
            if (($artifact->kind ?? '') !== $kind) {
                continue;
            }
            $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
            $agg = is_array($meta['aggregates'] ?? null) ? $meta['aggregates'] : [];
            if ($agg === []) {
                continue;
            }
            if ($merged === []) {
                $merged = $agg;

                continue;
            }
            foreach ($agg as $key => $value) {
                if (is_int($value) || is_float($value)) {
                    $merged[$key] = (int) ($merged[$key] ?? 0) + (int) $value;
                } elseif (is_array($value) && $this->isCountMap($value)) {
                    $base = is_array($merged[$key] ?? null) ? $merged[$key] : [];
                    foreach ($value as $label => $count) {
                        if (is_array($count)) {
                            continue;
                        }
                        $base[$label] = (int) ($base[$label] ?? 0) + (int) $count;
                    }
                    $merged[$key] = $base;
                } elseif (! isset($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isCountMap(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findInferenceSchoolRow(?ClioCampaignInference $inference, string $inep): ?array
    {
        if ($inference === null) {
            return null;
        }
        $payload = is_array($inference->payload) ? $inference->payload : [];
        foreach (is_array($payload['schools'] ?? null) ? $payload['schools'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['inep'] ?? '') === $inep) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Destaque do Relatório de Acompanhamento (arquivo geral municipal).
     *
     * @param  array<string, mixed>  $coverage
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @return array<string, mixed>
     */
    private function buildAcompSection(ClioCampaign $campaign, array $coverage, Collection $inferences): array
    {
        $mat = $inferences->get('INF-MAT');
        $matPayload = is_array($mat?->payload) ? $mat->payload : [];
        $hasAcomp = (bool) ($coverage['has_acomp'] ?? false);
        $artifact = $campaign->artifacts->firstWhere('kind', 'acomp_coleta_1etapa');

        $blocked = 0;
        $confirmar = 0;
        $withCurricular = 0;
        foreach ($campaign->schools as $school) {
            if ($this->isSchoolBlocked($school)) {
                $blocked++;
            }
            $meta = is_array($school->meta) ? $school->meta : [];
            if (is_numeric($meta['matriculas_a_confirmar'] ?? null)) {
                $confirmar += (int) $meta['matriculas_a_confirmar'];
            }
            if (is_numeric($meta['total_curricular'] ?? null)) {
                $withCurricular++;
            }
        }

        $curricular = (int) ($matPayload['acomp_curricular_sum'] ?? 0);
        $aee = (int) ($matPayload['acomp_aee_sum'] ?? 0);
        $ac = (int) ($matPayload['acomp_ac_sum'] ?? 0);
        $relacao = (int) ($matPayload['relacao_aluno_rows'] ?? 0);
        $delta = $hasAcomp && $relacao > 0 ? $relacao - $curricular : null;

        return [
            'available' => $hasAcomp,
            'file_name' => $artifact?->original_name,
            'reference_date' => $coverage['reference_date'] ?? null,
            'schools_in_file' => $withCurricular > 0 ? $withCurricular : $campaign->schools->count(),
            'totals' => [
                [
                    'label' => __('Curricular'),
                    'value' => number_format($curricular),
                    'hint' => __('Soma «Total matrículas - Curricular»'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('AEE'),
                    'value' => number_format($aee),
                    'hint' => __('Soma no arquivo geral'),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('Ativ. complementar'),
                    'value' => number_format($ac),
                    'hint' => __('Soma no arquivo geral'),
                    'tone' => 'amber',
                ],
                [
                    'label' => __('A confirmar / desconsiderar'),
                    'value' => number_format($confirmar),
                    'hint' => __('Marcadas no Acomp'),
                    'tone' => $confirmar > 0 ? 'amber' : 'slate',
                ],
                [
                    'label' => __('Escolas bloqueadas'),
                    'value' => number_format($blocked),
                    'hint' => __('Campo «Escola Bloqueada»'),
                    'tone' => $blocked > 0 ? 'rose' : 'emerald',
                ],
                [
                    'label' => __('vs Relação de alunos'),
                    'value' => $delta === null
                        ? '—'
                        : (($delta === 0) ? __('Bate') : (($delta > 0 ? '+' : '').number_format($delta))),
                    'hint' => __('Linhas Relação :r · Acomp curricular :a', [
                        'r' => number_format($relacao),
                        'a' => number_format($curricular),
                    ]),
                    'tone' => $delta === null ? 'slate' : ($delta === 0 ? 'emerald' : 'amber'),
                ],
            ],
            'note' => __('O arquivo geral (Relatório de Acompanhamento) informa totais por escola, não por ano/etapa. Para conferir «1º ano», «2º ano» etc., use a Relação de alunos e a Relação de turmas na seção de cruzamentos.'),
            'missing_hint' => __('Importe o CSV Relatorio_Acomp_Coleta_1Etapa_*.csv para comparar os totais oficiais do portal com as Relações.'),
        ];
    }

    /**
     * Panorama das escolas: tipos (dependência), status e contadores.
     *
     * @param  Collection<int, array<string, mixed>>  $schoolRows
     * @return array<string, mixed>
     */
    private function buildSchoolsOverview(ClioCampaign $campaign, Collection $schoolRows): array
    {
        $byDependency = [];
        $byFunctioning = [];
        $byLocation = [];
        $byCollection = [];

        foreach ($campaign->schools as $school) {
            $dep = $school->dependency ?: __('Não informado');
            $byDependency[$dep] = ($byDependency[$dep] ?? 0) + 1;
            $fun = $school->functioning_status ?: __('Não informado');
            $byFunctioning[$fun] = ($byFunctioning[$fun] ?? 0) + 1;
            $meta = is_array($school->meta) ? $school->meta : [];
            $loc = trim((string) ($meta['location'] ?? ''));
            $loc = $loc !== '' ? $loc : __('Não informado');
            $byLocation[$loc] = ($byLocation[$loc] ?? 0) + 1;
            $col = $school->collection_form ?: __('Não informado');
            $byCollection[$col] = ($byCollection[$col] ?? 0) + 1;
        }

        arsort($byDependency);
        arsort($byFunctioning);
        arsort($byLocation);
        arsort($byCollection);

        $agg = new RelationCsvAggregator;
        $acompSum = 0;
        $aeeSum = 0;
        $acSum = 0;
        foreach ($campaign->schools as $school) {
            $meta = is_array($school->meta) ? $school->meta : [];
            if (is_numeric($meta['total_curricular'] ?? null)) {
                $acompSum += (int) $meta['total_curricular'];
            }
            if (is_numeric($meta['total_aee'] ?? null)) {
                $aeeSum += (int) $meta['total_aee'];
            }
            if (is_numeric($meta['total_ac'] ?? null)) {
                $acSum += (int) $meta['total_ac'];
            }
        }

        return [
            'available' => $campaign->schools->isNotEmpty(),
            'counters' => [
                [
                    'label' => __('Escolas no arquivo geral'),
                    'value' => number_format($campaign->schools->count()),
                    'hint' => __('Com código INEP no Acomp ou nas Relações'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('Completas (tríade)'),
                    'value' => number_format($schoolRows->where('filter', 'complete')->count()),
                    'hint' => __('Sem erro associado'),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('Incompletas'),
                    'value' => number_format($schoolRows->where('filter', 'incomplete')->count()),
                    'hint' => __('Falta arquivo da tríade (só em atividade)'),
                    'tone' => 'amber',
                ],
                [
                    'label' => __('Fora de atividade'),
                    'value' => number_format($schoolRows->where('filter', 'inactive')->count()),
                    'hint' => __('Extintas / paralisadas — sem pendência de coleta'),
                    'tone' => 'slate',
                ],
                [
                    'label' => __('Com erros'),
                    'value' => number_format($schoolRows->where('filter', 'errors')->count()),
                    'hint' => __('Priorize estas unidades'),
                    'tone' => $schoolRows->where('filter', 'errors')->isNotEmpty() ? 'rose' : 'emerald',
                ],
                [
                    'label' => __('Matrículas curriculares (Acomp)'),
                    'value' => number_format($acompSum),
                    'hint' => __('AEE :aee · AC :ac', ['aee' => number_format($aeeSum), 'ac' => number_format($acSum)]),
                    'tone' => 'sky',
                ],
            ],
            'by_dependency' => $agg->toBars($byDependency, 8),
            'by_functioning' => $agg->toBars($byFunctioning, 8),
            'by_location' => $agg->toBars($byLocation, 6),
            'by_collection' => $agg->toBars($byCollection, 8),
        ];
    }

    /**
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  Collection<int, ClioCampaignFinding>  $findings
     * @return array<string, mixed>
     */
    private function buildCrossChecks(Collection $inferences, Collection $findings): array
    {
        $xchk = $inferences->get('INF-XCHK');
        $payload = is_array($xchk?->payload) ? $xchk->payload : [];
        $network = is_array($payload['network'] ?? null) ? $payload['network'] : [];
        $etapaRows = is_array($payload['etapa_compare'] ?? null) ? $payload['etapa_compare'] : [];

        $checks = [];
        if ($payload !== []) {
            $delta = $network['delta_curricular'] ?? null;
            $checks[] = [
                'key' => 'acomp_vs_relacao',
                'title' => __('Arquivo geral × Relação de alunos'),
                'ok' => (bool) ($network['ok'] ?? false),
                'detail' => $delta === null
                    ? __('Sem totais suficientes para comparar.')
                    : ($delta === 0
                        ? __('Os totais curriculares do Acomp batem com as linhas da Relação de alunos.')
                        : __('Diferença :d (Acomp curricular :a · Relação :r).', [
                            'd' => ($delta > 0 ? '+' : '').$delta,
                            'a' => $network['acomp_curricular'] ?? 0,
                            'r' => $network['relacao_aluno_rows'] ?? 0,
                        ])),
                'tone' => ($network['ok'] ?? false) ? 'emerald' : 'amber',
            ];
            $checks[] = [
                'key' => 'aluno_vs_turma_etapa',
                'title' => __('Alunos por ano/etapa × turmas na mesma etapa'),
                'ok' => (bool) ($payload['etapa_ok'] ?? false),
                'detail' => (($payload['etapas_alunos_sem_turma'] ?? 0) === 0)
                    ? __('Cada etapa com alunos na Relação tem ao menos uma turma na Relação de turmas.')
                    : __(':n etapa(s) com alunos sem turma correspondente. O Acomp não informa totais por ano — esta checagem usa só as Relações.', [
                        'n' => $payload['etapas_alunos_sem_turma'] ?? 0,
                    ]),
                'tone' => ($payload['etapa_ok'] ?? false) ? 'emerald' : 'amber',
            ];
        }

        $related = $findings
            ->filter(fn (ClioCampaignFinding $f) => in_array($f->code, [
                'CLIO-DELTA-REDE',
                'CLIO-XCHK-ETAPA',
                'CLIO-DELTA-MAT',
                'CLIO-DELTA-AC',
            ], true))
            ->take(15)
            ->map(fn (ClioCampaignFinding $f) => [
                'code' => $f->code,
                'severity' => $f->severity,
                'severity_label' => $f->severityLabel(),
                'message' => $f->message,
                'action' => $f->actionHint(),
                'school' => $f->school?->name,
                'inep' => $f->school?->inep_code,
            ])
            ->values()
            ->all();

        $order = new EtapaLabelOrder;
        $groupsMap = [];
        foreach ($etapaRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $alunos = (int) ($row['alunos'] ?? 0);
            $turmas = (int) ($row['turmas'] ?? 0);
            if ($alunos === 0 && $turmas === 0) {
                continue;
            }
            $segment = (string) ($row['segment'] ?? $order->segment((string) ($row['etapa'] ?? '')));
            $flag = $row['flag'] ?? null;
            $groupsMap[$segment][] = [
                'etapa' => (string) ($row['etapa'] ?? ''),
                'alunos' => $alunos,
                'turmas' => $turmas,
                'flag' => $flag,
                'segment' => $segment,
                'ok' => $flag === null,
            ];
        }

        $etapaGroups = [];
        foreach ($groupsMap as $segment => $rows) {
            $rows = $order->sortRowsByEtapaKey($rows, 'etapa');
            usort($rows, static function (array $a, array $b) use ($order): int {
                $prio = static fn (?string $f): int => match ($f) {
                    'alunos_sem_turma' => 0,
                    'turma_sem_aluno' => 1,
                    default => 2,
                };
                $pa = $prio($a['flag'] ?? null);
                $pb = $prio($b['flag'] ?? null);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $order->compare((string) ($a['etapa'] ?? ''), (string) ($b['etapa'] ?? ''));
            });
            $issues = count(array_filter($rows, static fn (array $r): bool => ($r['flag'] ?? null) !== null));
            $etapaGroups[] = [
                'key' => $segment,
                'title' => $order->segmentLabel($segment),
                'hint' => $order->segmentHint($segment),
                'tone' => match ($segment) {
                    EtapaLabelOrder::SEGMENT_SERIADA => 'sky',
                    EtapaLabelOrder::SEGMENT_EJA => 'amber',
                    EtapaLabelOrder::SEGMENT_PROFISSIONAL => 'violet',
                    EtapaLabelOrder::SEGMENT_ESPECIAL => 'indigo',
                    EtapaLabelOrder::SEGMENT_COMPLEMENTAR => 'emerald',
                    default => 'slate',
                },
                'rows' => $rows,
                'alunos' => array_sum(array_column($rows, 'alunos')),
                'turmas' => array_sum(array_column($rows, 'turmas')),
                'issues' => $issues,
                'ok' => $issues === 0,
            ];
        }
        usort($etapaGroups, fn (array $a, array $b): int => $order->segmentOrder((string) $a['key']) <=> $order->segmentOrder((string) $b['key']));

        $flatRows = [];
        foreach ($etapaGroups as $group) {
            foreach ($group['rows'] as $row) {
                $flatRows[] = $row;
            }
        }

        return [
            'available' => $payload !== [] || $related !== [],
            'summary' => $xchk?->summary,
            'acomp_note' => $payload['acomp_by_etapa_note']
                ?? __('O arquivo geral não desagrega matrículas por ano/etapa.'),
            'checks' => $checks,
            'etapa_groups' => $etapaGroups,
            'etapa_rows' => $flatRows,
            'findings' => $related,
        ];
    }

    /**
     * Perfil demográfico / inclusão a partir das Relações de alunos (agregados, sem PII).
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  array<string, mixed>|null  $neeCensus  censo por pessoa (mesma base do PDF), quando disponível
     * @return array<string, mixed>
     */
    private function buildProfileSection(Collection $inferences, ?array $neeCensus = null): array
    {
        $dem = $inferences->get('INF-DEM');
        $nee = $inferences->get('INF-NEE');
        $tra = $inferences->get('INF-TRA');
        $demPayload = is_array($dem?->payload) ? $dem->payload : [];
        $neePayload = is_array($nee?->payload) ? $nee->payload : [];
        $traPayload = is_array($tra?->payload) ? $tra->payload : [];
        $cols = is_array($demPayload['columns'] ?? null) ? $demPayload['columns'] : [];
        $agg = new RelationCsvAggregator;

        $liveNee = is_array($neeCensus) && ! empty($neeCensus['available']);
        if ($liveNee) {
            $neePayload = array_merge($neePayload, [
                'flagged' => (int) ($neeCensus['flagged'] ?? 0),
                'scanned' => (int) ($neeCensus['people_scanned'] ?? 0),
                'unit' => 'people',
                'without_aee' => (int) ($neeCensus['without_aee'] ?? 0),
                'aee_without_nee' => (int) ($neeCensus['aee_without_nee'] ?? 0),
                'by_nee' => is_array($neeCensus['by_nee'] ?? null) ? $neeCensus['by_nee'] : [],
                'by_deficiency' => is_array($neeCensus['by_deficiency'] ?? null) ? $neeCensus['by_deficiency'] : [],
                'by_disorder' => is_array($neeCensus['by_disorder'] ?? null) ? $neeCensus['by_disorder'] : [],
                'by_ah' => is_array($neeCensus['by_ah'] ?? null) ? $neeCensus['by_ah'] : [],
                'deficiency_flagged' => (int) ($neeCensus['deficiency_flagged'] ?? 0),
                'disorder_flagged' => (int) ($neeCensus['disorder_flagged'] ?? 0),
                'ah_flagged' => (int) ($neeCensus['ah_flagged'] ?? 0),
                'by_underreporting' => is_array($neeCensus['by_underreporting'] ?? null) ? $neeCensus['by_underreporting'] : [],
                'underreporting_flagged' => (int) ($neeCensus['underreporting_flagged'] ?? 0),
                'has_nee_columns' => (bool) ($neeCensus['has_nee_columns'] ?? false),
                'note_def_vs_trs' => __('Contagem por pessoa (como no PDF). Deficiências (DEF-*) e transtornos (TRS-*) são públicos distintos; AH é categoria própria. «Não se aplica» não conta como marcador.'),
            ]);
        }

        $scanned = (int) ($neePayload['scanned'] ?? $demPayload['scanned'] ?? $traPayload['scanned'] ?? 0);
        $hasTra = (bool) ($cols['transporte'] ?? $traPayload['has_transporte_columns'] ?? false);

        $coverage = [
            [
                'key' => 'cor_raca',
                'label' => __('Cor/Raça'),
                'available' => (bool) ($cols['cor_raca'] ?? false),
                'hint' => ($cols['cor_raca'] ?? false)
                    ? __('Disponível na Relação de alunos')
                    : __('Coluna ausente neste export'),
            ],
            [
                'key' => 'sexo',
                'label' => __('Sexo'),
                'available' => (bool) ($cols['sexo'] ?? false),
                'hint' => ($cols['sexo'] ?? false)
                    ? __('Disponível na Relação de alunos')
                    : __('Coluna ausente neste export'),
            ],
            [
                'key' => 'nascimento',
                'label' => __('Faixa etária'),
                'available' => (bool) ($cols['nascimento'] ?? false),
                'hint' => ($cols['nascimento'] ?? false)
                    ? __('Calculada pela Data de nascimento')
                    : __('Sem Data de nascimento no export'),
            ],
            [
                'key' => 'nee',
                'label' => __('Inclusão (NEE/TEA/AH)'),
                'available' => (bool) ($cols['nee'] ?? $neePayload['has_nee_columns'] ?? false),
                'hint' => (($cols['nee'] ?? false) || ($neePayload['has_nee_columns'] ?? false))
                    ? __('Colunas de deficiência/TEA/AH detectadas')
                    : __('Colunas de inclusão não detectadas'),
            ],
            [
                'key' => 'transporte',
                'label' => __('Transporte escolar'),
                'available' => $hasTra,
                'hint' => $hasTra
                    ? __('Detalhe na secção Transporte escolar (abaixo)')
                    : __('Não detectado neste export'),
            ],
            [
                'key' => 'vulnerabilidade',
                'label' => __('Vulnerabilidade social'),
                'available' => false,
                'hint' => __('Não vem no Educacenso — use CadÚnico / módulo próprio'),
            ],
            [
                'key' => 'distorcao',
                'label' => __('Distorção idade-série'),
                'available' => (bool) ($cols['nascimento'] ?? false),
                'hint' => ($cols['nascimento'] ?? false)
                    ? __('Calculável com nascimento + etapa seriada')
                    : __('Precisa de Data de nascimento + etapa'),
            ],
            [
                'key' => 'rendimento',
                'label' => __('Aprovação / abandono'),
                'available' => false,
                'hint' => __('Só na 2ª etapa (Situação do aluno) — fora deste pacote'),
            ],
        ];

        $dis = $inferences->get('INF-DIS');
        $den = $inferences->get('INF-DEN');
        $doc = $inferences->get('INF-DOC');

        return [
            'available' => $dem !== null || $nee !== null || $dis !== null || $den !== null,
            'summary' => $dem?->summary ?? $nee?->summary,
            'scanned' => $scanned,
            'coverage' => $coverage,
            'by_cor_raca' => $agg->toBars(is_array($demPayload['by_cor_raca'] ?? null) ? $demPayload['by_cor_raca'] : [], 10),
            'by_sexo' => $agg->toBars(is_array($demPayload['by_sexo'] ?? null) ? $demPayload['by_sexo'] : [], 6),
            'by_faixa_etaria' => $agg->toBars(is_array($demPayload['by_faixa_etaria'] ?? null) ? $demPayload['by_faixa_etaria'] : [], 8),
            'by_nee' => $agg->toBars(is_array($neePayload['by_nee'] ?? null) ? $neePayload['by_nee'] : [], 8),
            'nee_flagged' => (int) ($neePayload['flagged'] ?? 0),
            'nee_unit' => (string) ($neePayload['unit'] ?? 'rows'),
            'nee_without_aee' => array_key_exists('without_aee', $neePayload) ? (int) $neePayload['without_aee'] : null,
            'nee_aee_without_condition' => array_key_exists('aee_without_nee', $neePayload) ? (int) $neePayload['aee_without_nee'] : null,
            'by_deficiency' => $agg->toBars(is_array($neePayload['by_deficiency'] ?? null) ? $neePayload['by_deficiency'] : [], 10),
            'by_disorder' => $agg->toBars(is_array($neePayload['by_disorder'] ?? null) ? $neePayload['by_disorder'] : [], 8),
            'by_ah' => $agg->toBars(is_array($neePayload['by_ah'] ?? null) ? $neePayload['by_ah'] : [], 4),
            'deficiency_flagged' => (int) ($neePayload['deficiency_flagged'] ?? 0),
            'disorder_flagged' => (int) ($neePayload['disorder_flagged'] ?? 0),
            'ah_flagged' => (int) ($neePayload['ah_flagged'] ?? 0),
            'by_underreporting' => $agg->toBars(is_array($neePayload['by_underreporting'] ?? null) ? $neePayload['by_underreporting'] : [], 8),
            'underreporting_flagged' => (int) ($neePayload['underreporting_flagged'] ?? 0),
            'nee_note_def_vs_trs' => (string) ($neePayload['note_def_vs_trs']
                ?? __('Deficiências (DEF-*) e transtornos (TRS-*, ex. TEA) são públicos distintos no Censo; AH é categoria própria.')),
            'nee_note_sub' => (string) ($neePayload['note_sub']
                ?? __('Alertas de subnotificação são heurísticos (comorbidades frequentes e tipificação incompleta) — validar com a escola/laudo.')),
            'has_transporte' => $hasTra,
            'transporte_flagged' => (int) ($traPayload['flagged'] ?? 0),
            'transporte_pct' => $traPayload['pct'] ?? null,
            'transporte_summary' => $tra?->summary,
            'social_note' => $demPayload['social_note']
                ?? __('Vulnerabilidade social (CadÚnico/Bolsa Família) não está nos CSV da 1ª etapa do Educacenso.'),
            'privacy_note' => __('Somente contagens agregadas — nenhum nome, CPF ou NIS é exibido.'),
            'has_dis' => $dis !== null,
            'has_den' => $den !== null,
            'has_doc' => $doc !== null,
        ];
    }

    /**
     * Tempo de escolarização: turnos/CH das turmas e padrões de jornada do aluno.
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  list<string>  $inactiveIneps
     * @return array<string, mixed>
     */
    private function buildJornadaSection(Collection $inferences, array $inactiveIneps): array
    {
        $jor = $inferences->get('INF-JOR');
        $payload = is_array($jor?->payload) ? $jor->payload : [];
        if ($jor === null && $payload === []) {
            return ['available' => false];
        }

        $agg = new RelationCsvAggregator;
        $schools = collect(is_array($payload['schools'] ?? null) ? $payload['schools'] : []);
        $mapSchool = static function (array $row) use ($agg): array {
            return [
                'inep' => (string) ($row['inep'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'functioning' => (string) ($row['functioning'] ?? ''),
                'turmas' => (int) ($row['turmas'] ?? 0),
                'people' => (int) ($row['people'] ?? 0),
                'fund_aee_contraturno' => (int) ($row['fund_aee_contraturno'] ?? 0),
                'curricular_ac' => (int) ($row['curricular_ac'] ?? 0),
                'infantil_turma_estendida' => (int) ($row['infantil_turma_estendida'] ?? 0),
                'multi_enrollment' => (int) ($row['multi_enrollment'] ?? 0),
                'by_turno' => $agg->toBars(is_array($row['by_turno'] ?? null) ? $row['by_turno'] : [], 6),
                'by_ch_band' => $agg->toBars(is_array($row['by_ch_band'] ?? null) ? $row['by_ch_band'] : [], 6),
                'has_turno' => (bool) ($row['has_turno'] ?? false),
                'has_ch' => (bool) ($row['has_ch'] ?? false),
                'highlight' => ((int) ($row['fund_aee_contraturno'] ?? 0) > 0)
                    || ((int) ($row['curricular_ac'] ?? 0) > 0)
                    || ((int) ($row['infantil_turma_estendida'] ?? 0) > 0),
            ];
        };

        $schoolsActive = $schools
            ->reject(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->map($mapSchool)
            ->sortByDesc(fn (array $r) => ($r['fund_aee_contraturno'] * 1000) + ($r['curricular_ac'] * 100) + $r['infantil_turma_estendida'] + $r['turmas'])
            ->values()
            ->all();
        $schoolsOther = $schools
            ->filter(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->map($mapSchool)
            ->values()
            ->all();

        return [
            'available' => true,
            'summary' => $jor?->summary,
            'people' => (int) ($payload['people'] ?? 0),
            'turmas' => (int) ($payload['turmas'] ?? 0),
            'fund_aee_contraturno' => (int) ($payload['fund_aee_contraturno'] ?? 0),
            'curricular_ac' => (int) ($payload['curricular_ac'] ?? 0),
            'infantil_turma_estendida' => (int) ($payload['infantil_turma_estendida'] ?? 0),
            'multi_enrollment' => (int) ($payload['multi_enrollment'] ?? 0),
            'has_turno_columns' => (bool) ($payload['has_turno_columns'] ?? false),
            'has_ch_columns' => (bool) ($payload['has_ch_columns'] ?? false),
            'by_turno' => $agg->enrichTurnoBars($agg->toBars(is_array($payload['by_turno'] ?? null) ? $payload['by_turno'] : [], 12)),
            'by_ch_band' => $agg->enrichCargaBars($agg->toBars(is_array($payload['by_ch_band'] ?? null) ? $payload['by_ch_band'] : [], 16)),
            'by_turno_curricular' => $agg->enrichTurnoBars($agg->toBars(is_array($payload['by_turno_curricular'] ?? null) ? $payload['by_turno_curricular'] : [], 8)),
            'note_ch' => __('Valores exactos de «Carga horária semanal» encontrados no export — não são faixas inventadas.'),
            'note_turno' => __('Turnos normalizados (Manhã/Tarde/Noite/Integral). Dias e horários abreviados quando o texto original os traz.'),
            'note_fund_aee' => (string) ($payload['note_fund_aee']
                ?? __('Fundamental regular + AEE em outra matrícula (contraturno típico) — não confundir com atividade complementar.')),
            'note_infantil' => (string) ($payload['note_infantil']
                ?? __('Infantil em turma única com turno/CH estendido — diferente de tempo integral por duas matrículas.')),
            'schools_active' => $schoolsActive,
            'schools_other' => $schoolsOther,
        ];
    }

    /**
     * Transporte escolar: uso, rural/urbano, tipo de veículo — ativas × demais.
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @param  list<string>  $inactiveIneps
     * @return array<string, mixed>
     */
    private function buildTransporteSection(Collection $inferences, array $inactiveIneps): array
    {
        $tra = $inferences->get('INF-TRA');
        $payload = is_array($tra?->payload) ? $tra->payload : [];
        if ($tra === null && $payload === []) {
            return ['available' => false];
        }

        $hasCol = (bool) ($payload['has_transporte_columns'] ?? false);
        $agg = new RelationCsvAggregator;
        $active = is_array($payload['active'] ?? null) ? $payload['active'] : [];
        $other = is_array($payload['other'] ?? null) ? $payload['other'] : [];

        $mapSchool = static function (array $row) use ($agg): array {
            $flagged = (int) ($row['flagged'] ?? 0);

            return [
                'inep' => (string) ($row['inep'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'functioning' => (string) ($row['functioning'] ?? ''),
                'location' => (string) ($row['location'] ?? __('Não informado')),
                'scanned' => (int) ($row['scanned'] ?? 0),
                'flagged' => $flagged,
                'pct' => $row['pct'] ?? 0,
                'without' => (int) ($row['without'] ?? 0),
                'sem_poder' => (int) ($row['sem_poder'] ?? 0),
                'by_transporte' => $agg->toBars(is_array($row['by_transporte'] ?? null) ? $row['by_transporte'] : [], 4),
                'by_poder_publico' => $agg->toBars(is_array($row['by_poder_publico'] ?? null) ? $row['by_poder_publico'] : [], 6),
                'by_veiculo' => $agg->toBars(is_array($row['by_veiculo'] ?? null) ? $row['by_veiculo'] : [], 6),
                'has_transporte' => (bool) ($row['has_transporte'] ?? false),
                'has_veiculo' => (bool) ($row['has_veiculo'] ?? false),
                'highlight' => $flagged > 0,
                'highlight_rural' => $flagged > 0 && preg_match('/rural/iu', (string) ($row['location'] ?? '')) === 1,
            ];
        };

        $schools = collect(is_array($payload['schools'] ?? null) ? $payload['schools'] : []);
        $schoolsActive = $schools
            ->reject(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->map($mapSchool)
            ->sortByDesc(fn (array $r) => ($r['flagged'] * 1000) + $r['scanned'])
            ->values()
            ->all();
        $schoolsOther = $schools
            ->filter(fn (array $r) => in_array((string) ($r['inep'] ?? ''), $inactiveIneps, true))
            ->map($mapSchool)
            ->values()
            ->all();

        return [
            'available' => $hasCol || (int) ($payload['flagged'] ?? 0) > 0 || $tra !== null,
            'summary' => $tra?->summary,
            'has_transporte_columns' => $hasCol,
            'has_poder_publico' => (bool) ($payload['has_poder_publico'] ?? false),
            'has_veiculo' => (bool) ($payload['has_veiculo'] ?? false),
            'flagged' => (int) ($payload['flagged'] ?? 0),
            'scanned' => (int) ($payload['scanned'] ?? 0),
            'pct' => $payload['pct'] ?? null,
            'by_transporte' => $agg->toBars(is_array($payload['by_transporte'] ?? null) ? $payload['by_transporte'] : [], 6),
            'by_poder_publico' => $agg->toBars(is_array($payload['by_poder_publico'] ?? null) ? $payload['by_poder_publico'] : [], 8),
            'by_veiculo' => $agg->toBars(is_array($payload['by_veiculo'] ?? null) ? $payload['by_veiculo'] : [], 8),
            'by_location_users' => $agg->toBars(is_array($payload['by_location_users'] ?? null) ? $payload['by_location_users'] : [], 6),
            'active' => [
                'flagged' => (int) ($active['flagged'] ?? 0),
                'scanned' => (int) ($active['scanned'] ?? 0),
                'pct' => $active['pct'] ?? null,
                'by_location_users' => $agg->toBars(is_array($active['by_location_users'] ?? null) ? $active['by_location_users'] : [], 6),
                'by_veiculo' => $agg->toBars(is_array($active['by_veiculo'] ?? null) ? $active['by_veiculo'] : [], 8),
            ],
            'other' => [
                'flagged' => (int) ($other['flagged'] ?? 0),
                'scanned' => (int) ($other['scanned'] ?? 0),
                'pct' => $other['pct'] ?? null,
                'by_location_users' => $agg->toBars(is_array($other['by_location_users'] ?? null) ? $other['by_location_users'] : [], 6),
                'by_veiculo' => $agg->toBars(is_array($other['by_veiculo'] ?? null) ? $other['by_veiculo'] : [], 8),
            ],
            'note_location' => (string) ($payload['note_location']
                ?? __('Rural/urbano vem da Localização da escola no Acompanhamento; o uso e o tipo de veículo vêm da Relação de alunos.')),
            'schools_active' => $schoolsActive,
            'schools_other' => $schoolsOther,
        ];
    }

    /**
     * Medidores da 1ª etapa: distorção, densidade, docentes.
     *
     * @param  Collection<string, ClioCampaignInference>  $inferences
     * @return array<string, mixed>
     */
    private function buildStageMetricsSection(Collection $inferences): array
    {
        $dis = $inferences->get('INF-DIS');
        $den = $inferences->get('INF-DEN');
        $doc = $inferences->get('INF-DOC');
        $disPayload = is_array($dis?->payload) ? $dis->payload : [];
        $denPayload = is_array($den?->payload) ? $den->payload : [];
        $docPayload = is_array($doc?->payload) ? $doc->payload : [];

        $pct = $disPayload['pct_distorcao'] ?? null;
        $disTone = 'slate';
        if (is_numeric($pct)) {
            $disTone = ((float) $pct) >= 20 ? 'rose' : (((float) $pct) >= 10 ? 'amber' : 'emerald');
        }

        $etapaRows = [];
        foreach (is_array($disPayload['by_etapa'] ?? null) ? $disPayload['by_etapa'] : [] as $etapa => $row) {
            if (! is_array($row)) {
                continue;
            }
            $etapaRows[] = [
                'etapa' => $etapa,
                'eligible' => (int) ($row['eligible'] ?? 0),
                'distorcao' => (int) ($row['distorcao'] ?? 0),
                'atraso_1' => (int) ($row['atraso_1'] ?? 0),
                'adequado' => (int) ($row['adequado'] ?? 0),
                'pct' => $row['pct_distorcao'] ?? null,
            ];
        }
        $etapaRows = (new EtapaLabelOrder)->sortRowsByEtapaKey($etapaRows, 'etapa');

        return [
            'available' => $dis !== null || $den !== null || ($doc !== null && isset($docPayload['turmas_sem_docente'])),
            'distortion' => [
                'summary' => $dis?->summary,
                'pct' => $pct,
                'eligible' => (int) ($disPayload['eligible'] ?? 0),
                'distorcao' => (int) ($disPayload['distorcao'] ?? 0),
                'atraso_1' => (int) ($disPayload['atraso_1'] ?? 0),
                'adequado' => (int) ($disPayload['adequado'] ?? 0),
                'adiantado' => (int) ($disPayload['adiantado'] ?? 0),
                'tone' => $disTone,
                'note' => $disPayload['method_note'] ?? null,
                'by_etapa' => $etapaRows,
            ],
            'density' => [
                'summary' => $den?->summary,
                'media' => $denPayload['media_alunos_por_turma'] ?? null,
                'turmas_com_aluno' => (int) ($denPayload['turmas_com_aluno'] ?? 0),
                'turmas_sem_aluno' => (int) ($denPayload['turmas_sem_aluno'] ?? 0),
                'turmas_ge_40' => (int) ($denPayload['turmas_ge_40'] ?? 0),
                'max' => (int) ($denPayload['max_alunos_turma'] ?? 0),
                'tone' => ((int) ($denPayload['turmas_ge_40'] ?? 0)) > 0 ? 'amber' : 'emerald',
            ],
            'staff' => [
                'summary' => $doc?->summary,
                'rows' => (int) ($docPayload['relacao_profissional_rows'] ?? 0),
                'turmas_com_docente' => (int) ($docPayload['turmas_com_docente'] ?? 0),
                'turmas_sem_docente' => (int) ($docPayload['turmas_sem_docente'] ?? 0),
                'ratio' => $docPayload['vinculos_por_turma'] ?? null,
                'tone' => ((int) ($docPayload['turmas_sem_docente'] ?? 0)) > 0 ? 'amber' : 'emerald',
            ],
        ];
    }

    private function isSchoolBlocked(?\App\Models\Clio\ClioCampaignSchool $school): bool
    {
        if ($school === null) {
            return false;
        }
        $meta = is_array($school->meta) ? $school->meta : [];
        $blocked = mb_strtolower(trim((string) ($meta['blocked'] ?? '')));

        return $blocked !== '' && ! in_array($blocked, ['não', 'nao', 'n', '0', 'false', ''], true);
    }

    private function inferenceTitle(string $code): string
    {
        return match ($code) {
            'INF-COL' => __('Situação da coleta nas escolas'),
            'INF-ESC' => __('Rede escolar'),
            'INF-MAT' => __('Matrículas'),
            'INF-TUR' => __('Turmas'),
            'INF-DOC' => __('Profissionais'),
            'INF-NEE' => __('Inclusão / NEE'),
            'INF-TRA' => __('Transporte escolar'),
            'INF-JOR' => __('Tempo de escolarização'),
            'INF-DEM' => __('Perfil demográfico'),
            'INF-DIS' => __('Distorção idade-série'),
            'INF-DEN' => __('Densidade aluno/turma'),
            'INF-COE' => __('Coerência dos arquivos'),
            'INF-DUP' => __('Possíveis duplicidades'),
            'INF-DELTA' => __('Diferenças Acomp × Relações'),
            'INF-XCHK' => __('Conferências cruzadas'),
            'INF-GAP' => __('Comparação com o i-Educar'),
            default => $code,
        };
    }

    private function inferenceHint(string $code): string
    {
        return match ($code) {
            'INF-COL' => __('Quantas escolas já avançaram, ainda não começaram ou estão bloqueadas.'),
            'INF-ESC' => __('Escolas ativas e extintas identificadas nos relatórios.'),
            'INF-MAT' => __('Totais de matrícula do Acompanhamento (curricular/AEE/AC) e das relações de alunos.'),
            'INF-TUR' => __('Turmas por etapa, mediação e tipo (curricular, AEE, atividade complementar).'),
            'INF-DOC' => __('Volume de profissionais/vínculos nas relações enviadas.'),
            'INF-NEE' => __('Sinais de deficiência, transtornos (TEA) e AH; alertas de possível subnotificação/comorbidade.'),
            'INF-TRA' => __('Uso de transporte, rural/urbano (Localização da escola), tipo de veículo e poder público — com separação ativas × demais.'),
            'INF-JOR' => __('Turnos e CH das turmas; destaca fund.+AEE em contraturno, regular+AC e infantil em turma estendida (sem confundir com tempo integral por duas matrículas).'),
            'INF-DEM' => __('Cor/Raça, sexo e faixa etária agregados a partir das Relações de alunos.'),
            'INF-DIS' => __('Proporção de alunos com 2 ou mais anos acima da idade esperada para a série (estimativa INEP).'),
            'INF-DEN' => __('Média de alunos por turma e turmas vazias ou muito cheias.'),
            'INF-COE' => __('Se cada escola tem o conjunto aluno + turma + profissional.'),
            'INF-DUP' => __('Indícios de registros repetidos nos arquivos.'),
            'INF-DELTA' => __('Quando o Acompanhamento e as relações não batem (curricular, AEE, AC).'),
            'INF-XCHK' => __('Totais do arquivo geral × Relação de alunos e coerência alunos×turmas por ano/etapa.'),
            'INF-GAP' => __('Escolas só no Clio, só no i-Educar, ou nos dois.'),
            default => '',
        };
    }
}
