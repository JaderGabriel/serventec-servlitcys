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

    public function __construct(
        private readonly CampaignParseService $parseService,
        ?CsvReader $csv = null,
    ) {
        $this->csv = $csv ?? new CsvReader;
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
        $fromRelacao = 0;

        foreach ($campaign->schools as $school) {
            $curr = $school->meta['total_curricular'] ?? null;
            if (is_numeric($curr)) {
                $fromAcomp += (int) $curr;
            }
            $aluno = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            if ($aluno !== null) {
                $fromRelacao += (int) ($aluno->row_count ?? 0);
            }
        }

        $municipalAluno = $campaign->artifacts
            ->where('kind', 'relacao_aluno_escola')
            ->sum(fn (ClioCampaignArtifact $a) => (int) ($a->row_count ?? 0));

        $this->upsertInference(
            $campaign,
            'INF-MAT',
            __('Matrícula: Acomp curricular :a · linhas Relação aluno :r.', [
                'a' => $fromAcomp,
                'r' => $fromRelacao > 0 ? $fromRelacao : $municipalAluno,
            ]),
            [
                'acomp_curricular_sum' => $fromAcomp,
                'relacao_aluno_rows' => $fromRelacao > 0 ? $fromRelacao : $municipalAluno,
            ],
        );
    }

    private function inferTurmas(ClioCampaign $campaign): void
    {
        $rows = $campaign->artifacts
            ->where('kind', 'relacao_turma_escola')
            ->sum(fn (ClioCampaignArtifact $a) => (int) ($a->row_count ?? 0));

        $this->upsertInference(
            $campaign,
            'INF-TUR',
            __('Turmas declaradas (linhas): :n.', ['n' => $rows]),
            ['relacao_turma_rows' => $rows],
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
                __('Campanha sem relatório municipal de acompanhamento.'),
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

        foreach ($campaign->schools as $school) {
            $acomp = $school->meta['total_curricular'] ?? null;
            if (! is_numeric($acomp)) {
                continue;
            }
            $aluno = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            if ($aluno === null) {
                continue;
            }
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
                    __('Delta matrícula Acomp (:a) × Relação (:r).', [
                        'a' => (int) $acomp,
                        'r' => $rows,
                    ]),
                    $school,
                    $aluno,
                    ['diff' => $diff],
                );
            }
        }

        $this->upsertInference(
            $campaign,
            'INF-DELTA',
            __('Escolas com delta Acomp×Relação: :n.', ['n' => $divergent]),
            ['divergent_schools' => $divergent, 'samples' => array_slice($deltas, 0, 20)],
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
}
