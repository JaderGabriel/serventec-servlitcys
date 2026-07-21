<?php

namespace App\Services\Clio\Analysis;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use Illuminate\Support\Collection;

/**
 * Consolida cobertura, inferências e achados numa visão legível para o painel analítico.
 */
final class CampaignAnalysisPresenter
{
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
        $triadeComplete = (int) ($coverage['schools_triade_complete'] ?? $schools->where('triade', true)->count());
        $triadePct = (float) ($coverage['triade_coverage_pct'] ?? 0);

        $withAluno = $schools->where('aluno', true)->count();
        $withTurma = $schools->where('turma', true)->count();
        $withProf = $schools->where('profissional', true)->count();

        $errors = $findings->where('severity', ClioCampaignFinding::SEVERITY_ERROR);
        $warnings = $findings->where('severity', ClioCampaignFinding::SEVERITY_WARNING);
        $infos = $findings->where('severity', ClioCampaignFinding::SEVERITY_INFO);

        $errorSchoolIds = $errors->pluck('school_id')->filter()->unique();
        $okSchools = $schools->filter(function (array $row) use ($campaign, $errorSchoolIds) {
            if (! ($row['triade'] ?? false)) {
                return false;
            }
            $school = $campaign->schools->firstWhere('inep_code', $row['inep']);
            if ($school === null) {
                return true;
            }

            return ! $errorSchoolIds->contains($school->id);
        })->count();

        $mat = $inferences->get('INF-MAT');
        $matPayload = is_array($mat?->payload) ? $mat->payload : [];
        $col = $inferences->get('INF-COL');
        $colBuckets = is_array($col?->payload['buckets'] ?? null) ? $col->payload['buckets'] : [];

        $kpis = [
            [
                'label' => __('Escolas na coleta'),
                'value' => number_format($totalSchools),
                'hint' => $coverage['has_acomp'] ?? false
                    ? __('Com relatório de acompanhamento')
                    : __('Sem Acomp municipal ainda'),
                'tone' => 'sky',
            ],
            [
                'label' => __('Tríade completa'),
                'value' => number_format($triadePct, 1, ',', '.').'%',
                'hint' => __(':ok de :total escolas com aluno, turma e profissional', [
                    'ok' => $triadeComplete,
                    'total' => $totalSchools,
                ]),
                'tone' => $triadePct >= 80 ? 'emerald' : ($triadePct >= 40 ? 'amber' : 'rose'),
            ],
            [
                'label' => __('Erros a corrigir'),
                'value' => number_format($errors->count()),
                'hint' => $errors->isEmpty()
                    ? __('Nenhum erro crítico listado')
                    : __('Priorize estes pontos antes de fechar a coleta'),
                'tone' => $errors->isEmpty() ? 'emerald' : 'rose',
            ],
            [
                'label' => __('Pontos de atenção'),
                'value' => number_format($warnings->count()),
                'hint' => __('Avisos que merecem revisão'),
                'tone' => $warnings->isEmpty() ? 'emerald' : 'amber',
            ],
            [
                'label' => __('Matrículas (Acomp)'),
                'value' => number_format((int) ($matPayload['acomp_curricular_sum'] ?? 0)),
                'hint' => __('Linhas Relação aluno: :n', [
                    'n' => number_format((int) ($matPayload['relacao_aluno_rows'] ?? 0)),
                ]),
                'tone' => 'sky',
            ],
            [
                'label' => __('Escolas em boa forma'),
                'value' => number_format($okSchools),
                'hint' => __('Tríade completa e sem erro associado'),
                'tone' => $okSchools > 0 ? 'emerald' : 'slate',
            ],
        ];

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

            if ($schoolErrors > 0) {
                $status = __('Com erros');
                $tone = 'rose';
            } elseif ($row['triade'] ?? false) {
                $status = __('Completa');
                $tone = 'emerald';
            } elseif ($missing !== []) {
                $status = __('Incompleta');
                $tone = 'amber';
            } else {
                $status = __('Sem arquivos');
                $tone = 'slate';
            }

