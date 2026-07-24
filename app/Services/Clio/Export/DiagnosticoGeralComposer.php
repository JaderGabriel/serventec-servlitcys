<?php

namespace App\Services\Clio\Export;

use App\Models\Bi\BiClioInclusion;
use App\Models\Bi\BiClioSchool;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\RelationCsvAggregator;

/**
 * Quadro Diagnóstico Geral: escolas ativas × erros/avisos gerenciais
 * (Cor/Raça, enturmação, distorção, NEE, tríade e demais indicadores).
 */
final class DiagnosticoGeralComposer
{
    public function __construct(
        private readonly RelationCsvAggregator $aggregator = new RelationCsvAggregator,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     rows: list<array{
     *         inep: string,
     *         name: string,
     *         location: string,
     *         location_tone: string,
     *         alerts: list<array{severity: string, code: string, message: string, icon: string}>,
     *         error_count: int,
     *         warning_count: int,
     *         status: string,
     *         status_tone: string
     *     }>,
     *     totals: array{
     *         schools: int,
     *         with_alerts: int,
     *         ok: int,
     *         without_data: int,
     *         errors: int,
     *         warnings: int
     *     },
     *     cor_raca_undeclared: array{
     *         total: int,
     *         schools: list<array{inep: string, name: string, count: int}>
     *     },
     *     network_notices: list<array{severity: string, code: string, message: string}>
     * }
     */
    public function compose(ClioCampaign $campaign): array
    {
        $campaign->loadMissing(['schools.artifacts', 'findings']);
        if (! $campaign->relationLoaded('inferences')) {
            try {
                $campaign->load('inferences');
            } catch (\Throwable) {
                $campaign->setRelation('inferences', collect());
            }
        }

        $findingsBySchool = $campaign->findings
            ->filter(static function (ClioCampaignFinding $f): bool {
                return in_array($f->severity, [
                    ClioCampaignFinding::SEVERITY_ERROR,
                    ClioCampaignFinding::SEVERITY_WARNING,
                ], true);
            })
            ->groupBy(static fn (ClioCampaignFinding $f): int => (int) ($f->school_id ?? 0));

        $biByInep = collect();
        $inclusionByInep = collect();
        try {
            $biByInep = BiClioSchool::query()
                ->where('campaign_id', $campaign->id)
                ->get()
                ->keyBy(static fn (BiClioSchool $s): string => (string) $s->inep);

            $inclusionByInep = BiClioInclusion::query()
                ->where('campaign_id', $campaign->id)
                ->get()
                ->keyBy(static fn (BiClioInclusion $r): string => (string) $r->inep);
        } catch (\Throwable) {
            // Ambiente sem tabelas BI (testes unitários sem RefreshDatabase).
        }

        $rows = [];
        $totals = [
            'schools' => 0,
            'with_alerts' => 0,
            'ok' => 0,
            'without_data' => 0,
            'errors' => 0,
            'warnings' => 0,
        ];
        $corSchools = [];
        $corTotal = 0;

        $schools = $campaign->schools
            ->sortBy(static fn (ClioCampaignSchool $s): string => mb_strtolower((string) $s->name))
            ->values();

        foreach ($schools as $school) {
            if (! $school instanceof ClioCampaignSchool) {
                continue;
            }
            if (CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status)) {
                continue;
            }

            $totals['schools']++;
            $location = $this->normalizeLocation($this->rawLocation($school));
            $alerts = [];
            $inep = (string) $school->inep_code;

            $schoolFindings = $findingsBySchool->get((int) $school->id, collect());
            foreach ($schoolFindings as $finding) {
                if (! $finding instanceof ClioCampaignFinding) {
                    continue;
                }
                $sev = (string) $finding->severity;
                $alerts[] = [
                    'severity' => $sev,
                    'code' => (string) $finding->code,
                    'message' => (string) $finding->message,
                    'icon' => $sev === ClioCampaignFinding::SEVERITY_ERROR ? 'error' : 'warning',
                ];
            }

            $agg = $this->alunoAggregates($school);

            $withoutCor = $this->withoutCorCount($school, $agg);
            if ($withoutCor !== null && $withoutCor > 0) {
                $corTotal += $withoutCor;
                $corSchools[] = [
                    'inep' => $inep,
                    'name' => (string) $school->name,
                    'count' => $withoutCor,
                ];
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-DEM-COR-ESCOLA',
                    'message' => __(':n aluno(s) sem declaração de Cor/Raça', ['n' => $withoutCor]),
                    'icon' => 'warning',
                ];
            }

