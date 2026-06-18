<?php

namespace App\Services\Educacenso;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Educacenso\EducacensoErrorCatalog;

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

        return [
            'ok' => ($parsed['ok'] ?? false) && $status !== 'critical',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'analyzed_at' => now()->toIso8601String(),
            'city' => [
                'id' => $city->getKey(),
                'name' => $city->name,
                'uf' => $city->uf,
            ],
            'ano_letivo' => $filters->ano_letivo,
            'file' => is_array($parsed['file'] ?? null) ? $parsed['file'] : [],
            'statistics' => $stats,
            'ieducar' => [
                'total_matriculas' => (int) ($snapshot['total_matriculas'] ?? 0),
                'schools_mapped' => count(is_array($snapshot['schools_by_inep'] ?? null) ? $snapshot['schools_by_inep'] : []),
            ],
            'severity_counts' => $severityCounts,
            'findings_count' => count($findings),
            'findings' => $findings,
            'by_school' => $bySchool,
            'chart_records' => $this->chartRecordsByType($byType),
            'chart_findings' => $this->chartFindingsBySeverity($severityCounts),
            'kpis' => $this->buildKpis($stats, $snapshot, $severityCounts, $findings),
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
     * @return list<array<string, mixed>>
     */
    private function buildKpis(array $stats, ?array $snapshot, array $severityCounts, array $findings): array
    {
        $kpis = [
            ['label' => __('Escolas no arquivo'), 'value' => number_format((int) ($stats['schools'] ?? 0)), 'tone' => 'sky'],
            ['label' => __('Matrículas (reg. 60)'), 'value' => number_format((int) ($stats['matriculas'] ?? 0)), 'tone' => 'violet'],
            ['label' => __('Matrículas i-Educar'), 'value' => number_format((int) ($snapshot['total_matriculas'] ?? 0)), 'tone' => 'emerald'],
            ['label' => __('Achados'), 'value' => number_format(count($findings)), 'tone' => ($severityCounts['critical'] ?? 0) + ($severityCounts['error'] ?? 0) > 0 ? 'rose' : 'amber'],
        ];

        return $kpis;
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

            $rows[] = [
                'inep' => $inep,
                'nome' => $ied !== null ? (string) ($ied['nome'] ?? '—') : '—',
                'in_file' => $inFile,
                'in_ieducar' => $ied !== null,
                'matriculas_file' => $fileMat,
                'matriculas_ieducar' => $iedMat,
                'issues' => (int) ($issuesByInep[$inep] ?? 0),
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
