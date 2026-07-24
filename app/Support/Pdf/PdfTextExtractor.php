<?php

namespace App\Support\Pdf;

use App\Support\Filesystem\AppTemp;
use Illuminate\Support\Facades\Process;

/**
 * Extrai texto de PDF (pdftotext quando disponível; fallback heurístico em streams).
 */
final class PdfTextExtractor
{
    public static function fromBinary(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        try {
            $tmp = AppTemp::tempnam('sl_pdf_', 'pdf');
        } catch (\Throwable) {
            return self::extractFromStreams($binary);
        }

        try {
            file_put_contents($tmp, $binary);

            return self::fromPath($tmp) ?: self::extractFromStreams($binary);
        } finally {
            @unlink($tmp);
        }
    }

    public static function fromPath(string $pdfPath): string
    {
        if (! is_readable($pdfPath)) {
            return '';
        }

        if (self::pdftotextAvailable()) {
            try {
                $out = AppTemp::tempnam('sl_pdf_txt_', 'pdf');
            } catch (\Throwable) {
                $out = false;
            }
            if ($out !== false) {
                try {
                    $result = Process::run(['pdftotext', '-layout', $pdfPath, $out]);
                    if ($result->successful() && is_readable($out)) {
                        $text = file_get_contents($out);
                        if (is_string($text) && trim($text) !== '') {
                            return $text;
                        }
                    }
                } finally {
                    @unlink($out);
                }
            }

            $stdout = Process::run(['pdftotext', '-layout', $pdfPath, '-']);
            if ($stdout->successful() && trim($stdout->output()) !== '') {
                return $stdout->output();
            }
        }

        $binary = file_get_contents($pdfPath);

        return is_string($binary) ? self::extractFromStreams($binary) : '';
    }

    private static function pdftotextAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        $which = Process::run(['which', 'pdftotext']);
        $available = $which->successful() && trim($which->output()) !== '';

        return $available;
    }

    private static function extractFromStreams(string $binary): string
    {
        if (preg_match_all('/\d{7}\s+Inobservância[\x09\x20-\x7E\xC0-\xFF]+/u', $binary, $matches)) {
            return implode("\n", $matches[0]);
        }

        if (preg_match_all('/\(([^()]{8,200})\)/', $binary, $parts)) {
            $chunks = array_filter($parts[1], static fn (string $chunk): bool => preg_match('/\d{7}/', $chunk) === 1);
            if ($chunks !== []) {
                return implode("\n", $chunks);
            }
        }

        return '';
    }
}
