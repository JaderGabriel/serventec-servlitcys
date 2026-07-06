<?php

namespace App\Services\Inep;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Converte planilhas oficiais INEP (aba Municípios, CO_MUNICIPIO) para CSV canónico SAEB.
 */
final class SaebPlanilhaInepConverter
{
    private const SHEET_MUNICIPIOS_NAMES = ['Municípios', 'Municipios', 'MUNICIPIOS'];

    /** @var list<string> */
    private const MEDIA_PATTERN = [
        'MEDIA_5_LP' => ['disc' => 'lp', 'etapa' => 'efi'],
        'MEDIA_5_MT' => ['disc' => 'mat', 'etapa' => 'efi'],
        'MEDIA_9_LP' => ['disc' => 'lp', 'etapa' => 'efaf'],
        'MEDIA_9_MT' => ['disc' => 'mat', 'etapa' => 'efaf'],
        'MEDIA_12_LP' => ['disc' => 'lp', 'etapa' => 'em'],
        'MEDIA_12_MT' => ['disc' => 'mat', 'etapa' => 'em'],
        'MEDIA_2_LP' => ['disc' => 'lp', 'etapa' => '2ef'],
        'MEDIA_2_MT' => ['disc' => 'mat', 'etapa' => '2ef'],
        'MEDIA_EMT_LP' => ['disc' => 'lp', 'etapa' => 'em'],
        'MEDIA_EMT_MT' => ['disc' => 'mat', 'etapa' => 'em'],
        'MEDIA_EMI_LP' => ['disc' => 'lp', 'etapa' => 'em'],
        'MEDIA_EMI_MT' => ['disc' => 'mat', 'etapa' => 'em'],
        'MEDIA_EM_LP' => ['disc' => 'lp', 'etapa' => 'em'],
        'MEDIA_EM_MT' => ['disc' => 'mat', 'etapa' => 'em'],
    ];