            return [
                'inep' => $row['inep'],
                'name' => $row['name'],
                'collection_form' => $school?->collection_form ?: ($school?->functioning_status ?: '—'),
                'triade' => (bool) ($row['triade'] ?? false),
                'aluno' => (bool) ($row['aluno'] ?? false),
                'turma' => (bool) ($row['turma'] ?? false),
                'profissional' => (bool) ($row['profissional'] ?? false),
                'missing' => $missing,
                'status' => $status,
                'tone' => $tone,
                'errors' => $schoolErrors,
                'warnings' => $schoolWarnings,
            ];
        })->sort(function (array $a, array $b): int {
            $rank = ['rose' => 0, 'amber' => 1, 'emerald' => 2, 'slate' => 3];
            $ra = $rank[$a['tone']] ?? 9;
            $rb = $rank[$b['tone']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        })->values();

        $highlights = [];
        foreach (['INF-COL', 'INF-ESC', 'INF-MAT', 'INF-TUR', 'INF-DOC', 'INF-NEE', 'INF-COE', 'INF-DUP', 'INF-DELTA', 'INF-GAP'] as $code) {
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

        return [
            'kpis' => $kpis,
            'triade' => [
                'pct' => $triadePct,
                'complete' => $triadeComplete,
                'total' => $totalSchools,
                'aluno_pct' => $totalSchools > 0 ? round(100 * $withAluno / $totalSchools, 1) : 0,
                'turma_pct' => $totalSchools > 0 ? round(100 * $withTurma / $totalSchools, 1) : 0,
                'profissional_pct' => $totalSchools > 0 ? round(100 * $withProf / $totalSchools, 1) : 0,
                'aluno' => $withAluno,
                'turma' => $withTurma,
                'profissional' => $withProf,
            ],
            'collection_buckets' => [
                'em_andamento' => (int) ($colBuckets['em_andamento'] ?? 0),
                'nao_iniciou' => (int) ($colBuckets['nao_iniciou'] ?? 0),
                'fechada' => (int) ($colBuckets['fechada'] ?? 0),
                'bloqueada' => (int) ($colBuckets['bloqueada'] ?? 0),
            ],
            'report' => $report,
            'highlights' => $highlights,
            'schools' => $schoolRows,
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
                $flags[] = __('Delta curricular');
            }
            if ((int) ($row['acomp_aee'] ?? 0) > 0 && (int) $row['turmas_aee'] === 0) {
                $flags[] = __('AEE sem turma');
            }
            if ((int) ($row['acomp_ac'] ?? 0) > 0 && (int) $row['turmas_ac'] === 0) {
                $flags[] = __('AC sem turma');
            }
            if ((int) $row['turmas'] === 0 && (int) $row['alunos'] > 0) {
                $flags[] = __('Alunos sem turma');
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
        ];
        $apontamentos = $findings
            ->filter(fn (ClioCampaignFinding $f) => in_array($f->code, $reportCodes, true))
            ->take(25)
            ->map(fn (ClioCampaignFinding $f) => [
                'code' => $f->code,
                'severity' => $f->severity,
                'severity_label' => $f->severityLabel(),
                'message' => $f->message,
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
                    'label' => __('Deltas a revisar'),
                    'value' => number_format((int) ($deltaPayload['divergent_schools'] ?? 0)),
                    'hint' => __('Escolas com diferença Acomp curricular × Relação'),
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
    public function presentSchool(
        \App\Models\Clio\ClioCampaignSchool $school,
        ?array $coverageRow,
        Collection $findings,
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

        if ($errors->isNotEmpty()) {
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

        $artifacts = $school->artifacts ?? collect();
        $rowsAluno = (int) $artifacts->where('kind', 'relacao_aluno_escola')->sum('row_count');
        $rowsTurma = (int) $artifacts->where('kind', 'relacao_turma_escola')->sum('row_count');
        $rowsProf = (int) $artifacts->where('kind', 'relacao_profissional_escola')->sum('row_count');

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
                'label' => __('Linhas de alunos'),
                'value' => number_format($rowsAluno),
                'hint' => $matAcomp !== null
                    ? __('Acomp curricular: :n', ['n' => number_format($matAcomp)])
                    : __('Na relação de alunos enviada'),
                'tone' => 'sky',
            ],
            [
                'label' => __('Arquivos recebidos'),
                'value' => number_format($artifacts->count()),
                'hint' => __('Turmas :t · Profissionais :p', [
                    't' => number_format($rowsTurma),
                    'p' => number_format($rowsProf),
                ]),
                'tone' => 'sky',
            ],
        ];

        $triadeParts = [
            [
                'key' => 'aluno',
                'label' => __('Alunos'),
                'ok' => $aluno,
                'rows' => $rowsAluno,
                'hint' => $aluno ? __('Arquivo presente') : __('Arquivo em falta'),
            ],
            [
                'key' => 'turma',
                'label' => __('Turmas'),
                'ok' => $turma,
                'rows' => $rowsTurma,
                'hint' => $turma ? __('Arquivo presente') : __('Arquivo em falta'),
            ],
            [
                'key' => 'profissional',
                'label' => __('Profissionais'),
                'ok' => $profissional,
                'rows' => $rowsProf,
                'hint' => $profissional ? __('Arquivo presente') : __('Arquivo em falta'),
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

        return [
            'kpis' => $kpis,
            'status' => $status,
            'tone' => $tone,
            'status_hint' => $statusHint,
            'triade' => [
                'ok' => $triade,
                'pct' => $triadePct,
                'parts' => $triadeParts,
                'missing' => $missing,
            ],
            'context' => [
                'functioning' => $school->functioning_status ?: __('Não informado'),
                'collection_form' => $school->collection_form ?: __('Não informado'),
                'dependency' => $school->dependency ?: __('Não informado'),
            ],
            'files' => $files,
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

    private function inferenceTitle(string $code): string
    {
        return match ($code) {
            'INF-COL' => __('Situação da coleta nas escolas'),
            'INF-ESC' => __('Rede escolar'),
            'INF-MAT' => __('Matrículas'),
            'INF-TUR' => __('Turmas'),
            'INF-DOC' => __('Profissionais'),
            'INF-NEE' => __('Inclusão / NEE'),
            'INF-COE' => __('Coerência dos arquivos'),
            'INF-DUP' => __('Possíveis duplicidades'),
            'INF-DELTA' => __('Diferenças Acomp × Relações'),
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
            'INF-NEE' => __('Sinais de atendimento educacional especializado / NEE.'),
            'INF-COE' => __('Se cada escola tem o conjunto aluno + turma + profissional.'),
            'INF-DUP' => __('Indícios de registros repetidos nos arquivos.'),
            'INF-DELTA' => __('Quando o Acompanhamento e as relações não batem (curricular, AEE, AC).'),
            'INF-GAP' => __('Escolas só no Clio, só no i-Educar, ou nos dois.'),
            default => '',
        };
    }
}
