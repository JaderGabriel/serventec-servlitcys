<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\RelationCsvAggregator;

/**
 * Quadro Diagnóstico Geral: escolas ativas × erros/avisos (inclui Cor/Raça sem declaração).
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
     *     }
     * }
     */
    public function compose(ClioCampaign $campaign): array
    {
        $campaign->loadMissing(['schools.artifacts', 'findings']);

        $findingsBySchool = $campaign->findings
            ->filter(static function (ClioCampaignFinding $f): bool {
                return in_array($f->severity, [
                    ClioCampaignFinding::SEVERITY_ERROR,
                    ClioCampaignFinding::SEVERITY_WARNING,
                ], true);
            })
            ->groupBy(static fn (ClioCampaignFinding $f): int => (int) ($f->school_id ?? 0));

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

            $withoutCor = $this->withoutCorCount($school);
            if ($withoutCor !== null && $withoutCor > 0) {
                $corTotal += $withoutCor;
                $corSchools[] = [
                    'inep' => (string) $school->inep_code,
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
                $alerts = [[
                    'severity' => 'ok',
                    'code' => 'OK',
                    'message' => __('Não há alertas/pendências a serem destacadas'),
                    'icon' => 'ok',
                ]];
            }

            $rows[] = [
                'inep' => (string) $school->inep_code,
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
        ];
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

    private function withoutCorCount(ClioCampaignSchool $school): ?int
    {
        $artifact = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
        if ($artifact === null) {
            return null;
        }
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = is_array($meta['aggregates'] ?? null) ? $meta['aggregates'] : null;
        if ($agg === null) {
            return null;
        }
        $cols = is_array($agg['columns'] ?? null) ? $agg['columns'] : [];
        if (empty($cols['cor_raca'])) {
            return null;
        }

        return $this->aggregator->undeclaredCorCountFromAggregates($agg);
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
                'ok' => 2,
            ];

            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });

        return $out;
    }
}
