<?php

namespace App\Services\Inep;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Converte XLSX de divulgação municipal do IDEB (INEP) em pontos brutos para saeb_indicator_points.
 *
 * Cabeçalho técnico (linha com SG_UF / CO_MUNICIPIO / REDE / VL_OBSERVADO_YYYY / VL_NOTA_*_YYYY).
 */
final class IdebDivulgacaoInepConverter
{
    /**
     * @param  array<string, true>|null  $allowedIbge
     * @return array{
     *   pontos: list<array<string, mixed>>,
     *   municipios: int,
     *   warnings: list<string>,
     *   years_ideb: list<int>,
     *   years_saeb: list<int>
     * }
     */
    public function spreadsheetToPontos(
        string $spreadsheetPath,
        string $etapa,
        int $minYear,
        bool $importSaebNotas,
        string $preferRede,
        ?array $allowedIbge = null,
        ?array $ibgeToCityIds = null,
    ): array {
        $warnings = [];
        $ext = strtolower(pathinfo($spreadsheetPath, PATHINFO_EXTENSION));
        $reader = match ($ext) {
            'xlsx' => IOFactory::createReader('Xlsx'),
            'xls' => IOFactory::createReader('Xls'),
            default => IOFactory::createReaderForFile($spreadsheetPath),
        };
        $reader->setReadDataOnly(true);

        try {
            $names = $reader->listWorksheetNames($spreadsheetPath);
            $target = null;
            foreach ($names as $nm) {
                $trimmed = trim((string) $nm);
                if (stripos($trimmed, 'munic') !== false || stripos($trimmed, 'ideb') !== false) {
                    $target = $nm;
                    break;
                }
            }
            if ($target !== null) {
                $reader->setLoadSheetsOnly($target);
            }
        } catch (\Throwable) {
            // carrega o ficheiro completo
        }

        $spreadsheet = $reader->load($spreadsheetPath);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet);

        if ($matrix === []) {
            throw new \RuntimeException(__('Planilha IDEB vazia.'));
        }

        [$headerRowIdx, $headers] = $this->locateTechnicalHeaderFromMatrix($matrix);
        if ($headers === []) {
            throw new \RuntimeException(__('Cabeçalho técnico IDEB (CO_MUNICIPIO / VL_OBSERVADO_*) não encontrado.'));
        }

        $colMap = $this->columnIndexMap($headers);
        if (! isset($colMap['CO_MUNICIPIO']) || ! isset($colMap['REDE'])) {
            throw new \RuntimeException(__('Colunas CO_MUNICIPIO e REDE são obrigatórias na planilha IDEB.'));
        }

        $idebCols = $this->detectYearColumns($headers, 'VL_OBSERVADO_', $minYear);
        $saebLpCols = $importSaebNotas ? $this->detectYearColumns($headers, 'VL_NOTA_PORTUGUES_', $minYear) : [];
        $saebMatCols = $importSaebNotas ? $this->detectYearColumns($headers, 'VL_NOTA_MATEMATICA_', $minYear) : [];

        if ($idebCols === []) {
            throw new \RuntimeException(__('Nenhuma coluna VL_OBSERVADO_YYYY (≥ :y) encontrada.', ['y' => (string) $minYear]));
        }

        $preferRedeNorm = $this->normalizeRede($preferRede);
        $pontos = [];
        $municipios = [];

