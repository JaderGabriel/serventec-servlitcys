<?php

namespace App\Services\Clio\Analysis;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Parse\CampaignParseService;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Modo A — inferências INF-COL…INF-DELTA a partir de artefatos interpretados.
 */
final class CampaignAnalyzer
{
    private readonly CsvReader $csv;

    private readonly RelationCsvAggregator $aggregator;

    public function __construct(
        private readonly CampaignParseService $parseService,
        ?CsvReader $csv = null,
        ?RelationCsvAggregator $aggregator = null,
    ) {
        $this->csv = $csv ?? new CsvReader;
        $this->aggregator = $aggregator ?? new RelationCsvAggregator;
    }

    /**
     * @return array{inferences: int, findings: int, coverage: array<string, mixed>}
     */
    public function analyze(ClioCampaign $campaign, bool $parseFirst = true): array
    {
        if ($parseFirst) {
            $this->parseService->parseCampaign($campaign, reparse: false);
            $campaign->refresh();
        }

        $campaign->load(['schools.artifacts', 'artifacts']);

        $wasCrossChecked = $campaign->status === ClioCampaign::STATUS_CROSS_CHECKED
            || ClioCampaignInference::query()
                ->where('campaign_id', $campaign->id)
                ->where('code', 'INF-GAP')
                ->exists();

        DB::transaction(function () use ($campaign): void {
            // Preserva Modo B (INF-GAP / CLIO-GAP-*) — a re-análise é só Modo A.
            ClioCampaignFinding::query()
                ->where('campaign_id', $campaign->id)
                ->where('code', 'not like', 'CLIO-GAP-%')
                ->delete();
            ClioCampaignInference::query()
                ->where('campaign_id', $campaign->id)
                ->where('code', '!=', 'INF-GAP')
                ->delete();

            $this->inferColeta($campaign);
            $this->inferEscolas($campaign);
            $this->inferMatricula($campaign);
            $this->inferTurmas($campaign);
            $this->inferDocentes($campaign);
            $this->inferNee($campaign);
            $this->inferTransporte($campaign);
            $this->inferJornada($campaign);
            $this->inferDemografia($campaign);
            $this->inferDistorcao($campaign);
            $this->inferDensidade($campaign);
            $this->inferCoerencia($campaign);
            $this->inferDuplicidades($campaign);
            $this->inferDelta($campaign);
            $this->inferCruzamentos($campaign);
        });

        $stillHasGap = ClioCampaignInference::query()
            ->where('campaign_id', $campaign->id)
            ->where('code', 'INF-GAP')
            ->exists();

        $campaign->update([
            'status' => ($wasCrossChecked && $stillHasGap)
                ? ClioCampaign::STATUS_CROSS_CHECKED
                : ClioCampaign::STATUS_ANALYZED,
        ]);

        return [
            'inferences' => ClioCampaignInference::query()->where('campaign_id', $campaign->id)->count(),
            'findings' => ClioCampaignFinding::query()->where('campaign_id', $campaign->id)->count(),
            'coverage' => $this->parseService->coverage($campaign->fresh()),
        ];
    }

