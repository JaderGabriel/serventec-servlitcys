<?php

namespace App\Services\Educacenso;

use App\Support\Educacenso\EducacensoErrorCatalog;
use App\Support\Ieducar\DiscrepanciesQueries;

/**
 * Cruza arquivo Educacenso parseado com snapshot i-Educar.
 */
final class EducacensoIeducarCrossCheck
{
    /**
     * @param  array<string, mixed>  $parsed  Resultado de EducacensoFileReader::read
     * @param  array<string, mixed>  $snapshot  Resultado de EducacensoIeducarSnapshot::capture
     * @return list<array<string, mixed>>
     */
    public function crossCheck(array $parsed, array $snapshot): array
    {
        $findings = is_array($parsed['findings'] ?? null) ? $parsed['findings'] : [];
        if (! ($parsed['ok'] ?? false)) {
            return $findings;
        }

        $stats = is_array($parsed['statistics'] ?? null) ? $parsed['statistics'] : [];
        $fileSchools = is_array($stats['school_ineps'] ?? null) ? $stats['school_ineps'] : [];
        $fileSchoolSet = array_fill_keys($fileSchools, true);

        $byInep = is_array($snapshot['schools_by_inep'] ?? null) ? $snapshot['schools_by_inep'] : [];
        $ieducarTotal = (int) ($snapshot['total_matriculas'] ?? 0);
        $fileMatriculas = (int) ($stats['matriculas'] ?? 0);

        foreach ($fileSchools as $inep) {
            if (! isset($byInep[$inep])) {
                $findings[] = $this->finding('EDU-CEN-101', 0, '00', $inep, null);
            }
        }

        foreach ($byInep as $inep => $school) {
            $mat = (int) ($school['matriculas'] ?? 0);
            $nome = (string) ($school['nome'] ?? '');

            if ($mat > 0 && ! isset($fileSchoolSet[$inep])) {
                $findings[] = $this->finding('EDU-CEN-102', 0, '00', $inep, $nome);
            }

            if (isset($fileSchoolSet[$inep]) && $mat === 0) {
                $findings[] = $this->finding('EDU-CEN-103', 0, '00', $inep, $nome);
            }

            if (isset($fileSchoolSet[$inep]) && $mat > 0) {
                $fileCountForSchool = $this->countMatriculasForSchool($parsed, $inep);
                if ($fileCountForSchool > 0 && $fileCountForSchool !== $mat) {
                    $findings[] = array_merge(
                        $this->finding('EDU-CEN-502', 0, '60', $inep, $nome),
                        [
                            'meta' => [
                                'educacenso' => $fileCountForSchool,
                                'ieducar' => $mat,
                                'delta' => $fileCountForSchool - $mat,
                            ],
                        ],
                    );
                }
            }
        }

        $tolerancePct = max(0.0, (float) config('educacenso.tolerance_matricula_pct', 5));
        $minDiff = max(1, (int) config('educacenso.tolerance_matricula_min_diff', 10));

        if ($ieducarTotal > 0 && $fileMatriculas > 0) {
            $row = DiscrepanciesQueries::buildCensoMatriculaDiffRow(
                $ieducarTotal,
                $fileMatriculas,
                $tolerancePct,
                $minDiff,
            );
            if ($row !== null) {
                $findings[] = array_merge(
                    $this->finding('EDU-CEN-501', 0, '60', null, __('Rede municipal')),
                    [
                        'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : [],
                    ],
                );
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function countMatriculasForSchool(array $parsed, string $schoolInep): int
    {
        $stats = is_array($parsed['statistics'] ?? null) ? $parsed['statistics'] : [];
        $byInep = is_array($stats['matriculas_by_inep'] ?? null) ? $stats['matriculas_by_inep'] : [];
        if (isset($byInep[$schoolInep])) {
            return (int) $byInep[$schoolInep];
        }

        $records = is_array($parsed['records'] ?? null) ? $parsed['records'] : [];
        $count = 0;
        foreach ($records as $rec) {
            if (($rec['type'] ?? '') === '60' && ($rec['school_inep'] ?? '') === $schoolInep) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $code, int $line, ?string $recordType, ?string $schoolInep, ?string $schoolName): array
    {
        $meta = EducacensoErrorCatalog::get($code);

        return [
            'code' => $code,
            'severity' => $meta['severity'],
            'line' => $line,
            'record_type' => $recordType,
            'school_inep' => $schoolInep,
            'school_name' => $schoolName,
            'field' => null,
            'message' => $meta['message'],
            'suggestion' => $meta['suggestion'],
        ];
    }
}
