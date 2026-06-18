<?php

namespace App\Services\Educacenso;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Educacenso\EducacensoErrorCatalog;
use App\Support\Educacenso\EducacensoRecordTypeCatalog;
use App\Support\Ieducar\DiscrepanciesQueries;

/**
 * Orquestra leitura do arquivo Educacenso e cruzamento com i-Educar.
 */
final class EducacensoStage1ConferenceService
{
    public function __construct(
        private CityDataConnection $cityData,
        private EducacensoFileReader $reader,
        private EducacensoIeducarSnapshot $snapshot,
        private EducacensoIeducarCrossCheck $crossCheck,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(City $city, IeducarFilterState $filters, string $absolutePath, string $displayName): array
    {
        if (! filter_var(config('educacenso.enabled', true), FILTER_VALIDATE_BOOL)) {
            return $this->errorReport(__('Módulo Educacenso desactivado na configuração.'));
        }

        $parsed = $this->reader->read($absolutePath, $displayName);

        if (! ($parsed['ok'] ?? false)) {
            return $this->buildReport($city, $filters, $parsed, null, $parsed['findings'] ?? []);
        }

        try {
            $snapshot = $this->cityData->run($city, fn ($db) => $this->snapshot->capture($db, $city, $filters));
        } catch (\Throwable $e) {
            return $this->buildReport(
                $city,
                $filters,
                $parsed,
                null,
                array_merge(
                    $parsed['findings'] ?? [],
                    [[
                        'code' => 'EDU-CEN-DB',
                        'severity' => 'critical',
                        'line' => 0,
                        'record_type' => null,
                        'school_inep' => null,
                        'school_name' => null,
                        'field' => null,
                        'message' => __('Não foi possível consultar o i-Educar: :msg', ['msg' => $e->getMessage()]),
                        'suggestion' => __('Verifique conexão e credenciais da cidade.'),
                    ]],
                ),
            );
        }

        $findings = $this->crossCheck->crossCheck($parsed, $snapshot);

        return $this->buildReport($city, $filters, $parsed, $snapshot, $findings);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>|null  $snapshot
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, mixed>
     */
    private function buildReport(
        City $city,
        IeducarFilterState $filters,
        array $parsed,
        ?array $snapshot,
        array $findings,
    ): array {
        $stats = is_array($parsed['statistics'] ?? null) ? $parsed['statistics'] : [];
        $byType = is_array($stats['by_type'] ?? null) ? $stats['by_type'] : [];
        $severityCounts = $this->countBySeverity($findings);
        $status = $this->resolveStatus($severityCounts, (bool) ($parsed['ok'] ?? false));

        $bySchool = $this->buildSchoolRows($parsed, $snapshot, $findings);
        $comparison = $this->buildComparison($stats, $snapshot);
        $findingsByCode = $this->groupFindingsByCode($findings);

        return [
            'ok' => ($parsed['ok'] ?? false) && $status !== 'critical',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'status_hint' => $this->statusHint($status, $severityCounts, $comparison),
            'summary' => $this->buildSummary($stats, $snapshot, $bySchool, $severityCounts, $findings, $comparison, $filters),
            'analyzed_at' => now()->toIso8601String(),
            'city' => [
                'id' => $city->getKey(),
                'name' => $city->name,
                'uf' => $city->uf,
            ],
            'ano_letivo' => $filters->ano_letivo,
            'file' => is_array($parsed['file'] ?? null) ? $parsed['file'] : [],
            'statistics' => $stats,
            'comparison' => $comparison,
            'ieducar' => [
                'total_matriculas' => (int) ($snapshot['total_matriculas'] ?? 0),
                'schools_mapped' => count(is_array($snapshot['schools_by_inep'] ?? null) ? $snapshot['schools_by_inep'] : []),
            ],
            'severity_counts' => $severityCounts,
            'findings_count' => count($findings),
            'findings' => $findings,
            'findings_by_code' => $findingsByCode,
            'record_types' => $this->buildRecordTypeRows($byType),
            'by_school' => $bySchool,
            'chart_records' => $this->chartRecordsByType($byType),
            'chart_findings' => $this->chartFindingsBySeverity($severityCounts),
            'chart_matriculas' => $this->chartMatriculasCompare($comparison),
            'kpis' => $this->buildKpis($stats, $snapshot, $severityCounts, $findings, $comparison),
            'parse_error' => $parsed['error'] ?? null,
        ];
    }

    /**
     * @param  array<string, int>  $byType
     */
    private function chartRecordsByType(array $byType): ?array
    {
        if ($byType === []) {
            return null;
        }

        ksort($byType);
        $labels = array_keys($byType);
        $values = array_map('intval', array_values($byType));

        return ChartPayload::bar(
            __('Registos no arquivo Educacenso'),
            __('Quantidade'),
            $labels,
            $values,
        );
    }

    /**
     * @param  array<string, int>  $severityCounts
     */
    private function chartFindingsBySeverity(array $severityCounts): ?array
    {
        $labels = [];
        $values = [];
        foreach (['critical', 'error', 'warning', 'info'] as $sev) {
            $n = (int) ($severityCounts[$sev] ?? 0);
            if ($n > 0) {
                $labels[] = $this->severityLabel($sev);
                $values[] = $n;
            }
        }

        if ($labels === []) {
            return null;
        }

        return ChartPayload::bar(
            __('Achados por severidade'),
            __('Ocorrências'),
            $labels,
            $values,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, int>
     */
    private function countBySeverity(array $findings): array
    {
        $counts = ['critical' => 0, 'error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($findings as $f) {
            $sev = (string) ($f['severity'] ?? 'info');
            if (! isset($counts[$sev])) {
                $counts[$sev] = 0;
            }
            $counts[$sev]++;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $severityCounts
     */
    private function resolveStatus(array $severityCounts, bool $parseOk): string
    {
        if (! $parseOk || ($severityCounts['critical'] ?? 0) > 0) {
            return 'critical';
        }
        if (($severityCounts['error'] ?? 0) > 0) {
            return 'error';
        }
        if (($severityCounts['warning'] ?? 0) > 0) {
            return 'warning';
        }

        return 'ok';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'critical' => __('Crítico'),
            'error' => __('Com erros'),
            'warning' => __('Atenção'),
            default => __('Conferência OK'),
        };
    }

    private function chartMatriculasCompare(?array $comparison): ?array
    {
        if ($comparison === null) {
            return null;
        }

        $file = (int) ($comparison['matriculas_arquivo'] ?? 0);
        $ied = (int) ($comparison['matriculas_ieducar'] ?? 0);
        if ($file <= 0 && $ied <= 0) {
            return null;
        }

        return ChartPayload::bar(
            __('Matrículas — Educacenso × i-Educar'),
            __('Quantidade'),
            [__('Educacenso (reg. 60)'), __('i-Educar (filtro)')],
            [$file, $ied],
        );
    }

    /**
     * @param  array<string, int>  $byType
     * @return list<array{type: string, label: string, hint: string, count: int}>
     */
    private function buildRecordTypeRows(array $byType): array
    {
        ksort($byType);
        $rows = [];
        foreach ($byType as $type => $count) {
            $desc = EducacensoRecordTypeCatalog::describe((string) $type);
            $rows[] = [
                'type' => (string) $type,
                'label' => $desc['label'],
                'hint' => $desc['hint'],
                'count' => (int) $count,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array{code: string, title: string, severity: string, severity_label: string, count: int, suggestion: string}>
     */
    private function groupFindingsByCode(array $findings): array
    {
        $groups = [];
        foreach ($findings as $f) {
            $code = (string) ($f['code'] ?? '');
            if ($code === '') {
                continue;
            }
            if (! isset($groups[$code])) {
                $meta = EducacensoErrorCatalog::get($code);
                $groups[$code] = [
                    'code' => $code,
                    'title' => $meta['message'],
                    'severity' => (string) ($f['severity'] ?? $meta['severity']),
                    'severity_label' => $this->severityLabel((string) ($f['severity'] ?? $meta['severity'])),
                    'count' => 0,
                    'suggestion' => $meta['suggestion'],
                ];
            }
            $groups[$code]['count']++;
        }

        usort($groups, static fn (array $a, array $b): int => ($b['count'] <=> $a['count']) ?: strcmp($a['code'], $b['code']));

        return array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    private function buildComparison(array $stats, ?array $snapshot): ?array
    {
        $fileMat = (int) ($stats['matriculas'] ?? 0);
        $iedMat = (int) ($snapshot['total_matriculas'] ?? 0);
        if ($fileMat <= 0 && $iedMat <= 0) {
            return null;
        }

        $diff = $iedMat - $fileMat;
        $pct = $fileMat > 0 ? round(100.0 * abs($diff) / $fileMat, 1) : ($iedMat > 0 ? 100.0 : 0.0);
        $tolerancePct = max(0.0, (float) config('educacenso.tolerance_matricula_pct', 5));
        $minDiff = max(1, (int) config('educacenso.tolerance_matricula_min_diff', 10));

        $row = DiscrepanciesQueries::buildCensoMatriculaDiffRow($iedMat, $fileMat, $tolerancePct, $minDiff);
        $withinTolerance = $row === null && $fileMat > 0 && $iedMat > 0;

        $direction = match (true) {
            $diff > 0 => 'ieducar_maior',
            $diff < 0 => 'arquivo_maior',
            default => 'igual',
        };

        return [
            'matriculas_arquivo' => $fileMat,
            'matriculas_ieducar' => $iedMat,
            'delta' => $diff,
            'delta_abs' => abs($diff),
            'delta_pct' => $pct,
            'direction' => $direction,
            'direction_label' => match ($direction) {
                'ieducar_maior' => __('i-Educar com mais matrículas que o arquivo'),
                'arquivo_maior' => __('Arquivo Educacenso com mais matrículas que o i-Educar'),
                default => __('Totais de matrícula coincidentes'),
            },
            'within_tolerance' => $withinTolerance,
            'tolerance_pct' => $tolerancePct,
            'tolerance_min_diff' => $minDiff,
            'turmas_arquivo' => (int) ($stats['turmas'] ?? 0),
            'pessoas_arquivo' => (int) ($stats['pessoas'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>|null  $snapshot
     * @param  list<array<string, mixed>>  $bySchool
     * @param  array<string, int>  $severityCounts
     * @param  list<array<string, mixed>>  $findings
     * @param  array<string, mixed>|null  $comparison
     * @return array<string, mixed>
     */
    private function buildSummary(
        array $stats,
        ?array $snapshot,
        array $bySchool,
        array $severityCounts,
        array $findings,
        ?array $comparison,
        IeducarFilterState $filters,
    ): array {
        $schoolsWithIssues = count(array_filter($bySchool, static fn (array $r): bool => (int) ($r['issues'] ?? 0) > 0));
        $schoolsOk = count(array_filter($bySchool, static fn (array $r): bool => (int) ($r['issues'] ?? 0) === 0 && ($r['in_file'] ?? false) && ($r['in_ieducar'] ?? false)));

        return [
            'ano_letivo' => $filters->ano_letivo,
            'escolas_arquivo' => (int) ($stats['schools'] ?? 0),
            'escolas_ieducar_inep' => (int) ($snapshot !== null ? count($snapshot['schools_by_inep'] ?? []) : 0),
            'escolas_com_achados' => $schoolsWithIssues,
            'escolas_conferidas_ok' => $schoolsOk,
            'achados_total' => count($findings),
            'achados_criticos' => (int) ($severityCounts['critical'] ?? 0),
            'achados_erro' => (int) ($severityCounts['error'] ?? 0),
            'achados_aviso' => (int) ($severityCounts['warning'] ?? 0),
            'matriculas_arquivo' => (int) ($comparison['matriculas_arquivo'] ?? 0),
            'matriculas_ieducar' => (int) ($comparison['matriculas_ieducar'] ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $severityCounts
     * @param  array<string, mixed>|null  $comparison
     */
    private function statusHint(string $status, array $severityCounts, ?array $comparison): string
    {
        if ($status === 'critical') {
            return __('Há impedimentos graves — corrija antes de considerar a declaração conferida.');
        }
        if ($status === 'error') {
            return __('Existem divergências que exigem revisão cadastral ou no arquivo Educacenso.');
        }
        if ($status === 'warning') {
            return __('Pequenas diferenças ou escolas a validar — revise a lista de achados e a tabela por escola.');
        }
        if ($comparison !== null && ($comparison['within_tolerance'] ?? false)) {
            return __('Totais de matrícula dentro da tolerância configurada. Confira escola a escola para garantir.');
        }

        return __('Nenhum achado relevante na simulação para o filtro aplicado.');
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => __('Crítico'),
            'error' => __('Erro'),
            'warning' => __('Aviso'),
            default => __('Info'),
        };
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>|null  $snapshot
     * @param  array<string, int>  $severityCounts
     * @param  list<array<string, mixed>>  $findings
     * @param  array<string, mixed>|null  $comparison
     * @return list<array<string, mixed>>
     */
    private function buildKpis(array $stats, ?array $snapshot, array $severityCounts, array $findings, ?array $comparison): array
    {
        $deltaLabel = '—';
        if ($comparison !== null && ($comparison['delta'] ?? 0) !== 0) {
            $sign = ($comparison['delta'] ?? 0) > 0 ? '+' : '';
            $deltaLabel = $sign.number_format((int) $comparison['delta']);
        }

        return [
            ['label' => __('Escolas no arquivo'), 'value' => number_format((int) ($stats['schools'] ?? 0)), 'tone' => 'sky'],
            ['label' => __('Matrículas Educacenso'), 'value' => number_format((int) ($stats['matriculas'] ?? 0)), 'tone' => 'violet'],
            ['label' => __('Matrículas i-Educar'), 'value' => number_format((int) ($snapshot['total_matriculas'] ?? 0)), 'tone' => 'emerald'],
            ['label' => __('Diferença (rede)'), 'value' => $deltaLabel, 'tone' => ($comparison['within_tolerance'] ?? true) ? 'emerald' : 'amber', 'explicacao_resumo' => $comparison['direction_label'] ?? null],
            ['label' => __('Achados'), 'value' => number_format(count($findings)), 'tone' => ($severityCounts['critical'] ?? 0) + ($severityCounts['error'] ?? 0) > 0 ? 'rose' : 'amber'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>|null  $snapshot
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private function buildSchoolRows(array $parsed, ?array $snapshot, array $findings): array
    {
        $stats = is_array($parsed['statistics'] ?? null) ? $parsed['statistics'] : [];
        $fileSchools = is_array($stats['school_ineps'] ?? null) ? $stats['school_ineps'] : [];
        $byInep = is_array($snapshot['schools_by_inep'] ?? null) ? $snapshot['schools_by_inep'] : [];

        $issuesByInep = [];
        foreach ($findings as $f) {
            $inep = (string) ($f['school_inep'] ?? '');
            if ($inep === '') {
                continue;
            }
            $issuesByInep[$inep] = ($issuesByInep[$inep] ?? 0) + 1;
        }

        $rows = [];
        $allIneps = array_unique(array_merge($fileSchools, array_keys($byInep)));

        foreach ($allIneps as $inep) {
            $inFile = in_array($inep, $fileSchools, true);
            $ied = $byInep[$inep] ?? null;
            $fileMat = $inFile ? $this->countSchoolReg60($parsed, $inep) : 0;
            $iedMat = $ied !== null ? (int) ($ied['matriculas'] ?? 0) : 0;

            $delta = $iedMat - $fileMat;
            $deltaPct = $fileMat > 0 ? round(100.0 * abs($delta) / $fileMat, 1) : ($iedMat > 0 ? 100.0 : 0.0);
            $match = $this->resolveSchoolMatchStatus($inFile, $ied !== null, $fileMat, $iedMat, (int) ($issuesByInep[$inep] ?? 0));

            $rows[] = [
                'inep' => $inep,
                'nome' => $ied !== null ? (string) ($ied['nome'] ?? '—') : ($inFile ? __('Escola no arquivo (sem INEP local)') : '—'),
                'in_file' => $inFile,
                'in_ieducar' => $ied !== null,
                'matriculas_file' => $fileMat,
                'matriculas_ieducar' => $iedMat,
                'delta' => $delta,
                'delta_pct' => $deltaPct,
                'issues' => (int) ($issuesByInep[$inep] ?? 0),
                'match_status' => $match['status'],
                'match_label' => $match['label'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['issues'] <=> $a['issues']) ?: strcasecmp($a['nome'], $b['nome']));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function countSchoolReg60(array $parsed, string $inep): int
    {
        $stats = is_array($parsed['statistics'] ?? null) ? $parsed['statistics'] : [];
        $byInep = is_array($stats['matriculas_by_inep'] ?? null) ? $stats['matriculas_by_inep'] : [];
        if (isset($byInep[$inep])) {
            return (int) $byInep[$inep];
        }

        $n = 0;
        foreach (is_array($parsed['records'] ?? null) ? $parsed['records'] : [] as $rec) {
            if (($rec['type'] ?? '') === '60' && ($rec['school_inep'] ?? '') === $inep) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return array{status: string, label: string}
     */
    private function resolveSchoolMatchStatus(bool $inFile, bool $inIeducar, int $fileMat, int $iedMat, int $issues): array
    {
        if ($issues > 0) {
            return ['status' => 'divergence', 'label' => __('Divergência')];
        }
        if ($inFile && ! $inIeducar) {
            return ['status' => 'missing_ieducar', 'label' => __('Sem INEP local')];
        }
        if (! $inFile && $inIeducar && $iedMat > 0) {
            return ['status' => 'missing_file', 'label' => __('Ausente no arquivo')];
        }
        if ($inFile && $inIeducar && $fileMat !== $iedMat) {
            return ['status' => 'count_diff', 'label' => __('Contagem diferente')];
        }
        if ($inFile && $inIeducar) {
            return ['status' => 'ok', 'label' => __('Conferido')];
        }

        return ['status' => 'neutral', 'label' => __('—')];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorReport(string $message): array
    {
        return [
            'ok' => false,
            'status' => 'critical',
            'status_label' => __('Crítico'),
            'parse_error' => $message,
            'findings' => [],
            'statistics' => [],
            'kpis' => [],
        ];
    }
}
