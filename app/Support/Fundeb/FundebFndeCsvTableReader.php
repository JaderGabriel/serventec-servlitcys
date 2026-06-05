<?php

namespace App\Support\Fundeb;

use Illuminate\Support\Str;

/**
 * Leitor tolerante a CSV FNDE com linhas de título e cabeçalhos multilinha (Portaria 6/2026).
 */
final class FundebFndeCsvTableReader
{
    /**
     * @return list<list<string>>
     */
    public static function rowsFromBody(string $body): array
    {
        $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'ISO-8859-1';
        if ($encoding !== 'UTF-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $encoding);
        }

        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $body);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $rows[] = array_map(static fn (mixed $c): string => trim((string) $c), $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{header_rows: list<list<string>>, data_start: int, columns: array<string, int>}
     */
    public static function locateTable(array $rows, array $columnMatchers): array
    {
        $dataStart = -1;
        foreach ($rows as $i => $row) {
            if (self::isDataRow($row)) {
                $dataStart = $i;
                break;
            }
        }

        if ($dataStart < 0) {
            return ['header_rows' => [], 'data_start' => -1, 'columns' => []];
        }

        $headerRows = [];
        for ($h = $dataStart - 1; $h >= 0; $h--) {
            $joined = Str::lower(Str::ascii(implode(' ', $rows[$h])));
            if ($joined === '' || ! self::rowLooksLikeHeader($joined)) {
                break;
            }
            array_unshift($headerRows, $rows[$h]);
        }

        $columns = self::mapColumns($headerRows, $columnMatchers);
        if (($columns['ibge'] ?? -1) < 0 && $dataStart > 0) {
            $columns = self::inferColumnsFromData($rows[$dataStart]);
        }

        return [
            'header_rows' => $headerRows,
            'data_start' => $dataStart,
            'columns' => $columns,
        ];
    }

    /**
     * @param  list<list<string>>  $headerRows
     * @param  array<string, list<string>>  $columnMatchers
     * @return array<string, int>
     */
    public static function mapColumns(array $headerRows, array $columnMatchers): array
    {
        $colCount = 0;
        foreach ($headerRows as $row) {
            $colCount = max($colCount, count($row));
        }

        $labels = [];
        for ($c = 0; $c < $colCount; $c++) {
            $parts = [];
            foreach ($headerRows as $row) {
                if (isset($row[$c]) && $row[$c] !== '') {
                    $parts[] = $row[$c];
                }
            }
            $labels[$c] = Str::lower(Str::ascii(implode(' ', $parts)));
        }

        $map = [];
        foreach ($columnMatchers as $key => $needles) {
            $map[$key] = -1;
            foreach ($labels as $idx => $label) {
                foreach ($needles as $needle) {
                    $needle = Str::lower(Str::ascii($needle));
                    if ($needle !== '' && str_contains($label, $needle)) {
                        $map[$key] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Layout típico Portaria 6/2026 — receita total por ente.
     *
     * @param  list<string>  $row
     * @return array<string, int>
     */
    public static function inferReceitaColumns(array $row): array
    {
        return [
            'uf' => 0,
            'ibge' => 1,
            'entidade' => 2,
            'vaaf' => 4,
            'vaat_compl' => 5,
            'vaar' => 6,
            'total' => count($row) > 8 ? 8 : max(0, count($row) - 1),
        ];
    }

    /**
     * Layout típico Portaria 6/2026 — VAAT por ente.
     *
     * @param  list<string>  $row
     * @return array<string, int>
     */
    public static function inferVaatColumns(array $row): array
    {
        return [
            'uf' => 0,
            'entidade' => 1,
            'ibge' => 2,
            'vaat' => 4,
            'vaat_compl' => 5,
        ];
    }

    /**
     * @param  list<string>  $row
     */
    public static function isDataRow(array $row): bool
    {
        if (count($row) < 3) {
            return false;
        }
        $uf = strtoupper(trim($row[0]));
        if (! preg_match('/^[A-Z]{2}$/', $uf)) {
            return false;
        }

        foreach ([1, 2] as $ibgeIdx) {
            $ibge = FundebIbgeMatcher::normalize($row[$ibgeIdx] ?? null);
            if ($ibge !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $row
     * @return array<string, int>
     */
    private static function inferColumnsFromData(array $row): array
    {
        $ibgeAt = 1;
        if (FundebIbgeMatcher::normalize($row[1] ?? null) === null
            && FundebIbgeMatcher::normalize($row[2] ?? null) !== null) {
            $ibgeAt = 2;
        }

        $cols = self::inferReceitaColumns($row);
        $cols['ibge'] = $ibgeAt;
        if ($ibgeAt === 2) {
            $cols['entidade'] = 1;
        }

        return $cols;
    }

    private static function rowLooksLikeHeader(string $joined): bool
    {
        return str_contains($joined, 'ibge')
            || str_contains($joined, 'entidade')
            || str_contains($joined, 'ente federado')
            || str_contains($joined, 'receita')
            || str_contains($joined, 'vaat')
            || str_contains($joined, 'complementacao');
    }
}
