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
            $this->inferCoerencia($campaign);
            $this->inferDuplicidades($campaign);
            $this->inferDelta($campaign);
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
                    __('Escola bloqueada na coleta.'),
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
            __('Coleta: :a em andamento, :n não iniciou, :f fechada, :b bloqueada.', [
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
            __('Rede: :a ativas, :e extintas.', ['a' => $ativas, 'e' => $extintas]),
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
                    __('Matrículas sem Código da turma: :n.', ['n' => $alunoAgg['without_turma']]),
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
                __('Matrículas sem Etapa de ensino preenchida: :n (pirâmide por ano incompleta).', [
                    'n' => $withoutEtapa,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-MAT',
            __('Matrícula: curricular :c · AEE :aee · AC :ac · linhas Relação :r.', [
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
                    __('Acomp indica matrícula curricular (:n), mas não há turma do tipo Curricular na Relação.', [
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
                    __('Acomp declara :n matrícula(s) AEE, sem turma AEE na Relação.', [
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
                __('Turmas sem Etapa de ensino: :n (indicador por ano incompleto).', [
                    'n' => $withoutEtapa,
                ]),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-TUR',
            __('Turmas: :n · curricular :c · AEE :aee · AC :ac.', [
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
        $rows = $campaign->artifacts
            ->where('kind', 'relacao_profissional_escola')
            ->sum(fn (ClioCampaignArtifact $a) => (int) ($a->row_count ?? 0));

        $this->upsertInference(
            $campaign,
            'INF-DOC',
            __('Profissionais/vínculos (linhas): :n.', ['n' => $rows]),
            ['relacao_profissional_rows' => $rows],
        );
    }

    private function inferNee(ClioCampaign $campaign): void
    {
        $disk = (string) config('clio.disk', 'local');
        $flagged = 0;
        $scanned = 0;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            try {
                $data = $this->csv->read(Storage::disk($disk)->path($artifact->storage_path), 1);
            } catch (Throwable) {
                continue;
            }

            foreach ($data['rows'] as $row) {
                $scanned++;
                $blob = mb_strtolower(implode(' ', array_values($row)));
                if (
                    str_contains($blob, 'sim') && (
                        array_key_exists('Deficiência', $row)
                        || array_key_exists('Transtorno do espectro autista', $row)
                        || array_key_exists('Altas habilidades', $row)
                        || preg_match('/defici|autis|tea|altas habilidades/i', implode(' ', array_keys($row))) === 1
                    )
                ) {
                    // Contar se alguma coluna NEE típica tem valor não vazio / sim
                    foreach ($row as $key => $value) {
                        if ($value === '' || in_array(mb_strtolower($value), ['não', 'nao', 'n', '0'], true)) {
                            continue;
                        }
                        if (preg_match('/defici|autis|tea|altas\s*habil|nee|aee/i', (string) $key) === 1) {
                            $flagged++;
                            break;
                        }
                    }
                }
            }
        }

        $this->upsertInference(
            $campaign,
            'INF-NEE',
            __('Alunos com marcadores NEE/TEA/AH (heurística): :n de :s.', [
                'n' => $flagged,
                's' => $scanned,
            ]),
            ['flagged' => $flagged, 'scanned' => $scanned],
        );
    }

    private function inferCoerencia(ClioCampaign $campaign): void
    {
        $missingTriade = 0;
        $coverage = $this->parseService->coverage($campaign);

        foreach ($coverage['schools'] as $row) {
            if (! ($row['triade'] ?? false)) {
                $missingTriade++;
                $school = $campaign->schools->firstWhere('inep_code', $row['inep']);
                $this->addFinding(
                    $campaign,
                    'CLIO-COE-TRIADE',
                    ClioCampaignFinding::SEVERITY_WARNING,
                    __('Tríade incompleta (aluno/turma/profissional).'),
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
                __('Coleta sem relatório municipal de acompanhamento.'),
            );
        }

        $this->upsertInference(
            $campaign,
            'INF-COE',
            __('Coerência: :m escola(s) sem tríade completa · cobertura :p%.', [
                'm' => $missingTriade,
                'p' => $coverage['triade_coverage_pct'],
            ]),
            [
                'schools_missing_triade' => $missingTriade,
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
                            __('Identificação duplicada na rede (amostra: :id).', [
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
            __('Duplicidades de identificação: :n.', ['n' => $dupes]),
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
                        __('Delta matrícula Acomp curricular (:a) × Relação (:r).', [
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
                    __('Acomp declara :n matrícula(s) em Atividade Complementar, sem turma AC na Relação.', [
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
            __('Escolas com delta curricular: :n · AEE sem turma: :aee · AC sem turma: :ac.', [
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
    private function resolveAlunoAggregates(ClioCampaignArtifact $artifact): array
    {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['by_etapa_ensino'])) {
            return $this->normalizeAlunoAgg($agg);
        }

        $disk = (string) config('clio.disk', 'local');
        try {
            $data = $this->csv->read(Storage::disk($disk)->path($artifact->storage_path), 1);
        } catch (Throwable) {
            return $this->emptyAlunoAgg();
        }

        return $this->aggregator->aggregateAlunos($data['rows'], $this->csv);
    }

    /**
     * @return array{
     *   total: int,
     *   by_etapa_ensino: array<string, int>,
     *   by_etapa_agregada: array<string, int>,
     *   by_tipo_turma: array<string, int>,
     *   by_mediacao: array<string, int>,
     *   by_tipo_bucket: array{curricular: int, aee: int, atividade_complementar: int, outra: int},
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
            'without_etapa' => 0,
            'without_tipo' => 0,
        ];
    }

    /**
     * @return array{total: int, by_etapa_ensino: array<string, int>, without_etapa: int, without_turma: int}
     */
    private function emptyAlunoAgg(): array
    {
        return [
            'total' => 0,
            'by_etapa_ensino' => [],
            'without_etapa' => 0,
            'without_turma' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function normalizeTurmaAgg(array $agg): array
    {
        $base = $this->emptyTurmaAgg();

        return [
            'total' => (int) ($agg['total'] ?? 0),
            'by_etapa_ensino' => is_array($agg['by_etapa_ensino'] ?? null) ? $agg['by_etapa_ensino'] : [],
            'by_etapa_agregada' => is_array($agg['by_etapa_agregada'] ?? null) ? $agg['by_etapa_agregada'] : [],
            'by_tipo_turma' => is_array($agg['by_tipo_turma'] ?? null) ? $agg['by_tipo_turma'] : [],
            'by_mediacao' => is_array($agg['by_mediacao'] ?? null) ? $agg['by_mediacao'] : [],
            'by_tipo_bucket' => $this->aggregator->mergeBuckets($base['by_tipo_bucket'], is_array($agg['by_tipo_bucket'] ?? null) ? $agg['by_tipo_bucket'] : []),
            'without_etapa' => (int) ($agg['without_etapa'] ?? 0),
            'without_tipo' => (int) ($agg['without_tipo'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array{total: int, by_etapa_ensino: array<string, int>, without_etapa: int, without_turma: int}
     */
    private function normalizeAlunoAgg(array $agg): array
    {
        return [
            'total' => (int) ($agg['total'] ?? 0),
            'by_etapa_ensino' => is_array($agg['by_etapa_ensino'] ?? null) ? $agg['by_etapa_ensino'] : [],
            'without_etapa' => (int) ($agg['without_etapa'] ?? 0),
            'without_turma' => (int) ($agg['without_turma'] ?? 0),
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

        return $into;
    }

    /**
     * @param  array{total: int, by_etapa_ensino: array<string, int>, without_etapa: int, without_turma: int}  $into
     * @param  array{total: int, by_etapa_ensino: array<string, int>, without_etapa: int, without_turma: int}  $from
     * @return array{total: int, by_etapa_ensino: array<string, int>, without_etapa: int, without_turma: int}
     */
    private function mergeAlunoAgg(array $into, array $from): array
    {
        return [
            'total' => $into['total'] + $from['total'],
            'by_etapa_ensino' => $this->aggregator->mergeCounts($into['by_etapa_ensino'], $from['by_etapa_ensino']),
            'without_etapa' => $into['without_etapa'] + $from['without_etapa'],
            'without_turma' => $into['without_turma'] + $from['without_turma'],
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