    /**
     * @param  array<string, true>|null  $allowedIbge
     * @return array{rows: int, municipios: int, path: string, warnings: list<string>}
     */
    public function spreadsheetToCanonicalCsv(string $spreadsheetPath, string $outputCsvPath, ?array $allowedIbge, ?int $yearHint = null): array
    {
        $warnings = [];
        $ext = strtolower(pathinfo($spreadsheetPath, PATHINFO_EXTENSION));
        $reader = match ($ext) {
            'xlsx' => IOFactory::createReader('Xlsx'),
            'xls' => IOFactory::createReader('Xls'),
            default => IOFactory::createReaderForFile($spreadsheetPath),
        };
        $reader->setReadDataOnly(true);
        // Planilhas nacionais têm várias abas (Brasil/Estados/Municípios + erros amostrais).
        // Carregar só a aba de municípios reduz drasticamente a memória (evita OOM no XLSX 2023).
        try {
            $names = $reader->listWorksheetNames($spreadsheetPath);
            $target = null;
            foreach ($names as $nm) {
                $trimmed = trim((string) $nm);
                if (in_array($trimmed, self::SHEET_MUNICIPIOS_NAMES, true) || stripos($trimmed, 'munic') !== false) {
                    $target = $nm;
                    break;
                }
            }
            if ($target !== null) {
                $reader->setLoadSheetsOnly($target);
            }
        } catch (\Throwable) {
            // Sem lista de abas: segue carregando o arquivo inteiro.
        }
        $spreadsheet = $reader->load($spreadsheetPath);
        $sheet = $this->resolveMunicipiosSheet($spreadsheet, $warnings);
        if ($sheet === null) {
            throw new \RuntimeException(__('Aba «Municípios» não encontrada na planilha.'));
        }

        $matrix = $sheet->toArray(null, true, true, false);
        if ($matrix === []) {
            throw new \RuntimeException(__('Planilha vazia.'));
        }

        [$headerIdx, $headers] = $this->locateHeaderRow($matrix);
        if ($headers === []) {
            throw new \RuntimeException(__('Cabeçalho com CO_MUNICIPIO não encontrado.'));
        }

        $colMap = $this->columnIndexMap($headers);
        if (! isset($colMap['CO_MUNICIPIO'])) {
            throw new \RuntimeException(__('Coluna CO_MUNICIPIO ausente.'));
        }

        $mediaCols = $this->detectMediaColumns($headers, $colMap);
        if ($mediaCols === []) {
            throw new \RuntimeException(__('Nenhuma coluna MEDIA_*_LP/MT encontrada.'));
        }

        $preferDep = (string) config('ieducar.saeb.planilha_prefer_dependencia', 'Municipal');
        $dataStart = $headerIdx + 1;
        if ($this->rowLooksLikeHumanLabels($matrix[$dataStart] ?? [], $colMap)) {
            $dataStart++;
        }

        $byIbge = [];
        for ($r = $dataStart, $max = count($matrix); $r < $max; $r++) {
            $row = $matrix[$r];
            if (! is_array($row) || $row === []) {
                continue;
            }
            $ibge = $this->normalizeIbge($this->cell($row, $colMap['CO_MUNICIPIO'] ?? null));
            if ($ibge === null || ($allowedIbge !== null && ! isset($allowedIbge[$ibge]))) {
                continue;
            }

            $rec = $this->rowAssoc($row, $colMap);
            if (! isset($byIbge[$ibge])) {
                $byIbge[$ibge] = [];
            }
            $byIbge[$ibge][] = $rec;
        }

        $out = fopen($outputCsvPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException(__('Não foi possível gravar o CSV canónico.'));
        }

        fwrite($out, "municipio_ibge;ano_aplicacao;disciplina;etapa;valor;status;inep_escola\n");

        $rowsWritten = 0;
        $municipios = 0;

        foreach ($byIbge as $ibge => $candidates) {
            $picked = $this->pickMunicipioRow($candidates, $preferDep, $colMap);
            if ($picked === null) {
                $warnings[] = __('IBGE :ibge: nenhuma linha com dependência/localização reconhecida.', ['ibge' => $ibge]);

                continue;
            }

            $year = $this->parseYear($picked, $colMap, $yearHint);
            if ($year === null) {
                $warnings[] = __('IBGE :ibge: ano inválido.', ['ibge' => $ibge]);

                continue;
            }

            $municipios++;
            foreach ($mediaCols as $header => $spec) {
                $val = $picked[$header] ?? null;
                if (! is_numeric($val)) {
                    continue;
                }
                $line = $ibge.';'.$year.';'.$spec['disc'].';'.$spec['etapa'].';'
                    .str_replace('.', ',', (string) round((float) $val, 6)).';final;'."\n";
                fwrite($out, $line);
                $rowsWritten++;
            }
        }

        fclose($out);

        return [
            'rows' => $rowsWritten,
            'municipios' => $municipios,
            'path' => $outputCsvPath,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  \PhpOffice\PhpSpreadsheet\Spreadsheet  $spreadsheet
     * @param  list<string>  $warnings
     */
    private function resolveMunicipiosSheet($spreadsheet, array &$warnings): ?Worksheet
    {
        foreach (self::SHEET_MUNICIPIOS_NAMES as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            if ($sheet !== null) {
                return $sheet;
            }
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = (string) $sheet->getTitle();
            if (stripos($title, 'munic') !== false) {
                $warnings[] = __('Aba «:name» usada como Municípios.', ['name' => $title]);

                return $sheet;
            }
        }

        return $spreadsheet->getSheet(0);
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     * @return array{0: int, 1: list<string>}
     */
    private function locateHeaderRow(array $matrix): array
    {
        $limit = min(8, count($matrix));
        for ($i = 0; $i < $limit; $i++) {
            $headers = $this->normalizeHeaders($matrix[$i] ?? []);
            foreach ($headers as $h) {
                if ($h === 'CO_MUNICIPIO') {
                    return [$i, $headers];
                }
            }
        }

        return [0, []];
    }

    /**
     * @param  list<mixed>  $row
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $cell) {
            $h = strtoupper(trim((string) $cell));
            $h = str_replace([' ', '-', '.'], '_', $h);
            $out[] = $h;
        }

        return $out;
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
     * @param  array<string, int>  $colMap
     * @return array<string, array{disc: string, etapa: string}>
     */
    private function detectMediaColumns(array $headers, array $colMap): array
    {
        $out = [];
        foreach ($headers as $h) {
            if (! isset($colMap[$h])) {
                continue;
            }
            if (isset(self::MEDIA_PATTERN[$h])) {
                $out[$h] = self::MEDIA_PATTERN[$h];

                continue;
            }
            if (preg_match('/^MEDIA_([A-Z0-9]+)_(LP|MT)$/', $h, $m) === 1) {
                $etapa = strtolower($m[1]);
                $disc = strtolower($m[2]) === 'MT' ? 'mat' : 'lp';
                $out[$h] = ['disc' => $disc, 'etapa' => $this->normalizeEtapaCode($etapa)];
            }
        }

        return $out;
    }

    private function normalizeEtapaCode(string $code): string
    {
        $code = strtolower($code);
        if (preg_match('/^5ef$/', $code)) {
            return 'efi';
        }
        if (preg_match('/^9ef$/', $code)) {
            return 'efaf';
        }
        if (preg_match('/^2ef$/', $code)) {
            return '2ef';
        }
        if (preg_match('/^emt|emi|em$/', $code)) {
            return 'em';
        }

        return $code;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $colMap
     */
    private function rowLooksLikeHumanLabels(array $row, array $colMap): bool
    {
        $anoIdx = $colMap['ANO_SAEB'] ?? null;
        if ($anoIdx === null) {
            return false;
        }
        $val = $row[$anoIdx] ?? null;

        return ! is_numeric($val);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, int>  $colMap
     * @return ?array<string, mixed>
     */
    private function pickMunicipioRow(array $candidates, string $preferDep, array $colMap): ?array
    {
        $depKey = 'DEPENDENCIA_ADM';
        $locKey = 'LOCALIZACAO';

        foreach ($candidates as $rec) {
            $dep = trim((string) ($rec[$depKey] ?? ''));
            $loc = trim((string) ($rec[$locKey] ?? ''));
            if ($dep === $preferDep && strcasecmp($loc, 'Total') === 0) {
                return $rec;
            }
        }

        foreach ($candidates as $rec) {
            $dep = trim((string) ($rec[$depKey] ?? ''));
            $loc = trim((string) ($rec[$locKey] ?? ''));
            if (str_contains($dep, 'Total - Federal') && strcasecmp($loc, 'Total') === 0) {
                return $rec;
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $colMap
     * @return array<string, mixed>
     */
    private function rowAssoc(array $row, array $colMap): array
    {
        $assoc = [];
        foreach ($colMap as $name => $idx) {
            $assoc[$name] = $this->cell($row, $idx);
        }

        return $assoc;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function cell(array $row, ?int $idx): mixed
    {
        if ($idx === null) {
            return null;
        }

        return $row[$idx] ?? null;
    }

    /**
     * @param  array<string, mixed>  $rec
     * @param  array<string, int>  $colMap
     */
    private function parseYear(array $rec, array $colMap, ?int $yearHint): ?int
    {
        $keys = ['ANO_SAEB', 'NU_ANO', 'ANO'];
        foreach ($keys as $key) {
            if (! isset($colMap[$key])) {
                continue;
            }
            $v = $rec[$key] ?? null;
            if (is_numeric($v)) {
                $y = (int) $v;

                return ($y >= 1995 && $y <= 2100) ? $y : null;
            }
        }

        return $yearHint;
    }

    private function normalizeIbge(mixed $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) < 7) {
            $d = str_pad($d, 7, '0', STR_PAD_LEFT);
        }

        return strlen($d) === 7 ? $d : null;
    }
}
