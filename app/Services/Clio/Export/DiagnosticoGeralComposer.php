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
            if (! CampaignAnalysisPresenter::isOperationallyEligible(
                $school->functioning_status,
                $school->dependency,
                is_array($school->meta) ? $school->meta : [],
            )) {
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
                    'theme' => $this->themeForFindingCode((string) $finding->code),
                ];
            }

            $agg = $this->alunoAggregates($school);
            $turmaAgg = $this->turmaAggregates($school);
            $profAgg = $this->profissionalAggregates($school);

            $this->appendMatriculasAlerts($alerts, $agg, $turmaAgg);
            $this->appendDemografiaAlerts($alerts, $school, $agg, $corTotal, $corSchools, $inep);
            $this->appendDistorcaoAlerts($alerts, $agg);
            $this->appendTransporteAlerts($alerts, $agg, $location);
            $this->appendDensidadeAlerts($alerts, $agg, $turmaAgg);
            $this->appendProfissionaisAlerts($alerts, $profAgg, $turmaAgg);
            $this->appendJornadaAlerts($alerts, $turmaAgg);
            $this->appendInclusaoAlerts($alerts, $inclusionByInep->get($inep));
            $this->appendTriadeEDeltaAlerts($alerts, $biByInep->get($inep));

            $hasArtifacts = $school->artifacts->isNotEmpty();
            if (! $hasArtifacts) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-SEM-LANCAMENTO',
                    'message' => __('Não há lançamento de informações'),
                    'icon' => 'warning',
                    'theme' => 'matriculas',
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
                'CLIO-NEE-SEM-AEE',
                'CLIO-AEE-SEM-NEE',
                'CLIO-DEN-TURMA-CHEIA',
                'CLIO-XCHK-ETAPA',
                'CLIO-TRA-RURAL',
                'CLIO-TRA-SEM-PODER',
                'CLIO-DELTA-REDE',
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
        return $this->artifactAggregates($school, 'relacao_aluno_escola');
    }

    /**
     * @return array<string, mixed>
     */
    private function turmaAggregates(ClioCampaignSchool $school): array
    {
        return $this->artifactAggregates($school, 'relacao_turma_escola');
    }

    /**
     * @return array<string, mixed>
     */
    private function profissionalAggregates(ClioCampaignSchool $school): array
    {
        return $this->artifactAggregates($school, 'relacao_profissional_escola');
    }

    /**
     * @return array<string, mixed>
     */
    private function artifactAggregates(ClioCampaignSchool $school, string $kind): array
    {
        $artifact = $school->artifacts->firstWhere('kind', $kind);
        if ($artifact === null) {
            return [];
        }
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];

        return is_array($meta['aggregates'] ?? null) ? $meta['aggregates'] : [];
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $agg
     * @param  array<string, mixed>  $turmaAgg
     */
    private function appendMatriculasAlerts(array &$alerts, array $agg, array $turmaAgg): void
    {
        $withoutTurma = (int) ($agg['without_turma'] ?? 0);
        if ($withoutTurma > 0 && ! $this->hasAlertCode($alerts, 'CLIO-MAT-SEM-TURMA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-MAT-SEM-TURMA',
                'message' => __(':n matrícula(s) sem enturmação (Código da turma)', ['n' => $withoutTurma]),
                'icon' => 'warning',
                'theme' => 'matriculas',
            ];
        }

        $withoutEtapa = (int) ($agg['without_etapa'] ?? 0);
        if ($withoutEtapa > 0 && ! $this->hasAlertCode($alerts, 'CLIO-MAT-SEM-ETAPA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-MAT-SEM-ETAPA',
                'message' => __(':n matrícula(s) sem etapa de ensino', ['n' => $withoutEtapa]),
                'icon' => 'warning',
                'theme' => 'matriculas',
            ];
        }

        $turmaWithoutEtapa = (int) ($turmaAgg['without_etapa'] ?? 0);
        if ($turmaWithoutEtapa > 0 && ! $this->hasAlertCode($alerts, 'CLIO-TUR-SEM-ETAPA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-TUR-SEM-ETAPA',
                'message' => __(':n turma(s) sem etapa de ensino', ['n' => $turmaWithoutEtapa]),
                'icon' => 'warning',
                'theme' => 'matriculas',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $agg
     * @param  list<array{inep: string, name: string, count: int}>  $corSchools
     */
    private function appendDemografiaAlerts(
        array &$alerts,
        ClioCampaignSchool $school,
        array $agg,
        int &$corTotal,
        array &$corSchools,
        string $inep,
    ): void {
        $withoutCor = $this->withoutCorCount($school, $agg);
        if ($withoutCor !== null && $withoutCor > 0) {
            $corTotal += $withoutCor;
            $corSchools[] = [
                'inep' => $inep,
                'name' => (string) $school->name,
                'count' => $withoutCor,
            ];
            if (! $this->hasAlertCode($alerts, 'CLIO-DEM-COR-ESCOLA')) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-DEM-COR-ESCOLA',
                    'message' => __(':n aluno(s) sem declaração de Cor/Raça', ['n' => $withoutCor]),
                    'icon' => 'warning',
                    'theme' => 'demografia',
                ];
            }
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $agg
     */
    private function appendDistorcaoAlerts(array &$alerts, array $agg): void
    {
        $age = is_array($agg['age_grade'] ?? null) ? $agg['age_grade'] : [];
        $distN = (int) ($age['distorcao'] ?? 0);
        $atraso1 = (int) ($age['atraso_1'] ?? 0);
        $adiantado = (int) ($age['adiantado'] ?? 0);
        $eligible = (int) ($age['eligible'] ?? 0);
        $distPct = $age['pct_distorcao'] ?? null;
        if ($distPct === null && $eligible > 0) {
            $distPct = round(100 * $distN / $eligible, 1);
        }

        if ($eligible > 0 && $distN > 0 && ! $this->hasAlertCode($alerts, 'CLIO-DIS-ESCOLA')) {
            $severity = (is_numeric($distPct) && (float) $distPct >= 15)
                ? ClioCampaignFinding::SEVERITY_WARNING
                : 'info';
            $alerts[] = [
                'severity' => $severity,
                'code' => 'CLIO-DIS-ESCOLA',
                'message' => __('Distorção idade-série: :p% (:n/:e) · atraso 1 ano :a1 · adiantados :ad', [
                    'p' => is_numeric($distPct) ? number_format((float) $distPct, 1, ',', '.') : '—',
                    'n' => $distN,
                    'e' => $eligible,
                    'a1' => $atraso1,
                    'ad' => $adiantado,
                ]),
                'icon' => $severity === ClioCampaignFinding::SEVERITY_WARNING ? 'warning' : 'info',
                'theme' => 'distorcao',
            ];
        } elseif ($eligible > 0 && ($atraso1 > 0 || $adiantado > 0) && ! $this->hasAlertCode($alerts, 'CLIO-DIS-ESCOLA')) {
            $alerts[] = [
                'severity' => 'info',
                'code' => 'CLIO-DIS-ESCOLA',
                'message' => __('Fluxo idade-série: atraso 1 ano :a1 · adiantados :ad (sem distorção ≥2 anos)', [
                    'a1' => $atraso1,
                    'ad' => $adiantado,
                ]),
                'icon' => 'info',
                'theme' => 'distorcao',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $agg
     */
    private function appendTransporteAlerts(array &$alerts, array $agg, string $location): void
    {
        $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
        if (empty($cols['transporte']) && empty($cols['poder_publico_transporte'])) {
            return;
        }

        $semPoder = (int) ($agg['transporte_sem_poder'] ?? 0);
        if ($semPoder > 0 && ! $this->hasAlertCode($alerts, 'CLIO-TRA-SEM-PODER')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-TRA-SEM-PODER',
                'message' => __(':n aluno(s) com transporte sem poder público informado', ['n' => $semPoder]),
                'icon' => 'warning',
                'theme' => 'transporte',
            ];
        }

        $flagged = (int) ($agg['transporte_flagged'] ?? 0);
        $scanned = (int) ($agg['total'] ?? 0);
        if ($location === __('Rural') && $flagged > 0 && $scanned > 0) {
            $pct = round(100 * $flagged / max(1, $scanned), 1);
            if ($pct >= 40 && ! $this->hasAlertCode($alerts, 'CLIO-TRA-RURAL')) {
                $alerts[] = [
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'code' => 'CLIO-TRA-RURAL',
                    'message' => __('Escola rural: :p% (:n) usam transporte escolar', [
                        'p' => number_format($pct, 1, ',', '.'),
                        'n' => $flagged,
                    ]),
                    'icon' => 'warning',
                    'theme' => 'transporte',
                ];
            }
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $agg
     * @param  array<string, mixed>  $turmaAgg
     */
    private function appendDensidadeAlerts(array &$alerts, array $agg, array $turmaAgg): void
    {
        $byTurmaAluno = is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [];
        $profiles = is_array($turmaAgg['turma_profiles'] ?? null) ? $turmaAgg['turma_profiles'] : [];
        $curricular = [];
        foreach ($profiles as $code => $profile) {
            $bucket = is_array($profile) ? (string) ($profile['bucket'] ?? '') : '';
            if ($bucket === RelationCsvAggregator::BUCKET_CURRICULAR || $bucket === 'curricular') {
                $curricular[(string) $code] = true;
            }
        }
        if ($curricular === [] && is_array($turmaAgg['turma_codes'] ?? null)) {
            foreach ($turmaAgg['turma_codes'] as $code) {
                $curricular[(string) $code] = true;
            }
        }
        if ($curricular === []) {
            return;
        }

        $cheias = 0;
        $vazias = 0;
        $max = 0;
        foreach ($curricular as $code => $_) {
            $n = (int) ($byTurmaAluno[$code] ?? 0);
            if ($n === 0) {
                $vazias++;
            }
            if ($n > $max) {
                $max = $n;
            }
            if ($n >= 40) {
                $cheias++;
            }
        }

        if ($cheias > 0 && ! $this->hasAlertCode($alerts, 'CLIO-DEN-TURMA-CHEIA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-DEN-TURMA-CHEIA',
                'message' => __(':n turma(s) curricular(es) com ≥40 alunos (máx. :m)', [
                    'n' => $cheias,
                    'm' => $max,
                ]),
                'icon' => 'warning',
                'theme' => 'densidade',
            ];
        }
        if ($vazias > 0 && ! $this->hasAlertCode($alerts, 'CLIO-DEN-TURMA-VAZIA')) {
            $alerts[] = [
                'severity' => 'info',
                'code' => 'CLIO-DEN-TURMA-VAZIA',
                'message' => __(':n turma(s) curricular(es) sem aluno vinculado', ['n' => $vazias]),
                'icon' => 'info',
                'theme' => 'densidade',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $profAgg
     * @param  array<string, mixed>  $turmaAgg
     */
    private function appendProfissionaisAlerts(array &$alerts, array $profAgg, array $turmaAgg): void
    {
        if ($profAgg === []) {
            return;
        }
        $withoutTurma = (int) ($profAgg['without_turma'] ?? 0);
        if ($withoutTurma > 0 && ! $this->hasAlertCode($alerts, 'CLIO-DOC-SEM-VINCULO')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-DOC-SEM-VINCULO',
                'message' => __(':n vínculo(s) profissional(is) sem código de turma', ['n' => $withoutTurma]),
                'icon' => 'warning',
                'theme' => 'densidade',
            ];
        }

        $byTurmaProf = is_array($profAgg['by_turma'] ?? null) ? $profAgg['by_turma'] : [];
        $turmaCodes = is_array($turmaAgg['turma_codes'] ?? null) ? $turmaAgg['turma_codes'] : array_keys(is_array($turmaAgg['turma_profiles'] ?? null) ? $turmaAgg['turma_profiles'] : []);
        $semDocente = 0;
        foreach ($turmaCodes as $code) {
            $code = (string) $code;
            if ($code !== '' && empty($byTurmaProf[$code])) {
                $semDocente++;
            }
        }
        if ($semDocente > 0 && count($turmaCodes) > 0 && ! $this->hasAlertCode($alerts, 'CLIO-DOC-TURMA-SEM')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-DOC-TURMA-SEM',
                'message' => __(':n turma(s) sem profissional vinculado na Relação', ['n' => $semDocente]),
                'icon' => 'warning',
                'theme' => 'densidade',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     * @param  array<string, mixed>  $turmaAgg
     */
    private function appendJornadaAlerts(array &$alerts, array $turmaAgg): void
    {
        if ($turmaAgg === []) {
            return;
        }
        $semTurno = (int) ($turmaAgg['without_turno'] ?? 0);
        $semCh = (int) ($turmaAgg['without_ch'] ?? 0);
        if ($semTurno > 0 && ! $this->hasAlertCode($alerts, 'CLIO-JOR-SEM-TURNO')) {
            $alerts[] = [
                'severity' => 'info',
                'code' => 'CLIO-JOR-SEM-TURNO',
                'message' => __(':n turma(s) sem turno informado', ['n' => $semTurno]),
                'icon' => 'info',
                'theme' => 'tempos',
            ];
        }
        if ($semCh > 0 && ! $this->hasAlertCode($alerts, 'CLIO-JOR-SEM-CH')) {
            $alerts[] = [
                'severity' => 'info',
                'code' => 'CLIO-JOR-SEM-CH',
                'message' => __(':n turma(s) sem carga horária informada', ['n' => $semCh]),
                'icon' => 'info',
                'theme' => 'tempos',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     */
    private function appendInclusaoAlerts(array &$alerts, mixed $inclusion): void
    {
        if (! $inclusion instanceof BiClioInclusion) {
            return;
        }
        $nee = (int) $inclusion->qt_nee_people;
        $semAee = (int) $inclusion->qt_without_aee;
        $aeeSemNee = (int) $inclusion->qt_aee_without_nee;
        if ($semAee > 0 && ! $this->hasAlertCode($alerts, 'CLIO-NEE-SEM-AEE')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-NEE-SEM-AEE',
                'message' => __(':n pessoa(s) com NEE/TEA/AH sem matrícula AEE', ['n' => $semAee]),
                'icon' => 'warning',
                'theme' => 'inclusao',
            ];
        }
        if ($aeeSemNee > 0 && ! $this->hasAlertCode($alerts, 'CLIO-AEE-SEM-NEE')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-AEE-SEM-NEE',
                'message' => __(':n pessoa(s) em AEE sem tipificação NEE/TEA/AH', ['n' => $aeeSemNee]),
                'icon' => 'warning',
                'theme' => 'inclusao',
            ];
        }
        if ($nee > 0 && $semAee === 0 && $aeeSemNee === 0 && ! $this->hasAlertCode($alerts, 'CLIO-NEE-OK')) {
            $alerts[] = [
                'severity' => 'info',
                'code' => 'CLIO-NEE-OK',
                'message' => __(':n pessoa(s) com NEE/TEA/AH tipificada(s)', ['n' => $nee]),
                'icon' => 'info',
                'theme' => 'inclusao',
            ];
        }
    }

    /**
     * @param  list<array{severity: string, code: string, message: string, icon: string, theme?: string}>  $alerts
     */
    private function appendTriadeEDeltaAlerts(array &$alerts, mixed $biSchool): void
    {
        if (! $biSchool instanceof BiClioSchool || ! $biSchool->is_active) {
            return;
        }
        $parts = (int) ($biSchool->triade_parts ?? 0);
        if ($parts < 3 && ! $this->hasAlertCode($alerts, 'CLIO-TRIAD-INCOMPLETA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-TRIAD-INCOMPLETA',
                'message' => __('Tríade incompleta (:p/3 arquivos)', ['p' => $parts]),
                'icon' => 'warning',
                'theme' => 'matriculas',
            ];
        }
        $delta = $biSchool->delta_curricular;
        if ($delta !== null && (int) $delta !== 0 && ! $this->hasAlertCode($alerts, 'CLIO-DELTA-ESCOLA')) {
            $alerts[] = [
                'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                'code' => 'CLIO-DELTA-ESCOLA',
                'message' => __('Diferença Acomp × Relação de alunos: :d', [
                    'd' => ((int) $delta > 0 ? '+' : '').(int) $delta,
                ]),
                'icon' => 'warning',
                'theme' => 'matriculas',
            ];
        }
    }

    private function themeForFindingCode(string $code): string
    {
        $upper = strtoupper($code);
        if (str_contains($upper, 'NEE') || str_contains($upper, 'AEE') || str_contains($upper, 'GAP')) {
            return 'inclusao';
        }
        if (str_contains($upper, 'DIS')) {
            return 'distorcao';
        }
        if (str_contains($upper, 'DEM') || str_contains($upper, 'COR') || str_contains($upper, 'SEXO')) {
            return 'demografia';
        }
        if (str_contains($upper, 'TRA')) {
            return 'transporte';
        }
        if (str_contains($upper, 'JOR') || str_contains($upper, 'CH') || str_contains($upper, 'TURNO')) {
            return 'tempos';
        }
        if (str_contains($upper, 'DEN') || str_contains($upper, 'DOC')) {
            return 'densidade';
        }

        return 'matriculas';
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