        for ($r = $headerRowIdx + 1, $max = count($matrix); $r < $max; $r++) {
            $row = $matrix[$r];
            if (! is_array($row) || $row === []) {
                continue;
            }

            $ibge = $this->normalizeIbge($row[$colMap['CO_MUNICIPIO']] ?? null);
            if ($ibge === null) {
                continue;
            }
            if ($allowedIbge !== null && ! isset($allowedIbge[$ibge])) {
                continue;
            }

            $rede = $this->normalizeRede((string) ($row[$colMap['REDE']] ?? ''));
            if ($rede !== $preferRedeNorm) {
                continue;
            }

            $cityIds = null;
            if (is_array($ibgeToCityIds) && isset($ibgeToCityIds[$ibge]) && is_array($ibgeToCityIds[$ibge])) {
                $cityIds = $ibgeToCityIds[$ibge];
            }

            foreach ($idebCols as $year => $colIdx) {
                $val = $this->parseNumeric($row[$colIdx] ?? null);
                if ($val === null) {
                    continue;
                }
                $ponto = [
                    'ano' => $year,
                    'disciplina' => 'ideb',
                    'etapa' => $etapa,
                    'valor' => $val,
                    'status' => 'final',
                    'unidade' => 'IDEB',
                    'municipio_ibge' => $ibge,
                    'fonte_ideb' => 'divulgacao_inep',
                ];
                if ($cityIds !== null) {
                    $ponto['city_ids'] = $cityIds;
                }
                $pontos[] = $ponto;
                $municipios[$ibge] = true;
            }

            foreach ($saebLpCols as $year => $colIdx) {
                $val = $this->parseNumeric($row[$colIdx] ?? null);
                if ($val === null) {
                    continue;
                }
                $ponto = [
                    'ano' => $year,
                    'disciplina' => 'lp',
                    'etapa' => $etapa,
                    'valor' => $val,
                    'status' => 'final',
                    'unidade' => 'escala',
                    'municipio_ibge' => $ibge,
                    'fonte_ideb' => 'divulgacao_inep_saeb',
                ];
                if ($cityIds !== null) {
                    $ponto['city_ids'] = $cityIds;
                }
                $pontos[] = $ponto;
                $municipios[$ibge] = true;
            }

            foreach ($saebMatCols as $year => $colIdx) {
                $val = $this->parseNumeric($row[$colIdx] ?? null);
                if ($val === null) {
                    continue;
                }
                $ponto = [
                    'ano' => $year,
                    'disciplina' => 'mat',
                    'etapa' => $etapa,
                    'valor' => $val,
                    'status' => 'final',
                    'unidade' => 'escala',
                    'municipio_ibge' => $ibge,
                    'fonte_ideb' => 'divulgacao_inep_saeb',
                ];
                if ($cityIds !== null) {
                    $ponto['city_ids'] = $cityIds;
                }
                $pontos[] = $ponto;
                $municipios[$ibge] = true;
            }
        }

        unset($matrix);

        return [
            'pontos' => $pontos,
            'municipios' => count($municipios),
            'warnings' => $warnings,
            'years_ideb' => array_keys($idebCols),
            'years_saeb' => array_values(array_unique(array_merge(array_keys($saebLpCols), array_keys($saebMatCols)))),
        ];
    }

    /**
     * @param  list<list<mixed>>  $matrix
     * @return array{0: int, 1: list<string>}
     */
    private function locateTechnicalHeaderFromMatrix(array $matrix): array
    {
        $limit = min(30, count($matrix));
        for ($r = 0; $r < $limit; $r++) {
            $row = $matrix[$r] ?? [];
            if (! is_array($row)) {
                continue;
            }
            $headers = [];
            $hasCo = false;
            $hasObs = false;
            foreach ($row as $raw) {
                $h = strtoupper(trim((string) ($raw ?? '')));
                $headers[] = $h;
                if ($h === 'CO_MUNICIPIO') {
                    $hasCo = true;
                }
                if (str_starts_with($h, 'VL_OBSERVADO_')) {
                    $hasObs = true;
                }
            }
            if ($hasCo && $hasObs) {
                return [$r, $headers];
            }
        }

        return [0, []];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function columnIndexMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            if ($h !== '') {
                $map[$h] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $headers
     * @return array<int, int> year => column index (0-based)
     */
    private function detectYearColumns(array $headers, string $prefix, int $minYear): array
    {
        $out = [];
        $prefix = strtoupper($prefix);
        foreach ($headers as $i => $h) {
            if (! str_starts_with($h, $prefix)) {
                continue;
            }
            $suffix = substr($h, strlen($prefix));
            if (! ctype_digit($suffix)) {
                continue;
            }
            $year = (int) $suffix;
            if ($year < $minYear || $year > 2100) {
                continue;
            }
            $out[$year] = $i;
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }

    private function normalizeIbge(mixed $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($raw ?? '')) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) < 7) {
            $d = str_pad($d, 7, '0', STR_PAD_LEFT);
        }

        return strlen($d) === 7 ? $d : null;
    }

    private function normalizeRede(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

        return match (true) {
            str_contains($s, 'municipal') => 'municipal',
            str_contains($s, 'estadual') => 'estadual',
            str_contains($s, 'federal') => 'federal',
            str_contains($s, 'privada') || str_contains($s, 'particular') => 'privada',
            str_contains($s, 'publica') || $s === 'publica' => 'publica',
            default => $s,
        };
    }

    private function parseNumeric(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = trim((string) $raw);
        if ($s === '' || $s === '-' || strcasecmp($s, 'ND') === 0 || strcasecmp($s, 'N/D') === 0) {
            return null;
        }
        $s = str_replace([' ', ','], ['', '.'], $s);
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
