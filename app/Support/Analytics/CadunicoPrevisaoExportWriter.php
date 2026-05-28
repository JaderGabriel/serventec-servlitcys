<?php

namespace App\Support\Analytics;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class CadunicoPrevisaoExportWriter
{
    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function streamCsv(array $rows): void
    {
        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, CadunicoPrevisaoExportRowsBuilder::columnLabels(), ';');
        foreach ($rows as $row) {
            fputcsv($out, self::rowToLine($row), ';');
        }
        fclose($out);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function writeXlsx(string $absolutePath, array $rows): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle(__('CadÚnico'));

        $headers = CadunicoPrevisaoExportRowsBuilder::columnLabels();
        foreach ($headers as $col => $header) {
            $cell = self::columnLetter($col + 1).'1';
            $active->setCellValue($cell, $header);
            $active->getStyle($cell)->getFont()->setBold(true);
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            foreach (self::rowToLine($row) as $col => $value) {
                $active->setCellValue(self::columnLetter($col + 1).$rowNum, $value);
            }
            $rowNum++;
        }

        foreach (range(1, count($headers)) as $col) {
            $active->getColumnDimension(self::columnLetter($col))->setAutoSize(true);
        }

        (new Xlsx($sheet))->save($absolutePath);
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private static function rowToLine(array $row): array
    {
        $line = [];
        foreach (CadunicoPrevisaoExportRowsBuilder::columnHeaders() as $key) {
            $line[] = (string) ($row[$key] ?? '');
        }

        return $line;
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
