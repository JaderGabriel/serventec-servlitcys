<?php

namespace App\Services\Educacenso;

use App\Support\Educacenso\EducacensoErrorCatalog;

/**
 * Lê arquivo pipe-delimited do Educacenso (portal INEP).
 */
final class EducacensoFileReader
{
    /**
     * @return array{
     *   ok: bool,
     *   error: ?string,
     *   file: array<string, mixed>,
     *   records: list<array{line: int, type: string, fields: list<string>, school_inep: ?string}>,
     *   statistics: array<string, mixed>,
     *   findings: list<array<string, mixed>>
     * }
     */
    public function read(string $absolutePath, string $displayName): array
    {
        $findings = [];
        $records = [];
        $countsByType = [];
        $schoolIneps = [];
        $matriculasByInep = [];
        $lineNo = 0;
        $storeRecordsMax = max(0, (int) config('educacenso.store_records_max', 50_000));

        if (! is_readable($absolutePath)) {
            return $this->fail(__('Ficheiro não legível.'), $displayName, 0);
        }

        $size = filesize($absolutePath);
        if ($size === false || $size === 0) {
            $findings[] = $this->finding('EDU-CEN-001', 0, null, null);

            return $this->fail(__('Arquivo vazio.'), $displayName, 0, $findings);
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return $this->fail(__('Não foi possível abrir o arquivo.'), $displayName, $size);
        }

        $knownTypes = array_flip((array) config('educacenso.record_types_stage1', []));
        $schoolIdxMap = (array) config('educacenso.school_inep_field_index', []);

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $trimmed = rtrim($line, "\r\n");
            if ($trimmed === '') {
                continue;
            }

            $fields = explode('|', $trimmed);
            $type = str_pad(trim((string) ($fields[0] ?? '')), 2, '0', STR_PAD_LEFT);

            if ($type === '' || ! isset($knownTypes[$type])) {
                if ($type !== '' && ! in_array($type, ['70', '80', '90', '91'], true)) {
                    $findings[] = $this->finding('EDU-CEN-002', $lineNo, $type, $this->schoolInepFromFields($type, $fields, $schoolIdxMap));
                }

                continue;
            }

            if (count($fields) < 2) {
                $findings[] = $this->finding('EDU-CEN-005', $lineNo, $type, null);

                continue;
            }

            $schoolInep = $this->schoolInepFromFields($type, $fields, $schoolIdxMap);
            if ($type === '00') {
                if ($schoolInep === null) {
                    $findings[] = $this->finding('EDU-CEN-004', $lineNo, $type, null);
                } else {
                    $schoolIneps[$schoolInep] = true;
                }
            }

            $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;

            if ($type === '60' && $schoolInep !== null) {
                $matriculasByInep[$schoolInep] = ($matriculasByInep[$schoolInep] ?? 0) + 1;
            }

            if ($storeRecordsMax === 0 || count($records) < $storeRecordsMax) {
                $records[] = [
                    'line' => $lineNo,
                    'type' => $type,
                    'school_inep' => $schoolInep,
                ];
            }
        }

        fclose($handle);

        if ($countsByType === []) {
            $findings[] = $this->finding('EDU-CEN-001', 0, null, null);

            return $this->fail(__('Nenhum registro válido encontrado.'), $displayName, $size, $findings);
        }

        if (($countsByType['00'] ?? 0) === 0) {
            $findings[] = $this->finding('EDU-CEN-004', 0, '00', null);
        }

        $hash = hash_file('sha256', $absolutePath) ?: '';

        return [
            'ok' => true,
            'error' => null,
            'file' => [
                'name' => $displayName,
                'size' => $size,
                'hash' => $hash,
                'lines' => $lineNo,
            ],
            'records' => $records,
            'statistics' => [
                'total_lines' => $lineNo,
                'total_records' => array_sum($countsByType),
                'records_sampled' => count($records),
                'by_type' => $countsByType,
                'schools' => count($schoolIneps),
                'matriculas' => (int) ($countsByType['60'] ?? 0),
                'matriculas_by_inep' => $matriculasByInep,
                'turmas' => (int) ($countsByType['20'] ?? 0),
                'pessoas' => (int) ($countsByType['30'] ?? 0),
                'profissionais' => (int) (($countsByType['40'] ?? 0) + ($countsByType['50'] ?? 0) + ($countsByType['51'] ?? 0)),
                'school_ineps' => array_keys($schoolIneps),
            ],
            'findings' => $findings,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, int>  $schoolIdxMap
     */
    private function schoolInepFromFields(string $type, array $fields, array $schoolIdxMap): ?string
    {
        $idx = $schoolIdxMap[$type] ?? 1;
        $raw = trim((string) ($fields[$idx] ?? ''));
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }

        return substr($digits, 0, 8);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, mixed>
     */
    private function fail(string $error, string $name, int $size, array $findings = []): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'file' => ['name' => $name, 'size' => $size, 'hash' => '', 'lines' => 0],
            'records' => [],
            'statistics' => [
                'total_lines' => 0,
                'total_records' => 0,
                'by_type' => [],
                'schools' => 0,
                'matriculas' => 0,
                'turmas' => 0,
                'pessoas' => 0,
                'profissionais' => 0,
                'school_ineps' => [],
            ],
            'findings' => $findings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $code, int $line, ?string $recordType, ?string $schoolInep): array
    {
        $meta = EducacensoErrorCatalog::get($code);

        return [
            'code' => $code,
            'severity' => $meta['severity'],
            'line' => $line,
            'record_type' => $recordType,
            'school_inep' => $schoolInep,
            'school_name' => null,
            'field' => null,
            'message' => $meta['message'],
            'suggestion' => $meta['suggestion'],
        ];
    }
}
