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
            'INF-MAT' => __('Totais de matrícula do Acompanhamento e das relações de alunos.'),
            'INF-TUR' => __('Volume de turmas nas relações enviadas.'),
            'INF-DOC' => __('Volume de profissionais/vínculos nas relações enviadas.'),
            'INF-NEE' => __('Sinais de atendimento educacional especializado / NEE.'),
            'INF-COE' => __('Se cada escola tem o conjunto aluno + turma + profissional.'),
            'INF-DUP' => __('Indícios de registros repetidos nos arquivos.'),
            'INF-DELTA' => __('Quando o Acompanhamento e as relações não batem.'),
            'INF-GAP' => __('Escolas só no Clio, só no i-Educar, ou nos dois.'),
            default => '',
        };
    }
}
