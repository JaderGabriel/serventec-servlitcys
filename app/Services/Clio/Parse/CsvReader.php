<?php

namespace App\Services\Clio\Parse;

use InvalidArgumentException;
use RuntimeException;

/**
 * Leitor CSV Educacenso portal — separador `;`.
 * Aceita UTF-8 (com BOM) e Latin-1 / Windows-1252; células saem em UTF-8 válido.
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
            $sawNonUtf8 = false;

            while (($raw = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                $lineIndex++;
                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                if ($lineIndex < $headerOffset) {
                    continue;
                }

                if ($headers === null) {
                    $headers = [];
                    foreach ($raw as $h) {
                        $cell = (string) $h;
                        if (! mb_check_encoding($cell, 'UTF-8')) {
                            $sawNonUtf8 = true;
                        }
                        $headers[] = $this->normalizeHeader($cell);
                    }
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
                    $cell = isset($raw[$i]) ? (string) $raw[$i] : '';
                    if ($cell !== '' && ! mb_check_encoding($cell, 'UTF-8')) {
                        $sawNonUtf8 = true;
                    }
                    $assoc[$header] = $this->normalizeCell($cell);
                }
                $rows[] = $assoc;
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'header_offset' => $headerOffset,
                'encoding' => $sawNonUtf8 ? 'legacy-to-utf8' : 'UTF-8',
                'delimiter' => self::DELIMITER,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Normaliza bytes legados (portal Educacenso) para UTF-8 válido para JSON/Eloquent.
     */
    public function toUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $from) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $from);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $fromIconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($fromIconv) && $fromIconv !== '') {
            return $fromIconv;
        }

        return mb_scrub($value, 'UTF-8');
    }

    /**
     * Garante árvore JSON-serializável (UTF-8) — útil ao fundir parse_meta legado.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function deepUtf8(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->toUtf8($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $this->toUtf8($k) : $k;
                $out[$key] = $this->deepUtf8($v);
            }

            return $out;
        }

        return $value;
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
        return trim($this->toUtf8($this->stripBom($header)));
    }

    private function normalizeCell(string $cell): string
    {
        return trim($this->toUtf8($this->stripBom($cell)));
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