    private function upsertInference(ClioCampaign $campaign, string $code, string $summary, array $payload): void
    {
        ClioCampaignInference::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'code' => $code],
            ['summary' => $summary, 'payload' => $payload],
        );
    }

    private function addFinding(
        ClioCampaign $campaign,
        string $code,
        string $severity,
        string $message,
        ?ClioCampaignSchool $school = null,
        ?ClioCampaignArtifact $artifact = null,
        array $meta = [],
    ): void {
        ClioCampaignFinding::query()->create([
            'campaign_id' => $campaign->id,
            'school_id' => $school?->id,
            'artifact_id' => $artifact?->id,
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    private function inferColeta(ClioCampaign $campaign): void
    {
        $buckets = [
            'nao_iniciou' => 0,
            'em_andamento' => 0,
            'fechada' => 0,
            'bloqueada' => 0,
            'outra' => 0,
        ];

        foreach ($campaign->schools as $school) {
            $form = mb_strtolower((string) ($school->collection_form ?? ''));
            $status = mb_strtolower((string) ($school->functioning_status ?? ''));
            $blocked = mb_strtolower((string) (($school->meta['blocked'] ?? '')));

            if ($blocked !== '' && ! in_array($blocked, ['não', 'nao', 'n', '0', 'false'], true)) {
                $buckets['bloqueada']++;
                $this->addFinding(
                    $campaign,
                    'CLIO-COL-BLOCK',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('Esta escola está bloqueada no portal Educacenso. Confirme o motivo ou desbloqueie antes de seguir.'),
                    $school,
                );

                continue;
            }

            if (str_contains($form, 'não inici') || str_contains($form, 'nao inici') || str_contains($status, 'não inici')) {
                $buckets['nao_iniciou']++;
            } elseif (str_contains($form, 'fech') || str_contains($status, 'fech')) {
                $buckets['fechada']++;
            } elseif (str_contains($form, 'andamento') || str_contains($form, 'educacenso') || str_contains($status, 'atividade')) {
                $buckets['em_andamento']++;
            } else {
                $buckets['outra']++;
            }
        }

        $total = max(1, $campaign->schools->count());
        $this->upsertInference(
            $campaign,
            'INF-COL',
            __('Situação no portal: :a em andamento, :n ainda não iniciaram, :f fechadas, :b bloqueadas.', [
                'a' => $buckets['em_andamento'],
                'n' => $buckets['nao_iniciou'],
                'f' => $buckets['fechada'],
                'b' => $buckets['bloqueada'],
            ]),
            [
                'buckets' => $buckets,
                'pct_em_andamento' => round(100 * $buckets['em_andamento'] / $total, 1),
                'pct_nao_iniciou' => round(100 * $buckets['nao_iniciou'] / $total, 1),
            ],
        );
    }

    private function inferEscolas(ClioCampaign $campaign): void
    {
        $ativas = 0;
        $extintas = 0;
        $byDep = [];

        foreach ($campaign->schools as $school) {
            $status = mb_strtolower((string) ($school->functioning_status ?? ''));
            if (str_contains($status, 'extint')) {
                $extintas++;
            } else {
                $ativas++;
            }
            $dep = $school->dependency ?: __('Não informado');
            $byDep[$dep] = ($byDep[$dep] ?? 0) + 1;
        }

        $this->upsertInference(
            $campaign,
            'INF-ESC',
            __('Rede escolar: :a em atividade, :e extintas.', ['a' => $ativas, 'e' => $extintas]),
            [
                'active' => $ativas,
                'extinct' => $extintas,
                'by_dependency' => $byDep,
                'schools_total' => $campaign->schools->count(),
            ],
        );
    }

    private function inferMatricula(ClioCampaign $campaign): void
    {
        $fromAcomp = 0;
        $fromAee = 0;
        $fromAc = 0;
        $aeeSchools = 0;
        $acSchools = 0;
        $fromRelacao = 0;
        $byEtapa = [];
        $withoutEtapa = 0;
        $withoutTurma = 0;
        $schoolsBreakdown = [];

        foreach ($campaign->schools as $school) {
            $meta = is_array($school->meta) ? $school->meta : [];
            $curr = $meta['total_curricular'] ?? null;
            if (is_numeric($curr)) {
                $fromAcomp += (int) $curr;
            }
            $aee = $meta['total_aee'] ?? null;
            if (is_numeric($aee)) {
                $fromAee += (int) $aee;
                if ((int) $aee > 0) {
                    $aeeSchools++;
                }
            }
            $ac = $meta['total_ac'] ?? null;
            if (is_numeric($ac)) {
                $fromAc += (int) $ac;
                if ((int) $ac > 0) {
                    $acSchools++;
                }
            }

            $alunoAgg = $this->emptyAlunoAgg();
            foreach ($school->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
                $alunoAgg = $this->mergeAlunoAgg($alunoAgg, $this->resolveAlunoAggregates($artifact));
            }
            $fromRelacao += $alunoAgg['total'];
            $byEtapa = $this->aggregator->mergeCounts($byEtapa, $alunoAgg['by_etapa_ensino']);
            $withoutEtapa += $alunoAgg['without_etapa'];
            $withoutTurma += $alunoAgg['without_turma'];

            if ($alunoAgg['total'] > 0 || is_numeric($curr) || is_numeric($aee) || is_numeric($ac)) {
                $schoolsBreakdown[] = [
                    'inep' => $school->inep_code,
                    'name' => $school->name,
                    'alunos' => $alunoAgg['total'],
                    'acomp_curricular' => is_numeric($curr) ? (int) $curr : null,
                    'acomp_aee' => is_numeric($aee) ? (int) $aee : null,
                    'acomp_ac' => is_numeric($ac) ? (int) $ac : null,
                    'by_etapa_ensino' => $alunoAgg['by_etapa_ensino'],
                ];
            }

            if ($alunoAgg['without_turma'] > 0) {
                $this->addFinding(
                    $campaign,
                    'CLIO-MAT-SEM-TURMA',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('Há :n matrícula(s) na Relação de alunos sem Código da turma — vincule cada aluno a uma turma.', ['n' => $alunoAgg['without_turma']]),
                    $school,
                );
            }
        }

        if ($fromRelacao === 0) {
            $fromRelacao = (int) $campaign->artifacts
                ->where('kind', 'relacao_aluno_escola')
                ->sum(fn (ClioCampaignArtifact $a) => (int) ($a->row_count ?? 0));
            foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
                $agg = $this->resolveAlunoAggregates($artifact);
                $byEtapa = $this->aggregator->mergeCounts($byEtapa, $agg['by_etapa_ensino']);
                $withoutEtapa += $agg['without_etapa'];
                $withoutTurma += $agg['without_turma'];
            }
        }

        usort($schoolsBreakdown, fn (array $a, array $b): int => ($b['alunos'] ?? 0) <=> ($a['alunos'] ?? 0));

        if ($withoutEtapa > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-MAT-SEM-ETAPA',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Há :n matrícula(s) sem Etapa de ensino — o quadro por ano fica incompleto até preencher.', [
                    'n' => $withoutEtapa,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-MAT',
            __('Matrículas no Acomp: curricular :c · AEE :aee · AC :ac · linhas na Relação de alunos: :r.', [
                'c' => $fromAcomp,
                'aee' => $fromAee,
                'ac' => $fromAc,
                'r' => $fromRelacao,
            ]),
            [
                'acomp_curricular_sum' => $fromAcomp,
                'acomp_aee_sum' => $fromAee,
                'acomp_ac_sum' => $fromAc,
                'acomp_aee_schools' => $aeeSchools,
                'acomp_ac_schools' => $acSchools,
                'relacao_aluno_rows' => $fromRelacao,
                'by_etapa_ensino' => $byEtapa,
                'without_etapa' => $withoutEtapa,
                'without_turma' => $withoutTurma,
                'schools' => array_slice($schoolsBreakdown, 0, 80),
                'has_acomp_aee_column' => $this->schoolsHaveNumericMeta($campaign, 'total_aee'),
                'has_acomp_ac_column' => $this->schoolsHaveNumericMeta($campaign, 'total_ac'),
            ],
        );
    }

    private function inferTurmas(ClioCampaign $campaign): void
    {
        $byEtapa = [];
        $byAgregada = [];
        $byTipo = [];
        $byMediacao = [];
        $buckets = [
            RelationCsvAggregator::BUCKET_CURRICULAR => 0,
            RelationCsvAggregator::BUCKET_AEE => 0,
            RelationCsvAggregator::BUCKET_AC => 0,
            RelationCsvAggregator::BUCKET_OUTRA => 0,
        ];
        $total = 0;
        $withoutEtapa = 0;
        $withoutTipo = 0;
        $schoolsBreakdown = [];

        foreach ($campaign->schools as $school) {
            $turmaAgg = $this->emptyTurmaAgg();
            foreach ($school->artifacts->where('kind', 'relacao_turma_escola') as $artifact) {
                $turmaAgg = $this->mergeTurmaAgg($turmaAgg, $this->resolveTurmaAggregates($artifact));
            }

            if ($turmaAgg['total'] === 0) {
                continue;
            }

            $total += $turmaAgg['total'];
            $byEtapa = $this->aggregator->mergeCounts($byEtapa, $turmaAgg['by_etapa_ensino']);
            $byAgregada = $this->aggregator->mergeCounts($byAgregada, $turmaAgg['by_etapa_agregada']);
            $byTipo = $this->aggregator->mergeCounts($byTipo, $turmaAgg['by_tipo_turma']);
            $byMediacao = $this->aggregator->mergeCounts($byMediacao, $turmaAgg['by_mediacao']);
            $buckets = $this->aggregator->mergeBuckets($buckets, $turmaAgg['by_tipo_bucket']);
            $withoutEtapa += $turmaAgg['without_etapa'];
            $withoutTipo += $turmaAgg['without_tipo'];

            $schoolsBreakdown[] = [
                'inep' => $school->inep_code,
                'name' => $school->name,
                'turmas' => $turmaAgg['total'],
                'curricular' => $turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_CURRICULAR] ?? 0,
                'aee' => $turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_AEE] ?? 0,
                'atividade_complementar' => $turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_AC] ?? 0,
                'by_etapa_ensino' => $turmaAgg['by_etapa_ensino'],
            ];

            $meta = is_array($school->meta) ? $school->meta : [];
            $curr = $meta['total_curricular'] ?? null;
            if (
                is_numeric($curr)
                && (int) $curr > 0
                && ($turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_CURRICULAR] ?? 0) === 0
                && $turmaAgg['total'] > 0
            ) {
                $this->addFinding(
                    $campaign,
                    'CLIO-TUR-SEM-CURRICULAR',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('O Acompanhamento indica :n matrícula(s) curricular(es), mas não há turma curricular na Relação de turmas.', [
                        'n' => (int) $curr,
                    ]),
                    $school,
                );
            }

            $aeeAcomp = $meta['total_aee'] ?? null;
            if (
                is_numeric($aeeAcomp)
                && (int) $aeeAcomp > 0
                && ($turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_AEE] ?? 0) === 0
            ) {
                $this->addFinding(
                    $campaign,
                    'CLIO-TUR-AEE-AUSENTE',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('O Acompanhamento declara :n matrícula(s) AEE, mas não há turma AEE na Relação de turmas.', [
                        'n' => (int) $aeeAcomp,
                    ]),
                    $school,
                );
            }
        }

        if ($total === 0) {
            foreach ($campaign->artifacts->where('kind', 'relacao_turma_escola') as $artifact) {
                $agg = $this->resolveTurmaAggregates($artifact);
                $total += $agg['total'];
                $byEtapa = $this->aggregator->mergeCounts($byEtapa, $agg['by_etapa_ensino']);
                $byAgregada = $this->aggregator->mergeCounts($byAgregada, $agg['by_etapa_agregada']);
                $byTipo = $this->aggregator->mergeCounts($byTipo, $agg['by_tipo_turma']);
                $byMediacao = $this->aggregator->mergeCounts($byMediacao, $agg['by_mediacao']);
                $buckets = $this->aggregator->mergeBuckets($buckets, $agg['by_tipo_bucket']);
                $withoutEtapa += $agg['without_etapa'];
                $withoutTipo += $agg['without_tipo'];
            }
        }

        usort($schoolsBreakdown, fn (array $a, array $b): int => ($b['turmas'] ?? 0) <=> ($a['turmas'] ?? 0));

        if ($withoutEtapa > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-TUR-SEM-ETAPA',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Há :n turma(s) sem Etapa de ensino — o indicador por ano fica incompleto até preencher.', [
                    'n' => $withoutEtapa,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-TUR',
            __('Turmas na Relação: :n · curricular :c · AEE :aee · atividade complementar :ac.', [
                'n' => $total,
                'c' => $buckets[RelationCsvAggregator::BUCKET_CURRICULAR],
                'aee' => $buckets[RelationCsvAggregator::BUCKET_AEE],
                'ac' => $buckets[RelationCsvAggregator::BUCKET_AC],
            ]),
            [
                'relacao_turma_rows' => $total,
                'by_etapa_ensino' => $byEtapa,
                'by_etapa_agregada' => $byAgregada,
                'by_tipo_turma' => $byTipo,
                'by_mediacao' => $byMediacao,
                'by_tipo_bucket' => $buckets,
                'without_etapa' => $withoutEtapa,
                'without_tipo' => $withoutTipo,
                'schools' => array_slice($schoolsBreakdown, 0, 80),
            ],
        );
    }

    private function inferDocentes(ClioCampaign $campaign): void
    {
        $rows = 0;
        $docenteRows = 0;
        $withoutTurma = 0;
        $byTurmaProf = [];
        $turmaCodes = [];

        foreach ($campaign->artifacts->where('kind', 'relacao_profissional_escola') as $artifact) {
            $agg = $this->resolveProfissionalAggregates($artifact);
            $rows += (int) ($agg['total'] ?? 0);
            $docenteRows += (int) ($agg['docente_rows'] ?? 0);
            $withoutTurma += (int) ($agg['without_turma'] ?? 0);
            $byTurmaProf = $this->aggregator->mergeCounts(
                $byTurmaProf,
                is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [],
            );
        }

        foreach ($campaign->artifacts->where('kind', 'relacao_turma_escola') as $artifact) {
            $turmaAgg = $this->resolveTurmaAggregates($artifact);
            foreach (is_array($turmaAgg['turma_codes'] ?? null) ? $turmaAgg['turma_codes'] : [] as $code) {
                $turmaCodes[$code] = true;
            }
        }

        $turmasTotal = count($turmaCodes);
        $turmasComDocente = 0;
        $turmasSemDocente = 0;
        foreach (array_keys($turmaCodes) as $code) {
            if (($byTurmaProf[$code] ?? 0) > 0) {
                $turmasComDocente++;
            } else {
                $turmasSemDocente++;
            }
        }

        if ($turmasSemDocente > 0 && $turmasTotal > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-DOC-SEM-VINCULO',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Há :n turma(s) sem profissional vinculado na Relação (de :t turmas com código).', [
                    'n' => $turmasSemDocente,
                    't' => $turmasTotal,
                ]),
            );
        }

        $ratio = $turmasTotal > 0 ? round($docenteRows / $turmasTotal, 2) : null;

        $this->upsertInference(
            $campaign,
            'INF-DOC',
            $turmasTotal > 0
                ? __('Profissionais: :n vínculo(s) · :d com turma · :s turma(s) sem vínculo · média :r vínculo(s)/turma.', [
                    'n' => $rows,
                    'd' => $turmasComDocente,
                    's' => $turmasSemDocente,
                    'r' => $ratio ?? '—',
                ])
                : __('Profissionais na Relação: :n linha(s) de vínculo.', ['n' => $rows]),
            [
                'relacao_profissional_rows' => $rows,
                'docente_rows' => $docenteRows,
                'without_turma' => $withoutTurma,
                'turmas_total' => $turmasTotal,
                'turmas_com_docente' => $turmasComDocente,
                'turmas_sem_docente' => $turmasSemDocente,
                'vinculos_por_turma' => $ratio,
            ],
        );
    }

    private function inferDistorcao(ClioCampaign $campaign): void
    {
        $year = (int) $campaign->year;
        $merged = [
            'eligible' => 0,
            'distorcao' => 0,
            'atraso_1' => 0,
            'adequado' => 0,
            'adiantado' => 0,
            'indefinido' => 0,
            'excluido' => 0,
            'by_etapa' => [],
        ];
        $hasNasc = false;
        $scanned = 0;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            $agg = $this->resolveAlunoAggregates($artifact, $year);
            $scanned += (int) ($agg['total'] ?? 0);
            $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
            if (! empty($cols['nascimento'])) {
                $hasNasc = true;
            }
            $ag = is_array($agg['age_grade'] ?? null) ? $agg['age_grade'] : [];
            foreach (['eligible', 'distorcao', 'atraso_1', 'adequado', 'adiantado', 'indefinido', 'excluido'] as $k) {
                $merged[$k] = (int) ($merged[$k] ?? 0) + (int) ($ag[$k] ?? 0);
            }
            $byEtapa = is_array($ag['by_etapa'] ?? null) ? $ag['by_etapa'] : [];
            foreach ($byEtapa as $etapa => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! isset($merged['by_etapa'][$etapa])) {
                    $merged['by_etapa'][$etapa] = [
                        'eligible' => 0,
                        'distorcao' => 0,
                        'atraso_1' => 0,
                        'adequado' => 0,
                        'adiantado' => 0,
                    ];
                }
                foreach (['eligible', 'distorcao', 'atraso_1', 'adequado', 'adiantado'] as $k) {
                    $merged['by_etapa'][$etapa][$k] = (int) ($merged['by_etapa'][$etapa][$k] ?? 0) + (int) ($row[$k] ?? 0);
                }
            }
        }

        $eligible = (int) $merged['eligible'];
        $pct = $eligible > 0 ? round(100 * ((int) $merged['distorcao']) / $eligible, 1) : null;
        foreach ($merged['by_etapa'] as $etapa => $row) {
            $elig = max(1, (int) ($row['eligible'] ?? 0));
            $merged['by_etapa'][$etapa]['pct_distorcao'] = round(100 * ((int) ($row['distorcao'] ?? 0)) / $elig, 1);
        }
        uasort($merged['by_etapa'], static fn (array $a, array $b): int => ($b['distorcao'] ?? 0) <=> ($a['distorcao'] ?? 0));
        $merged['by_etapa'] = array_slice($merged['by_etapa'], 0, 20, true);

        if ($scanned > 0 && ! $hasNasc) {
            $this->addFinding(
                $campaign,
                'CLIO-DIS-SEM-NASC',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Sem Data de nascimento nas Relações — a distorção idade-série não pode ser calculada.'),
            );
        } elseif ($pct !== null && $pct >= 20) {
            $this->addFinding(
                $campaign,
                'CLIO-DIS-ALTA',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Distorção idade-série estimada em :p% (:n de :e alunos no escopo EF/EM). Revisar fluxos de progressão e defasagem.', [
                    'p' => $pct,
                    'n' => $merged['distorcao'],
                    'e' => $eligible,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-DIS',
            $pct === null
                ? __('Distorção idade-série: sem base suficiente (nascimento + etapa seriada).')
                : __('Distorção idade-série estimada: :p% (:n de :e no escopo). Adequados :a · atraso 1 ano :d1 · adiantados :ad.', [
                    'p' => $pct,
                    'n' => $merged['distorcao'],
                    'e' => $eligible,
                    'a' => $merged['adequado'],
                    'd1' => $merged['atraso_1'],
                    'ad' => $merged['adiantado'],
                ]),
            [
                ...$merged,
                'pct_distorcao' => $pct,
                'has_nascimento' => $hasNasc,
                'scanned' => $scanned,
                'method_note' => __('Estimativa alinhada ao critério INEP (≥2 anos acima da idade esperada em 31/03). EJA/AEE/AC fora do denominador. Não substitui o indicador oficial publicado.'),
            ],
        );
    }

    private function inferDensidade(ClioCampaign $campaign): void
    {
        $alunosPorTurma = [];
        $turmaCodes = [];
        $alunosComTurma = 0;
        $alunosSemTurma = 0;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            $agg = $this->resolveAlunoAggregates($artifact, (int) $campaign->year);
            $alunosSemTurma += (int) ($agg['without_turma'] ?? 0);
            $byTurma = is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [];
            foreach ($byTurma as $code => $n) {
                $alunosPorTurma[$code] = ($alunosPorTurma[$code] ?? 0) + (int) $n;
                $alunosComTurma += (int) $n;
            }
        }

        foreach ($campaign->artifacts->where('kind', 'relacao_turma_escola') as $artifact) {
            $turmaAgg = $this->resolveTurmaAggregates($artifact);
            foreach (is_array($turmaAgg['turma_codes'] ?? null) ? $turmaAgg['turma_codes'] : [] as $code) {
                $turmaCodes[$code] = true;
            }
        }

        $turmas = count($turmaCodes);
        $turmasComAluno = 0;
        $turmasSemAluno = 0;
        $turmasCheias = 0;
        $max = 0;
        $samplesCheias = [];

        foreach (array_keys($turmaCodes) as $code) {
            $n = (int) ($alunosPorTurma[$code] ?? 0);
            if ($n > 0) {
                $turmasComAluno++;
            } else {
                $turmasSemAluno++;
            }
            if ($n > $max) {
                $max = $n;
            }
            if ($n >= 40) {
                $turmasCheias++;
                if (count($samplesCheias) < 10) {
                    $samplesCheias[] = ['turma' => $code, 'alunos' => $n];
                }
            }
        }

        $media = $turmasComAluno > 0 ? round($alunosComTurma / $turmasComAluno, 1) : null;

        if ($turmasSemAluno > 0 && $turmas > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-DEN-TURMA-VAZIA',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Há :n turma(s) na Relação sem nenhum aluno vinculado pelo Código da turma.', [
                    'n' => $turmasSemAluno,
                ]),
            );
        }
        if ($turmasCheias > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-DEN-TURMA-CHEIA',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Há :n turma(s) com 40 ou mais alunos vinculados (máx. observado: :m). Conferir no portal se a composição está correta.', [
                    'n' => $turmasCheias,
                    'm' => $max,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-DEN',
            $media === null
                ? __('Densidade aluno/turma: sem pareamento suficiente por Código da turma.')
                : __('Densidade: média :m aluno(s)/turma · :c com aluno · :v sem aluno · :h com ≥40 alunos.', [
                    'm' => $media,
                    'c' => $turmasComAluno,
                    'v' => $turmasSemAluno,
                    'h' => $turmasCheias,
                ]),
            [
                'alunos_com_turma' => $alunosComTurma,
                'alunos_sem_turma' => $alunosSemTurma,
                'turmas_total' => $turmas,
                'turmas_com_aluno' => $turmasComAluno,
                'turmas_sem_aluno' => $turmasSemAluno,
                'turmas_ge_40' => $turmasCheias,
                'media_alunos_por_turma' => $media,
                'max_alunos_turma' => $max,
                'samples_cheias' => $samplesCheias,
            ],
        );
    }

    private function inferNee(ClioCampaign $campaign): void
    {
        $flagged = 0;
        $scanned = 0;
        $defFlagged = 0;
        $disorderFlagged = 0;
        $ahFlagged = 0;
        $underFlagged = 0;
        $byNee = [];
        $byDef = [];
        $byDisorder = [];
        $byAh = [];
        $byUnder = [];
        $hasNeeCol = false;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            $agg = $this->resolveAlunoAggregates($artifact, (int) $campaign->year);
            $scanned += (int) ($agg['total'] ?? 0);
            $flagged += (int) ($agg['nee_flagged'] ?? 0);
            $defFlagged += (int) ($agg['deficiency_flagged'] ?? 0);
            $disorderFlagged += (int) ($agg['disorder_flagged'] ?? 0);
            $ahFlagged += (int) ($agg['ah_flagged'] ?? 0);
            $underFlagged += (int) ($agg['underreporting_flagged'] ?? 0);
            $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
            if (! empty($cols['nee'])) {
                $hasNeeCol = true;
            }
            $byNee = $this->aggregator->mergeCounts(
                $byNee,
                is_array($agg['by_nee'] ?? null) ? $agg['by_nee'] : [],
            );
            $byDef = $this->aggregator->mergeCounts(
                $byDef,
                is_array($agg['by_deficiency'] ?? null) ? $agg['by_deficiency'] : [],
            );
            $byDisorder = $this->aggregator->mergeCounts(
                $byDisorder,
                is_array($agg['by_disorder'] ?? null) ? $agg['by_disorder'] : [],
            );
            $byAh = $this->aggregator->mergeCounts(
                $byAh,
                is_array($agg['by_ah'] ?? null) ? $agg['by_ah'] : [],
            );
            $byUnder = $this->aggregator->mergeCounts(
                $byUnder,
                is_array($agg['by_underreporting'] ?? null) ? $agg['by_underreporting'] : [],
            );
        }

        if (! $hasNeeCol && $scanned > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-DEM-SEM-NEE',
                ClioCampaignFinding::SEVERITY_INFO,
                __('As Relações de alunos desta coleta não trouxeram colunas de deficiência/TEA/AH — o indicador de inclusão fica limitado.'),
            );
        }

        if ($hasNeeCol && $underFlagged > 0 && $flagged > 0 && ($underFlagged / max(1, $flagged)) >= 0.15) {
            $this->addFinding(
                $campaign,
                'CLIO-NEE-SUB',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Possível subnotificação / comorbidade em :n de :s aluno(s) com NEE (:p%). Revise deficiências × transtornos e tipificação.', [
                    'n' => $underFlagged,
                    's' => $flagged,
                    'p' => round(100 * $underFlagged / max(1, $flagged), 1),
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-NEE',
            $hasNeeCol
                ? __('Inclusão: :n com marcador (:p%) · deficiências :d · transtornos :t · AH :a · alertas de subnotificação :u.', [
                    'n' => $flagged,
                    'p' => $scanned > 0 ? round(100 * $flagged / $scanned, 1) : 0,
                    'd' => $defFlagged,
                    't' => $disorderFlagged,
                    'a' => $ahFlagged,
                    'u' => $underFlagged,
                ])
                : __('Inclusão: colunas NEE/TEA/AH não detectadas nas Relações importadas (:s linhas).', [
                    's' => $scanned,
                ]),
            [
                'flagged' => $flagged,
                'scanned' => $scanned,
                'by_nee' => $byNee,
                'by_deficiency' => $byDef,
                'by_disorder' => $byDisorder,
                'by_ah' => $byAh,
                'deficiency_flagged' => $defFlagged,
                'disorder_flagged' => $disorderFlagged,
                'ah_flagged' => $ahFlagged,
                'by_underreporting' => $byUnder,
                'underreporting_flagged' => $underFlagged,
                'has_nee_columns' => $hasNeeCol,
                'note_def_vs_trs' => __('Deficiências (DEF-*) e transtornos (TRS-*, ex. TEA) são públicos distintos no Censo; AH é categoria própria.'),
                'note_sub' => __('Alertas de subnotificação são heurísticos (comorbidades frequentes e tipificação incompleta) — validar com a escola/laudo.'),
            ],
        );
    }

    private function inferTransporte(ClioCampaign $campaign): void
    {
        $flagged = 0;
        $scanned = 0;
        $without = 0;
        $semPoder = 0;
        $byTransporte = [];
        $byPoder = [];
        $byVeiculo = [];
        $byLocationUsers = [];
        $byLocationUsersActive = [];
        $byLocationUsersOther = [];
        $byVeiculoActive = [];
        $byVeiculoOther = [];
        $flaggedActive = 0;
        $scannedActive = 0;
        $flaggedOther = 0;
        $scannedOther = 0;
        $hasCol = false;
        $hasPoderCol = false;
        $hasVeiculoCol = false;
        $schoolsBreakdown = [];
        $seenArtifactIds = [];

        foreach ($campaign->schools as $school) {
            $schoolAgg = $this->emptyAlunoAgg();
            foreach ($school->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
                $seenArtifactIds[(int) $artifact->id] = true;
                $schoolAgg = $this->mergeAlunoAgg(
                    $schoolAgg,
                    $this->resolveAlunoAggregates($artifact, (int) $campaign->year),
                );
            }

            $schoolTotal = (int) ($schoolAgg['total'] ?? 0);
            if ($schoolTotal === 0) {
                continue;
            }

            $schoolFlagged = (int) ($schoolAgg['transporte_flagged'] ?? 0);
            $schoolWithout = (int) ($schoolAgg['without_transporte'] ?? 0);
            $schoolSemPoder = (int) ($schoolAgg['transporte_sem_poder'] ?? 0);
            $cols = is_array($schoolAgg['columns'] ?? null) ? $schoolAgg['columns'] : [];
            if (! empty($cols['transporte'])) {
                $hasCol = true;
            }
            if (! empty($cols['poder_publico_transporte'])) {
                $hasPoderCol = true;
            }
            if (! empty($cols['veiculo_transporte'])) {
                $hasVeiculoCol = true;
            }

            $meta = is_array($school->meta) ? $school->meta : [];
            $location = $this->normalizeSchoolLocation((string) ($meta['location'] ?? ''));
            $inactive = CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status);
            $byVeiculoSchool = is_array($schoolAgg['by_veiculo_transporte'] ?? null)
                ? $schoolAgg['by_veiculo_transporte']
                : [];
            $byPoderSchool = is_array($schoolAgg['by_poder_publico_transporte'] ?? null)
                ? $schoolAgg['by_poder_publico_transporte']
                : [];
            $byUsoSchool = is_array($schoolAgg['by_transporte'] ?? null)
                ? $schoolAgg['by_transporte']
                : [];

            $scanned += $schoolTotal;
            $flagged += $schoolFlagged;
            $without += $schoolWithout;
            $semPoder += $schoolSemPoder;
            $byTransporte = $this->aggregator->mergeCounts($byTransporte, $byUsoSchool);
            $byPoder = $this->aggregator->mergeCounts($byPoder, $byPoderSchool);
            $byVeiculo = $this->aggregator->mergeCounts($byVeiculo, $byVeiculoSchool);

            if ($schoolFlagged > 0) {
                $byLocationUsers[$location] = ($byLocationUsers[$location] ?? 0) + $schoolFlagged;
            }

            if ($inactive) {
                $scannedOther += $schoolTotal;
                $flaggedOther += $schoolFlagged;
                if ($schoolFlagged > 0) {
                    $byLocationUsersOther[$location] = ($byLocationUsersOther[$location] ?? 0) + $schoolFlagged;
                }
                $byVeiculoOther = $this->aggregator->mergeCounts($byVeiculoOther, $byVeiculoSchool);
            } else {
                $scannedActive += $schoolTotal;
                $flaggedActive += $schoolFlagged;
                if ($schoolFlagged > 0) {
                    $byLocationUsersActive[$location] = ($byLocationUsersActive[$location] ?? 0) + $schoolFlagged;
                }
                $byVeiculoActive = $this->aggregator->mergeCounts($byVeiculoActive, $byVeiculoSchool);
            }

            $schoolsBreakdown[] = [
                'inep' => $school->inep_code,
                'name' => $school->name,
                'functioning' => $school->functioning_status,
                'location' => $location,
                'inactive' => $inactive,
                'scanned' => $schoolTotal,
                'flagged' => $schoolFlagged,
                'pct' => $schoolTotal > 0 ? round(100 * $schoolFlagged / $schoolTotal, 1) : 0,
                'without' => $schoolWithout,
                'sem_poder' => $schoolSemPoder,
                'by_transporte' => $byUsoSchool,
                'by_poder_publico' => $byPoderSchool,
                'by_veiculo' => $byVeiculoSchool,
                'has_transporte' => ! empty($cols['transporte']),
                'has_veiculo' => ! empty($cols['veiculo_transporte']),
                'has_poder' => ! empty($cols['poder_publico_transporte']),
            ];
        }

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            if (isset($seenArtifactIds[(int) $artifact->id])) {
                continue;
            }
            $agg = $this->resolveAlunoAggregates($artifact, (int) $campaign->year);
            $scanned += (int) ($agg['total'] ?? 0);
            $flagged += (int) ($agg['transporte_flagged'] ?? 0);
            $without += (int) ($agg['without_transporte'] ?? 0);
            $semPoder += (int) ($agg['transporte_sem_poder'] ?? 0);
            $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
            if (! empty($cols['transporte'])) {
                $hasCol = true;
            }
            if (! empty($cols['poder_publico_transporte'])) {
                $hasPoderCol = true;
            }
            if (! empty($cols['veiculo_transporte'])) {
                $hasVeiculoCol = true;
            }
            $byTransporte = $this->aggregator->mergeCounts(
                $byTransporte,
                is_array($agg['by_transporte'] ?? null) ? $agg['by_transporte'] : [],
            );
            $byPoder = $this->aggregator->mergeCounts(
                $byPoder,
                is_array($agg['by_poder_publico_transporte'] ?? null) ? $agg['by_poder_publico_transporte'] : [],
            );
            $byVeiculo = $this->aggregator->mergeCounts(
                $byVeiculo,
                is_array($agg['by_veiculo_transporte'] ?? null) ? $agg['by_veiculo_transporte'] : [],
            );
        }

        if (! $hasCol && $scanned > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-TRA-SEM-COL',
                ClioCampaignFinding::SEVERITY_INFO,
                __('As Relações de alunos desta coleta não trouxeram colunas de transporte escolar — o indicador fica indisponível.'),
            );
        }

        if ($hasCol && $scanned > 0 && $without > 0 && ($without / $scanned) >= 0.2) {
            $this->addFinding(
                $campaign,
                'CLIO-TRA-VAZIO',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Em :n de :s matrículas (:p%) o uso de transporte escolar não foi informado.', [
                    'n' => $without,
                    's' => $scanned,
                    'p' => round(100 * $without / $scanned, 1),
                ]),
            );
        }

        if ($hasCol && $hasPoderCol && $flagged > 0 && $semPoder > 0 && ($semPoder / max(1, $flagged)) >= 0.1) {
            $this->addFinding(
                $campaign,
                'CLIO-TRA-SEM-PODER',
                ClioCampaignFinding::SEVERITY_WARNING,
                __(':n aluno(s) usam transporte escolar sem poder público responsável informado (:p% dos que usam).', [
                    'n' => $semPoder,
                    'p' => round(100 * $semPoder / max(1, $flagged), 1),
                ]),
            );
        }

        $ruralUsers = (int) ($byLocationUsersActive[__('Rural')] ?? $byLocationUsers[__('Rural')] ?? 0);
        if ($hasCol && $flaggedActive > 0 && $ruralUsers > 0 && ($ruralUsers / max(1, $flaggedActive)) >= 0.5) {
            $this->addFinding(
                $campaign,
                'CLIO-TRA-RURAL',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Nas escolas ativas, :p% dos alunos que usam transporte estão em unidades rurais (:n). Priorize logística e tipo de veículo.', [
                    'p' => round(100 * $ruralUsers / max(1, $flaggedActive), 1),
                    'n' => $ruralUsers,
                ]),
            );
        }

        $pct = $scanned > 0 ? round(100 * $flagged / $scanned, 1) : 0;
        $pctActive = $scannedActive > 0 ? round(100 * $flaggedActive / $scannedActive, 1) : 0;

        usort($schoolsBreakdown, static function (array $a, array $b): int {
            return ($b['flagged'] <=> $a['flagged']) ?: ($b['scanned'] <=> $a['scanned']);
        });

        $veiculoTipos = count(array_filter(
            array_keys($byVeiculo),
            static fn ($k) => (string) $k !== __('Não informado'),
        ));

        $this->upsertInference(
            $campaign,
            'INF-TRA',
            $hasCol
                ? __('Transporte: :n usam (:p%) · ativas :na (:pa%) · rural :r · urbano :u · tipos de veículo: :v.', [
                    'n' => $flagged,
                    'p' => $pct,
                    'na' => $flaggedActive,
                    'pa' => $pctActive,
                    'r' => (int) ($byLocationUsers[__('Rural')] ?? 0),
                    'u' => (int) ($byLocationUsers[__('Urbana')] ?? 0),
                    'v' => $veiculoTipos,
                ])
                : __('Transporte escolar: colunas não detectadas nas Relações importadas (:s linhas).', [
                    's' => $scanned,
                ]),
            [
                'flagged' => $flagged,
                'scanned' => $scanned,
                'pct' => $pct,
                'without' => $without,
                'sem_poder' => $semPoder,
                'by_transporte' => $byTransporte,
                'by_poder_publico' => $byPoder,
                'by_veiculo' => $byVeiculo,
                'by_location_users' => $this->aggregator->mergeCounts([], $byLocationUsers),
                'has_transporte_columns' => $hasCol,
                'has_poder_publico' => $hasPoderCol,
                'has_veiculo' => $hasVeiculoCol,
                'active' => [
                    'flagged' => $flaggedActive,
                    'scanned' => $scannedActive,
                    'pct' => $pctActive,
                    'by_location_users' => $this->aggregator->mergeCounts([], $byLocationUsersActive),
                    'by_veiculo' => $byVeiculoActive,
                ],
                'other' => [
                    'flagged' => $flaggedOther,
                    'scanned' => $scannedOther,
                    'pct' => $scannedOther > 0 ? round(100 * $flaggedOther / $scannedOther, 1) : 0,
                    'by_location_users' => $this->aggregator->mergeCounts([], $byLocationUsersOther),
                    'by_veiculo' => $byVeiculoOther,
                ],
                'schools' => array_slice($schoolsBreakdown, 0, 120),
                'note_location' => __('Rural/urbano vem da Localização da escola no Acompanhamento; o uso e o tipo de veículo vêm da Relação de alunos.'),
            ],
        );
    }

    private function normalizeSchoolLocation(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return __('Não informado');
        }
        if (preg_match('/rural/u', $s) === 1) {
            return __('Rural');
        }
        if (preg_match('/urban/u', $s) === 1) {
            return __('Urbana');
        }

        return $raw;
    }

    /**
     * Tempo de escolarização / turnos / CH e padrões de jornada (fund+AEE, AC, infantil estendido).
     */
    private function inferJornada(ClioCampaign $campaign): void
    {
        $people = 0;
        $fundAee = 0;
        $currAc = 0;
        $infantilExt = 0;
        $multi = 0;
        $turmas = 0;
        $byTurno = [];
        $byChBand = [];
        $byTurnoCurricular = [];
        $hasTurnoCol = false;
        $hasChCol = false;
        $schoolsBreakdown = [];
        $disk = (string) config('clio.disk', 'local');

        foreach ($campaign->schools as $school) {
            $turmaArt = $school->artifacts->firstWhere('kind', 'relacao_turma_escola');
            $alunoArt = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            if ($turmaArt === null && $alunoArt === null) {
                continue;
            }

            $profiles = [];
            $schoolByTurno = [];
            $schoolByCh = [];
            $schoolHasTurno = false;
            $schoolHasCh = false;
            $schoolTurmas = 0;

            if ($turmaArt !== null) {
                try {
                    $turmaData = $this->csv->read(Storage::disk($disk)->path($turmaArt->storage_path), 1);
                    $turmaAgg = $this->aggregator->aggregateTurmas($turmaData['rows'], $this->csv);
                } catch (Throwable) {
                    $turmaAgg = $this->emptyTurmaAgg();
                }
                $profiles = is_array($turmaAgg['turma_profiles'] ?? null) ? $turmaAgg['turma_profiles'] : [];
                $schoolByTurno = is_array($turmaAgg['by_turno'] ?? null) ? $turmaAgg['by_turno'] : [];
                $schoolByCh = is_array($turmaAgg['by_ch_band'] ?? null) ? $turmaAgg['by_ch_band'] : [];
                $cols = is_array($turmaAgg['columns'] ?? null) ? $turmaAgg['columns'] : [];
                $schoolHasTurno = ! empty($cols['turno']);
                $schoolHasCh = ! empty($cols['carga_horaria']);
                $schoolTurmas = (int) ($turmaAgg['total'] ?? 0);
                $turmas += $schoolTurmas;
                $byTurno = $this->aggregator->mergeCounts($byTurno, $schoolByTurno);
                $byChBand = $this->aggregator->mergeCounts($byChBand, $schoolByCh);
                if ($schoolHasTurno) {
                    $hasTurnoCol = true;
                }
                if ($schoolHasCh) {
                    $hasChCol = true;
                }
            }

            $pattern = [
                'people' => 0,
                'fund_aee_contraturno' => 0,
                'curricular_ac' => 0,
                'infantil_turma_estendida' => 0,
                'multi_enrollment' => 0,
                'by_turno_curricular' => [],
                'columns_turno' => $schoolHasTurno,
                'columns_ch' => $schoolHasCh,
            ];

            if ($alunoArt !== null && $profiles !== []) {
                try {
                    $alunoData = $this->csv->read(Storage::disk($disk)->path($alunoArt->storage_path), 1);
                    $pattern = $this->aggregator->aggregateEnrollmentDayPatterns(
                        $alunoData['rows'],
                        $this->csv,
                        $profiles,
                    );
                } catch (Throwable) {
                    // mantém zeros
                }
            }

            $people += (int) $pattern['people'];
            $fundAee += (int) $pattern['fund_aee_contraturno'];
            $currAc += (int) $pattern['curricular_ac'];
            $infantilExt += (int) $pattern['infantil_turma_estendida'];
            $multi += (int) $pattern['multi_enrollment'];
            $byTurnoCurricular = $this->aggregator->mergeCounts(
                $byTurnoCurricular,
                is_array($pattern['by_turno_curricular'] ?? null) ? $pattern['by_turno_curricular'] : [],
            );

            if ($schoolTurmas === 0 && (int) $pattern['people'] === 0) {
                continue;
            }

            $schoolsBreakdown[] = [
                'inep' => $school->inep_code,
                'name' => $school->name,
                'functioning' => $school->functioning_status,
                'turmas' => $schoolTurmas,
                'people' => (int) $pattern['people'],
                'fund_aee_contraturno' => (int) $pattern['fund_aee_contraturno'],
                'curricular_ac' => (int) $pattern['curricular_ac'],
                'infantil_turma_estendida' => (int) $pattern['infantil_turma_estendida'],
                'multi_enrollment' => (int) $pattern['multi_enrollment'],
                'by_turno' => $schoolByTurno,
                'by_ch_band' => $schoolByCh,
                'has_turno' => $schoolHasTurno,
                'has_ch' => $schoolHasCh,
            ];
        }

        if (! $hasTurnoCol && ! $hasChCol && $turmas > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-JOR-SEM-COL',
                ClioCampaignFinding::SEVERITY_INFO,
                __('As Relações de turmas não trouxeram Turno nem Carga horária — o quadro de funcionamento das turmas fica limitado aos padrões de matrícula (AEE/AC).'),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-JOR',
            __('Jornada: :aee aluno(s) fund. + AEE · :ac com atividade complementar · :inf infantil em turma estendida · :m com mais de uma matrícula.', [
                'aee' => $fundAee,
                'ac' => $currAc,
                'inf' => $infantilExt,
                'm' => $multi,
            ]),
            [
                'people' => $people,
                'turmas' => $turmas,
                'fund_aee_contraturno' => $fundAee,
                'curricular_ac' => $currAc,
                'infantil_turma_estendida' => $infantilExt,
                'multi_enrollment' => $multi,
                'by_turno' => $byTurno,
                'by_ch_band' => $byChBand,
                'by_turno_curricular' => $byTurnoCurricular,
                'has_turno_columns' => $hasTurnoCol,
                'has_ch_columns' => $hasChCol,
                'schools' => array_slice($schoolsBreakdown, 0, 120),
                'note_fund_aee' => __('Fundamental regular + AEE em outra matrícula (contraturno típico) — não confundir com atividade complementar.'),
                'note_infantil' => __('Infantil em turma única com turno/CH estendido — diferente de tempo integral por duas matrículas.'),
            ],
        );
    }

    /**
     * Perfil demográfico agregado (Cor/Raça, sexo, faixa etária) — sem PII.
     */
    private function inferDemografia(ClioCampaign $campaign): void
    {
        $total = 0;
        $byCor = [];
        $bySexo = [];
        $byIdade = [];
        $withoutCor = 0;
        $withoutSexo = 0;
        $withoutNasc = 0;
        $cols = [
            'cor_raca' => false,
            'sexo' => false,
            'nascimento' => false,
            'nee' => false,
            'transporte' => false,
            'poder_publico' => false,
        ];

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            $agg = $this->resolveAlunoAggregates($artifact, (int) $campaign->year);
            $total += (int) ($agg['total'] ?? 0);
            $byCor = $this->aggregator->mergeCounts($byCor, is_array($agg['by_cor_raca'] ?? null) ? $agg['by_cor_raca'] : []);
            $bySexo = $this->aggregator->mergeCounts($bySexo, is_array($agg['by_sexo'] ?? null) ? $agg['by_sexo'] : []);
            $byIdade = $this->mergeAgeBands($byIdade, is_array($agg['by_faixa_etaria'] ?? null) ? $agg['by_faixa_etaria'] : []);
            $withoutCor += (int) ($agg['without_cor'] ?? 0);
            $withoutSexo += (int) ($agg['without_sexo'] ?? 0);
            $withoutNasc += (int) ($agg['without_nascimento'] ?? 0);
            $c = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
            foreach (array_keys($cols) as $key) {
                if (! empty($c[$key])) {
                    $cols[$key] = true;
                }
            }
        }

        if ($total > 0 && ! $cols['cor_raca']) {
            $this->addFinding(
                $campaign,
                'CLIO-DEM-SEM-COR',
                ClioCampaignFinding::SEVERITY_INFO,
                __('As Relações de alunos não trouxeram a coluna Cor/Raça — o perfil racial não pode ser calculado neste export.'),
            );
        }
        if ($total > 0 && ! $cols['sexo']) {
            $this->addFinding(
                $campaign,
                'CLIO-DEM-SEM-SEXO',
                ClioCampaignFinding::SEVERITY_INFO,
                __('As Relações de alunos não trouxeram a coluna Sexo — o perfil por sexo não pode ser calculado neste export.'),
            );
        }
        if ($cols['cor_raca'] && $withoutCor > 0 && $total > 0 && ($withoutCor / $total) >= 0.2) {
            $this->addFinding(
                $campaign,
                'CLIO-DEM-COR-VAZIO',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Cor/Raça em branco em :n de :t matrículas (:p%). Complete no portal para o indicador ficar confiável.', [
                    'n' => $withoutCor,
                    't' => $total,
                    'p' => round(100 * $withoutCor / $total, 1),
                ]),
            );
        }

        $parts = [];
        if ($cols['cor_raca']) {
            $parts[] = __('Cor/Raça');
        }
        if ($cols['sexo']) {
            $parts[] = __('sexo');
        }
        if ($cols['nascimento']) {
            $parts[] = __('faixa etária');
        }

        $this->upsertInference(
            $campaign,
            'INF-DEM',
            $parts === []
                ? __('Perfil demográfico: nenhuma coluna Cor/Raça, Sexo ou Data de nascimento detectada nas Relações (:n linhas).', [
                    'n' => $total,
                ])
                : __('Perfil demográfico disponível (:campos) em :n matrícula(s) das Relações.', [
                    'campos' => implode(', ', $parts),
                    'n' => $total,
                ]),
            [
                'scanned' => $total,
                'by_cor_raca' => $byCor,
                'by_sexo' => $bySexo,
                'by_faixa_etaria' => $byIdade,
                'without_cor' => $withoutCor,
                'without_sexo' => $withoutSexo,
                'without_nascimento' => $withoutNasc,
                'columns' => $cols,
                'social_note' => __('Vulnerabilidade social (CadÚnico/Bolsa Família) não vem nos CSV do Educacenso 1ª etapa. Use o módulo CadÚnico do ServLitcys ou cruzamento autorizado.'),
            ],
        );
    }

    /**
     * @param  array<string, int>  $into
     * @param  array<string, int>  $from
     * @return array<string, int>
     */
    private function mergeAgeBands(array $into, array $from): array
    {
        foreach ($from as $k => $v) {
            $into[$k] = ($into[$k] ?? 0) + (int) $v;
        }

        return $into;
    }

    private function inferCoerencia(ClioCampaign $campaign): void
    {
        $coverage = $this->parseService->coverage($campaign);
        $missingTriade = 0;

        foreach ($coverage['schools'] as $row) {
            if (! empty($row['inactive'])) {
                continue;
            }
            if (! ($row['triade'] ?? false)) {
                $missingTriade++;
                $school = $campaign->schools->firstWhere('inep_code', $row['inep']);
                $this->addFinding(
                    $campaign,
                    'CLIO-COE-TRIADE',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('Faltam arquivos da tríade (alunos, turmas e/ou profissionais) nesta escola.'),
                    $school,
                    null,
                    [
                        'aluno' => $row['aluno'],
                        'turma' => $row['turma'],
                        'profissional' => $row['profissional'],
                    ],
                );
            }
        }

        if (! ($coverage['has_acomp'] ?? false)) {
            $this->addFinding(
                $campaign,
                'CLIO-COE-ACOMP',
                ClioCampaignFinding::SEVERITY_INFO,
                __('Ainda não há Relatório de Acompanhamento municipal nesta coleta — os totais oficiais do portal ficam indisponíveis.'),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-COE',
            __('Arquivos (escolas em atividade): :m sem tríade completa · cobertura :p%.', [
                'm' => $missingTriade,
                'p' => $coverage['triade_coverage_pct'],
            ]),
            [
                'schools_missing_triade' => $missingTriade,
                'schools_active' => (int) ($coverage['schools_active'] ?? 0),
                'schools_other' => (int) ($coverage['schools_other'] ?? 0),
                'schools_triade_complete' => (int) ($coverage['schools_triade_complete'] ?? 0),
                'triade_coverage_pct' => $coverage['triade_coverage_pct'],
                'has_acomp' => $coverage['has_acomp'],
            ],
        );
    }

    private function inferDuplicidades(ClioCampaign $campaign): void
    {
        $disk = (string) config('clio.disk', 'local');
        $seen = [];
        $dupes = 0;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            try {
                $data = $this->csv->read(Storage::disk($disk)->path($artifact->storage_path), 1);
            } catch (Throwable) {
                continue;
            }

            foreach ($data['rows'] as $row) {
                $id = $this->csv->value($row, 'Identificação única');
                if ($id === '') {
                    $id = $this->csv->value($row, 'Código da Matrícula');
                }
                if ($id === '') {
                    continue;
                }
                $key = mb_strtolower($id);
                if (isset($seen[$key])) {
                    $dupes++;
                    if ($dupes <= 50) {
                        $this->addFinding(
                            $campaign,
                            'CLIO-DUP-ID',
                            ClioCampaignFinding::SEVERITY_WARNING,
                            __('O mesmo identificador aparece mais de uma vez na Relação de alunos (amostra: :id).', [
                                'id' => $this->maskIdentifier($id),
                            ]),
                            $artifact->school,
                            $artifact,
                        );
                    }
                } else {
                    $seen[$key] = true;
                }
            }
        }

        $this->upsertInference(
            $campaign,
            'INF-DUP',
            __('Possíveis duplicidades de identificação: :n ocorrência(s).', ['n' => $dupes]),
            ['duplicate_ids' => $dupes, 'unique_ids' => count($seen)],
        );
    }

    private function inferDelta(ClioCampaign $campaign): void
    {
        $deltas = [];
        $divergent = 0;
        $divergentAee = 0;
        $divergentAc = 0;

        foreach ($campaign->schools as $school) {
            $meta = is_array($school->meta) ? $school->meta : [];
            $acomp = $meta['total_curricular'] ?? null;
            $aluno = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            if ($aluno !== null && is_numeric($acomp)) {
                $rows = (int) ($aluno->row_count ?? 0);
                $diff = $rows - (int) $acomp;
                if ($diff !== 0) {
                    $divergent++;
                    $deltas[] = [
                        'inep' => $school->inep_code,
                        'acomp' => (int) $acomp,
                        'relacao' => $rows,
                        'diff' => $diff,
                    ];
                    $this->addFinding(
                        $campaign,
                        'CLIO-DELTA-MAT',
                        ClioCampaignFinding::SEVERITY_INFO,
                        __('Diferença de matrícula curricular: Acompanhamento indica :a, Relação de alunos tem :r linha(s).', [
                            'a' => (int) $acomp,
                            'r' => $rows,
                        ]),
                        $school,
                        $aluno,
                        ['diff' => $diff],
                    );
                }
            }

            $turmaArt = $school->artifacts->firstWhere('kind', 'relacao_turma_escola');
            if ($turmaArt === null) {
                continue;
            }
            $turmaAgg = $this->resolveTurmaAggregates($turmaArt);
            $aeeAcomp = $meta['total_aee'] ?? null;
            $aeeTurmas = (int) ($turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_AEE] ?? 0);
            if (is_numeric($aeeAcomp) && (int) $aeeAcomp > 0 && $aeeTurmas === 0) {
                $divergentAee++;
            }
            $acAcomp = $meta['total_ac'] ?? null;
            $acTurmas = (int) ($turmaAgg['by_tipo_bucket'][RelationCsvAggregator::BUCKET_AC] ?? 0);
            if (is_numeric($acAcomp) && (int) $acAcomp > 0 && $acTurmas === 0) {
                $divergentAc++;
                $this->addFinding(
                    $campaign,
                    'CLIO-DELTA-AC',
                    ClioCampaignFinding::SEVERITY_INFO,
                    __('O Acompanhamento declara :n matrícula(s) em Atividade Complementar, mas não há turma AC na Relação.', [
                        'n' => (int) $acAcomp,
                    ]),
                    $school,
                    $turmaArt,
                );
            }
        }

        $this->upsertInference(
            $campaign,
            'INF-DELTA',
            __('Escolas com diferença curricular: :n · AEE sem turma: :aee · AC sem turma: :ac.', [
                'n' => $divergent,
                'aee' => $divergentAee,
                'ac' => $divergentAc,
            ]),
            [
                'divergent_schools' => $divergent,
                'divergent_aee_schools' => $divergentAee,
                'divergent_ac_schools' => $divergentAc,
                'samples' => array_slice($deltas, 0, 20),
            ],
        );
    }

    /**
     * Conferências cruzadas municipais: Acomp (arquivo geral) × Relação aluno × Relação turma.
     * O Acomp oficial não desagrega por ano/etapa — o cruzamento por ano usa só as Relações.
     */
    private function inferCruzamentos(ClioCampaign $campaign): void
    {
        $mat = ClioCampaignInference::query()
            ->where('campaign_id', $campaign->id)
            ->where('code', 'INF-MAT')
            ->first();
        $tur = ClioCampaignInference::query()
            ->where('campaign_id', $campaign->id)
            ->where('code', 'INF-TUR')
            ->first();

        $matPayload = is_array($mat?->payload) ? $mat->payload : [];
        $turPayload = is_array($tur?->payload) ? $tur->payload : [];

        $acompCurricular = (int) ($matPayload['acomp_curricular_sum'] ?? 0);
        $acompAee = (int) ($matPayload['acomp_aee_sum'] ?? 0);
        $acompAc = (int) ($matPayload['acomp_ac_sum'] ?? 0);
        $relacaoAlunos = (int) ($matPayload['relacao_aluno_rows'] ?? 0);
        $hasAcomp = $acompCurricular > 0 || $acompAee > 0 || $acompAc > 0
            || $campaign->artifacts->contains(fn ($a) => $a->kind === 'acomp_coleta_1etapa');

        $networkDelta = $hasAcomp && $relacaoAlunos > 0
            ? $relacaoAlunos - $acompCurricular
            : null;

        if ($networkDelta !== null && $networkDelta !== 0) {
            $this->addFinding(
                $campaign,
                'CLIO-DELTA-REDE',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Totais da rede: o arquivo geral (Acomp) soma :a matrículas curriculares e a Relação de alunos tem :r linha(s) — diferença :d.', [
                    'a' => $acompCurricular,
                    'r' => $relacaoAlunos,
                    'd' => ($networkDelta > 0 ? '+' : '').$networkDelta,
                ]),
            );
        }

        $alunoByEtapa = is_array($matPayload['by_etapa_ensino'] ?? null) ? $matPayload['by_etapa_ensino'] : [];
        $turmaByEtapa = is_array($turPayload['by_etapa_ensino'] ?? null) ? $turPayload['by_etapa_ensino'] : [];
        $etapas = array_unique(array_merge(array_keys($alunoByEtapa), array_keys($turmaByEtapa)));
        sort($etapas);

        $etapaRows = [];
        $alunosSemTurmaEtapa = 0;
        $turmasSemAlunoEtapa = 0;

        foreach ($etapas as $etapa) {
            $alunos = (int) ($alunoByEtapa[$etapa] ?? 0);
            $turmas = (int) ($turmaByEtapa[$etapa] ?? 0);
            $flag = null;
            if ($alunos > 0 && $turmas === 0) {
                $flag = 'alunos_sem_turma';
                $alunosSemTurmaEtapa++;
            } elseif ($turmas > 0 && $alunos === 0) {
                $flag = 'turma_sem_aluno';
                $turmasSemAlunoEtapa++;
            }

            $etapaRows[] = [
                'etapa' => $etapa,
                'alunos' => $alunos,
                'turmas' => $turmas,
                'flag' => $flag,
            ];
        }

        usort($etapaRows, static function (array $a, array $b): int {
            $prio = static fn (?string $f): int => match ($f) {
                'alunos_sem_turma' => 0,
                'turma_sem_aluno' => 1,
                default => 2,
            };
            $pa = $prio($a['flag']);
            $pb = $prio($b['flag']);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ($b['alunos'] + $b['turmas']) <=> ($a['alunos'] + $a['turmas']);
        });

        if ($alunosSemTurmaEtapa > 0) {
            $this->addFinding(
                $campaign,
                'CLIO-XCHK-ETAPA',
                ClioCampaignFinding::SEVERITY_WARNING,
                __('Em :n etapa(s)/ano(s) há alunos na Relação sem turma correspondente na mesma etapa. O arquivo geral (Acomp) não informa totais por ano — esta conferência usa só as Relações.', [
                    'n' => $alunosSemTurmaEtapa,
                ]),
            );
        }

        $okNetwork = $networkDelta === null || $networkDelta === 0;
        $okEtapa = $alunosSemTurmaEtapa === 0;

        $this->upsertInference(
            $campaign,
            'INF-XCHK',
            $hasAcomp
                ? __('Cruzamentos: Acomp curricular :a × Relação alunos :r (:delta) · etapas com alunos sem turma: :e.', [
                    'a' => $acompCurricular,
                    'r' => $relacaoAlunos,
                    'delta' => $networkDelta === null
                        ? __('sem comparação')
                        : (($networkDelta === 0) ? __('ok') : (($networkDelta > 0 ? '+' : '').$networkDelta)),
                    'e' => $alunosSemTurmaEtapa,
                ])
                : __('Cruzamentos: sem arquivo geral (Acomp) — conferência por etapa só entre Relação aluno e Relação turma (:e etapa(s) com alunos sem turma).', [
                    'e' => $alunosSemTurmaEtapa,
                ]),
            [
                'has_acomp' => $hasAcomp,
                'acomp_has_by_etapa' => false,
                'acomp_by_etapa_note' => __('O Relatório de Acompanhamento (arquivo geral) traz totais por escola (curricular/AEE/AC), sem desagregação por ano/etapa.'),
                'network' => [
                    'acomp_curricular' => $acompCurricular,
                    'acomp_aee' => $acompAee,
                    'acomp_ac' => $acompAc,
                    'relacao_aluno_rows' => $relacaoAlunos,
                    'delta_curricular' => $networkDelta,
                    'ok' => $okNetwork,
                ],
                'etapa_compare' => array_slice($etapaRows, 0, 40),
                'etapas_alunos_sem_turma' => $alunosSemTurmaEtapa,
                'etapas_turmas_sem_aluno' => $turmasSemAlunoEtapa,
                'etapa_ok' => $okEtapa,
            ],
        );
    }

    private function maskIdentifier(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '***';
        }
        if (preg_match('/^\d{11}$/', $id) === 1) {
            return '[redacted]';
        }
        $len = mb_strlen($id);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return mb_substr($id, 0, 2).str_repeat('*', max(0, $len - 4)).mb_substr($id, -2);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTurmaAggregates(ClioCampaignArtifact $artifact): array
    {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['by_etapa_ensino'], $agg['by_tipo_bucket'])) {
            return $this->normalizeTurmaAgg($agg);
        }

        $disk = (string) config('clio.disk', 'local');
        try {
            $data = $this->csv->read(Storage::disk($disk)->path($artifact->storage_path), 1);
        } catch (Throwable) {
            return $this->emptyTurmaAgg();
        }

        return $this->aggregator->aggregateTurmas($data['rows'], $this->csv);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAlunoAggregates(ClioCampaignArtifact $artifact, ?int $referenceYear = null): array
    {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['by_etapa_ensino'], $agg['columns'])) {
            return $this->normalizeAlunoAgg($agg);
        }

        $disk = (string) config('clio.disk', 'local');
        try {
            $data = $this->csv->read(Storage::disk($disk)->path($artifact->storage_path), 1);
        } catch (Throwable) {
            return $this->emptyAlunoAgg();
        }

        $year = $referenceYear
            ?? ($artifact->campaign?->year ? (int) $artifact->campaign->year : null);

        return $this->aggregator->aggregateAlunos($data['rows'], $this->csv, $year);
    }

    private function resolveProfissionalAggregates(ClioCampaignArtifact $artifact): array
    {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['by_turma'])) {
            return [
                'total' => (int) ($agg['total'] ?? 0),
                'by_turma' => is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [],
                'without_turma' => (int) ($agg['without_turma'] ?? 0),
                'docente_rows' => (int) ($agg['docente_rows'] ?? $agg['total'] ?? 0),
            ];
        }

        $disk = (string) config('clio.disk', 'local');
        try {
            $data = $this->csv->read(
                Storage::disk($disk)->path($artifact->storage_path),
                \App\Services\Clio\Parse\RelacaoProfissionalEscolaParser::HEADER_OFFSET,
            );
        } catch (Throwable) {
            return [
                'total' => 0,
                'by_turma' => [],
                'without_turma' => 0,
                'docente_rows' => 0,
            ];
        }

        return $this->aggregator->aggregateProfissionais($data['rows'], $this->csv);
    }

    /**
     * @return array{
     *   total: int,
     *   by_etapa_ensino: array<string, int>,
     *   by_etapa_agregada: array<string, int>,
     *   by_tipo_turma: array<string, int>,
     *   by_mediacao: array<string, int>,
     *   by_tipo_bucket: array{curricular: int, aee: int, atividade_complementar: int, outra: int},
     *   turma_codes: list<string>,
     *   without_etapa: int,
     *   without_tipo: int
     * }
     */
    private function emptyTurmaAgg(): array
    {
        return [
            'total' => 0,
            'by_etapa_ensino' => [],
            'by_etapa_agregada' => [],
            'by_tipo_turma' => [],
            'by_mediacao' => [],
            'by_tipo_bucket' => [
                RelationCsvAggregator::BUCKET_CURRICULAR => 0,
                RelationCsvAggregator::BUCKET_AEE => 0,
                RelationCsvAggregator::BUCKET_AC => 0,
                RelationCsvAggregator::BUCKET_OUTRA => 0,
            ],
            'by_turno' => [],
            'by_ch_band' => [],
            'turma_codes' => [],
            'turma_profiles' => [],
            'without_etapa' => 0,
            'without_tipo' => 0,
            'columns' => [
                'turno' => false,
                'carga_horaria' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAlunoAgg(): array
    {
        return [
            'total' => 0,
            'by_etapa_ensino' => [],
            'without_etapa' => 0,
            'without_turma' => 0,
            'by_turma' => [],
            'by_cor_raca' => [],
            'by_sexo' => [],
            'by_faixa_etaria' => [],
            'by_nee' => [],
            'nee_flagged' => 0,
            'by_deficiency' => [],
            'by_disorder' => [],
            'by_ah' => [],
            'deficiency_flagged' => 0,
            'disorder_flagged' => 0,
            'ah_flagged' => 0,
            'by_underreporting' => [],
            'underreporting_flagged' => 0,
            'by_transporte' => [],
            'transporte_flagged' => 0,
            'without_transporte' => 0,
            'transporte_sem_poder' => 0,
            'by_poder_publico_transporte' => [],
            'by_veiculo_transporte' => [],
            'without_cor' => 0,
            'without_sexo' => 0,
            'without_nascimento' => 0,
            'age_grade' => [
                'eligible' => 0,
                'distorcao' => 0,
                'atraso_1' => 0,
                'adequado' => 0,
                'adiantado' => 0,
                'indefinido' => 0,
                'excluido' => 0,
                'by_etapa' => [],
                'pct_distorcao' => null,
            ],
            'columns' => [
                'cor_raca' => false,
                'sexo' => false,
                'nascimento' => false,
                'nee' => false,
                'transporte' => false,
                'poder_publico' => false,
                'poder_publico_transporte' => false,
                'veiculo_transporte' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function normalizeTurmaAgg(array $agg): array
    {
        $base = $this->emptyTurmaAgg();
        $codes = is_array($agg['turma_codes'] ?? null) ? $agg['turma_codes'] : [];
        $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
        $profiles = is_array($agg['turma_profiles'] ?? null) ? $agg['turma_profiles'] : [];

        return [
            'total' => (int) ($agg['total'] ?? 0),
            'by_etapa_ensino' => is_array($agg['by_etapa_ensino'] ?? null) ? $agg['by_etapa_ensino'] : [],
            'by_etapa_agregada' => is_array($agg['by_etapa_agregada'] ?? null) ? $agg['by_etapa_agregada'] : [],
            'by_tipo_turma' => is_array($agg['by_tipo_turma'] ?? null) ? $agg['by_tipo_turma'] : [],
            'by_mediacao' => is_array($agg['by_mediacao'] ?? null) ? $agg['by_mediacao'] : [],
            'by_tipo_bucket' => $this->aggregator->mergeBuckets($base['by_tipo_bucket'], is_array($agg['by_tipo_bucket'] ?? null) ? $agg['by_tipo_bucket'] : []),
            'by_turno' => is_array($agg['by_turno'] ?? null) ? $agg['by_turno'] : [],
            'by_ch_band' => is_array($agg['by_ch_band'] ?? null) ? $agg['by_ch_band'] : [],
            'turma_codes' => array_values(array_unique(array_map('strval', $codes))),
            // Perfis só em leitura fresca do CSV (inferJornada); não persistir em parse_meta normalizado.
            'turma_profiles' => $profiles,
            'without_etapa' => (int) ($agg['without_etapa'] ?? 0),
            'without_tipo' => (int) ($agg['without_tipo'] ?? 0),
            'columns' => [
                'turno' => (bool) ($cols['turno'] ?? false),
                'carga_horaria' => (bool) ($cols['carga_horaria'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function normalizeAlunoAgg(array $agg): array
    {
        $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
        $age = is_array($agg['age_grade'] ?? null) ? $agg['age_grade'] : [];

        return [
            'total' => (int) ($agg['total'] ?? 0),
            'by_etapa_ensino' => is_array($agg['by_etapa_ensino'] ?? null) ? $agg['by_etapa_ensino'] : [],
            'without_etapa' => (int) ($agg['without_etapa'] ?? 0),
            'without_turma' => (int) ($agg['without_turma'] ?? 0),
            'by_turma' => is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [],
            'by_cor_raca' => is_array($agg['by_cor_raca'] ?? null) ? $agg['by_cor_raca'] : [],
            'by_sexo' => is_array($agg['by_sexo'] ?? null) ? $agg['by_sexo'] : [],
            'by_faixa_etaria' => is_array($agg['by_faixa_etaria'] ?? null) ? $agg['by_faixa_etaria'] : [],
            'by_nee' => is_array($agg['by_nee'] ?? null) ? $agg['by_nee'] : [],
            'nee_flagged' => (int) ($agg['nee_flagged'] ?? 0),
            'by_deficiency' => is_array($agg['by_deficiency'] ?? null) ? $agg['by_deficiency'] : [],
            'by_disorder' => is_array($agg['by_disorder'] ?? null) ? $agg['by_disorder'] : [],
            'by_ah' => is_array($agg['by_ah'] ?? null) ? $agg['by_ah'] : [],
            'deficiency_flagged' => (int) ($agg['deficiency_flagged'] ?? 0),
            'disorder_flagged' => (int) ($agg['disorder_flagged'] ?? 0),
            'ah_flagged' => (int) ($agg['ah_flagged'] ?? 0),
            'by_underreporting' => is_array($agg['by_underreporting'] ?? null) ? $agg['by_underreporting'] : [],
            'underreporting_flagged' => (int) ($agg['underreporting_flagged'] ?? 0),
            'by_transporte' => is_array($agg['by_transporte'] ?? null) ? $agg['by_transporte'] : [],
            'transporte_flagged' => (int) ($agg['transporte_flagged'] ?? 0),
            'without_transporte' => (int) ($agg['without_transporte'] ?? 0),
            'transporte_sem_poder' => (int) ($agg['transporte_sem_poder'] ?? 0),
            'by_poder_publico_transporte' => is_array($agg['by_poder_publico_transporte'] ?? null) ? $agg['by_poder_publico_transporte'] : [],
            'by_veiculo_transporte' => is_array($agg['by_veiculo_transporte'] ?? null) ? $agg['by_veiculo_transporte'] : [],
            'without_cor' => (int) ($agg['without_cor'] ?? 0),
            'without_sexo' => (int) ($agg['without_sexo'] ?? 0),
            'without_nascimento' => (int) ($agg['without_nascimento'] ?? 0),
            'age_grade' => [
                'eligible' => (int) ($age['eligible'] ?? 0),
                'distorcao' => (int) ($age['distorcao'] ?? 0),
                'atraso_1' => (int) ($age['atraso_1'] ?? 0),
                'adequado' => (int) ($age['adequado'] ?? 0),
                'adiantado' => (int) ($age['adiantado'] ?? 0),
                'indefinido' => (int) ($age['indefinido'] ?? 0),
                'excluido' => (int) ($age['excluido'] ?? 0),
                'by_etapa' => is_array($age['by_etapa'] ?? null) ? $age['by_etapa'] : [],
                'pct_distorcao' => $age['pct_distorcao'] ?? null,
            ],
            'columns' => [
                'cor_raca' => (bool) ($cols['cor_raca'] ?? false),
                'sexo' => (bool) ($cols['sexo'] ?? false),
                'nascimento' => (bool) ($cols['nascimento'] ?? false),
                'nee' => (bool) ($cols['nee'] ?? false),
                'transporte' => (bool) ($cols['transporte'] ?? false),
                'poder_publico' => (bool) ($cols['poder_publico'] ?? false),
                'poder_publico_transporte' => (bool) ($cols['poder_publico_transporte'] ?? false),
                'veiculo_transporte' => (bool) ($cols['veiculo_transporte'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $into
     * @param  array<string, mixed>  $from
     * @return array<string, mixed>
     */
    private function mergeTurmaAgg(array $into, array $from): array
    {
        $into['total'] = (int) ($into['total'] ?? 0) + (int) ($from['total'] ?? 0);
        $into['without_etapa'] = (int) ($into['without_etapa'] ?? 0) + (int) ($from['without_etapa'] ?? 0);
        $into['without_tipo'] = (int) ($into['without_tipo'] ?? 0) + (int) ($from['without_tipo'] ?? 0);
        $into['by_etapa_ensino'] = $this->aggregator->mergeCounts(
            is_array($into['by_etapa_ensino'] ?? null) ? $into['by_etapa_ensino'] : [],
            is_array($from['by_etapa_ensino'] ?? null) ? $from['by_etapa_ensino'] : [],
        );
        $into['by_etapa_agregada'] = $this->aggregator->mergeCounts(
            is_array($into['by_etapa_agregada'] ?? null) ? $into['by_etapa_agregada'] : [],
            is_array($from['by_etapa_agregada'] ?? null) ? $from['by_etapa_agregada'] : [],
        );
        $into['by_tipo_turma'] = $this->aggregator->mergeCounts(
            is_array($into['by_tipo_turma'] ?? null) ? $into['by_tipo_turma'] : [],
            is_array($from['by_tipo_turma'] ?? null) ? $from['by_tipo_turma'] : [],
        );
        $into['by_mediacao'] = $this->aggregator->mergeCounts(
            is_array($into['by_mediacao'] ?? null) ? $into['by_mediacao'] : [],
            is_array($from['by_mediacao'] ?? null) ? $from['by_mediacao'] : [],
        );
        $into['by_tipo_bucket'] = $this->aggregator->mergeBuckets(
            is_array($into['by_tipo_bucket'] ?? null) ? $into['by_tipo_bucket'] : $this->emptyTurmaAgg()['by_tipo_bucket'],
            is_array($from['by_tipo_bucket'] ?? null) ? $from['by_tipo_bucket'] : [],
        );
        $into['by_turno'] = $this->aggregator->mergeCounts(
            is_array($into['by_turno'] ?? null) ? $into['by_turno'] : [],
            is_array($from['by_turno'] ?? null) ? $from['by_turno'] : [],
        );
        $into['by_ch_band'] = $this->aggregator->mergeCounts(
            is_array($into['by_ch_band'] ?? null) ? $into['by_ch_band'] : [],
            is_array($from['by_ch_band'] ?? null) ? $from['by_ch_band'] : [],
        );
        $intoCols = is_array($into['columns'] ?? null) ? $into['columns'] : $this->emptyTurmaAgg()['columns'];
        $fromCols = is_array($from['columns'] ?? null) ? $from['columns'] : [];
        foreach (array_keys($intoCols) as $key) {
            if (! empty($fromCols[$key])) {
                $intoCols[$key] = true;
            }
        }
        $into['columns'] = $intoCols;
        $codes = array_unique(array_merge(
            is_array($into['turma_codes'] ?? null) ? $into['turma_codes'] : [],
            is_array($from['turma_codes'] ?? null) ? $from['turma_codes'] : [],
        ));
        $into['turma_codes'] = array_values($codes);
        // Não fundir turma_profiles no merge de rede (peso + PII-adjacent codes).
        $into['turma_profiles'] = [];

        return $into;
    }

    /**
     * @param  array<string, mixed>  $into
     * @param  array<string, mixed>  $from
     * @return array<string, mixed>
     */
    private function mergeAlunoAgg(array $into, array $from): array
    {
        $intoCols = is_array($into['columns'] ?? null) ? $into['columns'] : $this->emptyAlunoAgg()['columns'];
        $fromCols = is_array($from['columns'] ?? null) ? $from['columns'] : [];
        foreach (array_keys($intoCols) as $key) {
            if (! empty($fromCols[$key])) {
                $intoCols[$key] = true;
            }
        }

        $ageInto = is_array($into['age_grade'] ?? null) ? $into['age_grade'] : $this->emptyAlunoAgg()['age_grade'];
        $ageFrom = is_array($from['age_grade'] ?? null) ? $from['age_grade'] : [];
        foreach (['eligible', 'distorcao', 'atraso_1', 'adequado', 'adiantado', 'indefinido', 'excluido'] as $k) {
            $ageInto[$k] = (int) ($ageInto[$k] ?? 0) + (int) ($ageFrom[$k] ?? 0);
        }
        $byEtapa = is_array($ageInto['by_etapa'] ?? null) ? $ageInto['by_etapa'] : [];
        foreach (is_array($ageFrom['by_etapa'] ?? null) ? $ageFrom['by_etapa'] : [] as $etapa => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($byEtapa[$etapa])) {
                $byEtapa[$etapa] = [
                    'eligible' => 0,
                    'distorcao' => 0,
                    'atraso_1' => 0,
                    'adequado' => 0,
                    'adiantado' => 0,
                ];
            }
            foreach (['eligible', 'distorcao', 'atraso_1', 'adequado', 'adiantado'] as $k) {
                $byEtapa[$etapa][$k] = (int) ($byEtapa[$etapa][$k] ?? 0) + (int) ($row[$k] ?? 0);
            }
        }
        $ageInto['by_etapa'] = $byEtapa;
        $elig = (int) ($ageInto['eligible'] ?? 0);
        $ageInto['pct_distorcao'] = $elig > 0
            ? round(100 * ((int) ($ageInto['distorcao'] ?? 0)) / $elig, 1)
            : null;

        return [
            'total' => (int) ($into['total'] ?? 0) + (int) ($from['total'] ?? 0),
            'by_etapa_ensino' => $this->aggregator->mergeCounts(
                is_array($into['by_etapa_ensino'] ?? null) ? $into['by_etapa_ensino'] : [],
                is_array($from['by_etapa_ensino'] ?? null) ? $from['by_etapa_ensino'] : [],
            ),
            'without_etapa' => (int) ($into['without_etapa'] ?? 0) + (int) ($from['without_etapa'] ?? 0),
            'without_turma' => (int) ($into['without_turma'] ?? 0) + (int) ($from['without_turma'] ?? 0),
            'by_turma' => $this->aggregator->mergeCounts(
                is_array($into['by_turma'] ?? null) ? $into['by_turma'] : [],
                is_array($from['by_turma'] ?? null) ? $from['by_turma'] : [],
            ),
            'by_cor_raca' => $this->aggregator->mergeCounts(
                is_array($into['by_cor_raca'] ?? null) ? $into['by_cor_raca'] : [],
                is_array($from['by_cor_raca'] ?? null) ? $from['by_cor_raca'] : [],
            ),
            'by_sexo' => $this->aggregator->mergeCounts(
                is_array($into['by_sexo'] ?? null) ? $into['by_sexo'] : [],
                is_array($from['by_sexo'] ?? null) ? $from['by_sexo'] : [],
            ),
            'by_faixa_etaria' => $this->mergeAgeBands(
                is_array($into['by_faixa_etaria'] ?? null) ? $into['by_faixa_etaria'] : [],
                is_array($from['by_faixa_etaria'] ?? null) ? $from['by_faixa_etaria'] : [],
            ),
            'by_nee' => $this->aggregator->mergeCounts(
                is_array($into['by_nee'] ?? null) ? $into['by_nee'] : [],
                is_array($from['by_nee'] ?? null) ? $from['by_nee'] : [],
            ),
            'nee_flagged' => (int) ($into['nee_flagged'] ?? 0) + (int) ($from['nee_flagged'] ?? 0),
            'by_deficiency' => $this->aggregator->mergeCounts(
                is_array($into['by_deficiency'] ?? null) ? $into['by_deficiency'] : [],
                is_array($from['by_deficiency'] ?? null) ? $from['by_deficiency'] : [],
            ),
            'by_disorder' => $this->aggregator->mergeCounts(
                is_array($into['by_disorder'] ?? null) ? $into['by_disorder'] : [],
                is_array($from['by_disorder'] ?? null) ? $from['by_disorder'] : [],
            ),
            'by_ah' => $this->aggregator->mergeCounts(
                is_array($into['by_ah'] ?? null) ? $into['by_ah'] : [],
                is_array($from['by_ah'] ?? null) ? $from['by_ah'] : [],
            ),
            'deficiency_flagged' => (int) ($into['deficiency_flagged'] ?? 0) + (int) ($from['deficiency_flagged'] ?? 0),
            'disorder_flagged' => (int) ($into['disorder_flagged'] ?? 0) + (int) ($from['disorder_flagged'] ?? 0),
            'ah_flagged' => (int) ($into['ah_flagged'] ?? 0) + (int) ($from['ah_flagged'] ?? 0),
            'by_underreporting' => $this->aggregator->mergeCounts(
                is_array($into['by_underreporting'] ?? null) ? $into['by_underreporting'] : [],
                is_array($from['by_underreporting'] ?? null) ? $from['by_underreporting'] : [],
            ),
            'underreporting_flagged' => (int) ($into['underreporting_flagged'] ?? 0) + (int) ($from['underreporting_flagged'] ?? 0),
            'by_transporte' => $this->aggregator->mergeCounts(
                is_array($into['by_transporte'] ?? null) ? $into['by_transporte'] : [],
                is_array($from['by_transporte'] ?? null) ? $from['by_transporte'] : [],
            ),
            'transporte_flagged' => (int) ($into['transporte_flagged'] ?? 0) + (int) ($from['transporte_flagged'] ?? 0),
            'without_transporte' => (int) ($into['without_transporte'] ?? 0) + (int) ($from['without_transporte'] ?? 0),
            'transporte_sem_poder' => (int) ($into['transporte_sem_poder'] ?? 0) + (int) ($from['transporte_sem_poder'] ?? 0),
            'by_poder_publico_transporte' => $this->aggregator->mergeCounts(
                is_array($into['by_poder_publico_transporte'] ?? null) ? $into['by_poder_publico_transporte'] : [],
                is_array($from['by_poder_publico_transporte'] ?? null) ? $from['by_poder_publico_transporte'] : [],
            ),
            'by_veiculo_transporte' => $this->aggregator->mergeCounts(
                is_array($into['by_veiculo_transporte'] ?? null) ? $into['by_veiculo_transporte'] : [],
                is_array($from['by_veiculo_transporte'] ?? null) ? $from['by_veiculo_transporte'] : [],
            ),
            'without_cor' => (int) ($into['without_cor'] ?? 0) + (int) ($from['without_cor'] ?? 0),
            'without_sexo' => (int) ($into['without_sexo'] ?? 0) + (int) ($from['without_sexo'] ?? 0),
            'without_nascimento' => (int) ($into['without_nascimento'] ?? 0) + (int) ($from['without_nascimento'] ?? 0),
            'age_grade' => $ageInto,
            'columns' => $intoCols,
        ];
    }

    private function schoolsHaveNumericMeta(ClioCampaign $campaign, string $key): bool
    {
        foreach ($campaign->schools as $school) {
            $meta = is_array($school->meta) ? $school->meta : [];
            if (is_numeric($meta[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
