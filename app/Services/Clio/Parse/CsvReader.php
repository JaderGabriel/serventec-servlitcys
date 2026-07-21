<?php

namespace App\Services\Clio\Parse;

use InvalidArgumentException;
use RuntimeException;

/**
 * Leitor CSV Educacenso portal — separador `;`, UTF-8 com BOM.
 */
final class CsvReader
{
    public const DELIMITER = ';';

    /**
     * @return array{
     *   headers: list<string>,
     *   rows: list<array<string, string>>,
     *   header_offset: int,
     *   encoding: string,
     *   delimiter: string
     * }
     */
    public function read(string $absolutePath, int $headerOffset = 1): array
    {
        if ($headerOffset < 1) {
            throw new InvalidArgumentException('headerOffset must be >= 1 (1-based line of header).');
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException(__('CSV Clio não legível: :path', ['path' => $absolutePath]));
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(__('Não foi possível abrir CSV Clio.'));
        }

        try {
            $headers = null;
            $lineIndex = 0;

            while (($raw = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                $lineIndex++;
                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                if ($lineIndex < $headerOffset) {
                    continue;
                }

                if ($headers === null) {
                    $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $raw);
                    // Align header offset to first non-empty line if we skipped empties awkwardly
                    break;
                }
            }

            if ($headers === null || $headers === []) {
                throw new RuntimeException(__('CSV Clio sem cabeçalho (EDU-REL-HEADER).'));
            }

            $rows = [];
            while (($raw = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $assoc[$header] = isset($raw[$i]) ? $this->normalizeCell((string) $raw[$i]) : '';
                }
                $rows[] = $assoc;
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'header_offset' => $headerOffset,
                'encoding' => 'UTF-8',
                'delimiter' => self::DELIMITER,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $required
     * @return list<string> missing headers
     */
    public function missingHeaders(array $headers, array $required): array
    {
        $normalized = array_map(fn (string $h) => mb_strtolower(trim($h)), $headers);
        $missing = [];
        foreach ($required as $need) {
            $needle = mb_strtolower(trim($need));
            if (! in_array($needle, $normalized, true)) {
                $missing[] = $need;
            }
        }

        return $missing;
    }

    /**
     * Resolve valor por nome de coluna (case-insensitive, trim).
     *
     * @param  array<string, string>  $row
     */
    public function value(array $row, string $column): string
    {
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        $needle = mb_strtolower(trim($column));
        foreach ($row as $key => $value) {
            if (mb_strtolower(trim((string) $key)) === $needle) {
                return $value;
            }
        }

        return '';
    }

    private function normalizeHeader(string $header): string
    {
        $header = $this->stripBom($header);

        return trim($header);
    }

    private function normalizeCell(string $cell): string
    {
        return trim($this->stripBom($cell));
    }

    private function stripBom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    /**
     * @param  list<null|string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($this->stripBom((string) $cell)) !== '') {
                return false;
            }
        }

        return true;
    }
}