            $withoutTurma = (int) ($agg['without_turma'] ?? 0);
            if ($withoutTurma > 0 && ! $this->hasAlertCode($alerts, 'CLIO-MAT-SEM-TURMA')) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-MAT-SEM-TURMA',
                    'message' => __(':n matrícula(s) sem enturmação (Código da turma)', ['n' => $withoutTurma]),
                    'icon' => 'warning',
                ];
            }

            $age = is_array($agg['age_grade'] ?? null) ? $agg['age_grade'] : [];
            $distN = (int) ($age['distorcao'] ?? 0);
            $eligible = (int) ($age['eligible'] ?? 0);
            $distPct = $age['pct_distorcao'] ?? null;
            if ($distPct === null && $eligible > 0) {
                $distPct = round(100 * $distN / $eligible, 1);
            }
            if ($eligible > 0 && $distN > 0 && is_numeric($distPct) && (float) $distPct >= 15) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-DIS-ESCOLA',
                    'message' => __('Distorção idade-série estimada em :p% (:n de :e no escopo EF/EM)', [
                        'p' => number_format((float) $distPct, 1, ',', '.'),
                        'n' => $distN,
                        'e' => $eligible,
                    ]),
                    'icon' => 'warning',
                ];
            }

            $inclusion = $inclusionByInep->get($inep);
            if ($inclusion instanceof BiClioInclusion) {
                $nee = (int) $inclusion->qt_nee_people;
                $semAee = (int) $inclusion->qt_without_aee;
                $aeeSemNee = (int) $inclusion->qt_aee_without_nee;
                if ($semAee > 0) {
                    $alerts[] = [
                        'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                        'code' => 'CLIO-NEE-SEM-AEE',
                        'message' => __(':n pessoa(s) com NEE/TEA/AH sem matrícula AEE', ['n' => $semAee]),
                        'icon' => 'warning',
                    ];
                }
                if ($aeeSemNee > 0) {
                    $alerts[] = [
                        'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                        'code' => 'CLIO-AEE-SEM-NEE',
                        'message' => __(':n pessoa(s) em AEE sem tipificação NEE/TEA/AH', ['n' => $aeeSemNee]),
                        'icon' => 'warning',
                    ];
                }
                if ($nee > 0 && $semAee === 0 && $aeeSemNee === 0) {
                    $alerts[] = [
                        'severity' => 'info',
                        'code' => 'CLIO-NEE-OK',
                        'message' => __(':n pessoa(s) com NEE/TEA/AH tipificada(s)', ['n' => $nee]),
                        'icon' => 'info',
                    ];
                }
            }

            $biSchool = $biByInep->get($inep);
            if ($biSchool instanceof BiClioSchool && $biSchool->is_active) {
                $parts = (int) ($biSchool->triade_parts ?? 0);
                if ($parts < 3) {
                    $alerts[] = [
                        'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                        'code' => 'CLIO-TRIAD-INCOMPLETA',
                        'message' => __('Tríade incompleta (:p/3 arquivos)', ['p' => $parts]),
                        'icon' => 'warning',
                    ];
                }
                $delta = $biSchool->delta_curricular;
                if ($delta !== null && (int) $delta !== 0) {
                    $alerts[] = [
                        'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                        'code' => 'CLIO-DELTA-ESCOLA',
                        'message' => __('Diferença Acomp × Relação de alunos: :d', [
                            'd' => ((int) $delta > 0 ? '+' : '').(int) $delta,
                        ]),
                        'icon' => 'warning',
                    ];
                }
            }

            $hasArtifacts = $school->artifacts->isNotEmpty();
            if (! $hasArtifacts) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-SEM-LANCAMENTO',
                    'message' => __('Não há lançamento de informações'),
                    'icon' => 'warning',
                ];
                $totals['without_data']++;
            }

            $alerts = $this->dedupeAlerts($alerts);
            $errorCount = count(array_filter($alerts, static fn (array $a): bool => $a['severity'] === ClioCampaignFinding::SEVERITY_ERROR));
            $warningCount = count(array_filter($alerts, static fn (array $a): bool => $a['severity'] === ClioCampaignFinding::SEVERITY_WARNING));
            $totals['errors'] += $errorCount;
            $totals['warnings'] += $warningCount;

            if ($errorCount + $warningCount > 0) {
                $totals['with_alerts']++;
                $status = $errorCount > 0 ? 'error' : 'warning';
                $statusTone = $errorCount > 0 ? 'rose' : 'amber';
            } else {
                $totals['ok']++;
                $status = 'ok';
                $statusTone = 'emerald';
                if ($alerts === []) {
                    $alerts = [[
                        'severity' => 'ok',
                        'code' => 'OK',
                        'message' => __('Não há alertas/pendências a serem destacadas'),
                        'icon' => 'ok',
                    ]];
                }
            }

            $rows[] = [
                'inep' => $inep,
                'name' => (string) $school->name,
                'location' => $location,
                'location_tone' => $location === __('Rural') ? 'amber' : ($location === __('Urbana') ? 'sky' : 'slate'),
                'alerts' => $alerts,
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'status' => $status,
                'status_tone' => $statusTone,
            ];
        }

        usort($corSchools, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'available' => $rows !== [],
            'rows' => $rows,
            'totals' => $totals,
            'cor_raca_undeclared' => [
                'total' => $corTotal,
                'schools' => $corSchools,
            ],
            'network_notices' => $this->networkNotices($campaign, $corTotal, $totals['schools']),
        ];
    }

    /**
     * @return list<array{severity: string, code: string, message: string}>
     */
    private function networkNotices(ClioCampaign $campaign, int $corTotal, int $schoolsActive): array
    {
        $notices = [];

        if ($corTotal > 0) {
            $notices[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-DEM-COR-REDE',
                'message' => __('Cor/Raça não declarada: :n aluno(s) na rede (:s escola(s) em atividade). Complete no Educacenso para o indicador demográfico ficar confiável.', [
                    'n' => number_format($corTotal, 0, ',', '.'),
                    's' => $schoolsActive,
                ]),
            ];
        }

        foreach ($campaign->findings as $finding) {
            if (! $finding instanceof ClioCampaignFinding) {
                continue;
            }
            if ($finding->school_id !== null) {
                continue;
            }
            if (! in_array($finding->severity, [
                ClioCampaignFinding::SEVERITY_ERROR,
                ClioCampaignFinding::SEVERITY_WARNING,
            ], true)) {
                continue;
            }
            if (in_array((string) $finding->code, [
                'CLIO-DIS-ALTA',
                'CLIO-DEM-COR-VAZIO',
                'CLIO-NEE-SUB',
                'CLIO-DEN-TURMA-CHEIA',
                'CLIO-XCHK-ETAPA',
            ], true)) {
                $notices[] = [
                    'severity' => (string) $finding->severity,
                    'code' => (string) $finding->code,
                    'message' => (string) $finding->message,
                ];
            }
        }

        return $notices;
    }

    /**
     * @return array<string, mixed>
     */
    private function alunoAggregates(ClioCampaignSchool $school): array
    {
        $artifact = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
        if ($artifact === null) {
            return [];
        }
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];

        return is_array($meta['aggregates'] ?? null) ? $meta['aggregates'] : [];
    }

    /**
     * @param  array<string, mixed>  $agg
     */
    private function withoutCorCount(ClioCampaignSchool $school, array $agg = []): ?int
    {
        if ($agg === []) {
            $agg = $this->alunoAggregates($school);
        }
        if ($agg === []) {
            return null;
        }
        $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
        if (empty($cols['cor_raca'])) {
            return null;
        }

        return $this->aggregator->undeclaredCorCountFromAggregates($agg);
    }

    private function rawLocation(ClioCampaignSchool $school): string
    {
        $meta = is_array($school->meta) ? $school->meta : [];

        foreach (['location', 'localizacao', 'Localização', 'Localizacao'] as $key) {
            $v = trim((string) ($meta[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    private function normalizeLocation(string $raw): string
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
     * @param  list<array{severity: string, code: string, message: string, icon: string}>  $alerts
     */
    private function hasAlertCode(array $alerts, string $code): bool
    {
        foreach ($alerts as $alert) {
            if (($alert['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string}>  $alerts
     * @return list<array{severity: string, code: string, message: string, icon: string}>
     */
    private function dedupeAlerts(array $alerts): array
    {
        $seen = [];
        $out = [];
        foreach ($alerts as $alert) {
            $key = $alert['code'].'|'.$alert['message'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $alert;
        }

        usort($out, static function (array $a, array $b): int {
            $rank = [
                ClioCampaignFinding::SEVERITY_ERROR => 0,
                ClioCampaignFinding::SEVERITY_WARNING => 1,
                'info' => 2,
                'ok' => 3,
            ];

            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });

        return $out;
    }
}
