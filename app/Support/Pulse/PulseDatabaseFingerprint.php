<?php

namespace App\Support\Pulse;

/**
 * Normaliza SQL para agrupar padrões lentos no Pulse (sem literais sensíveis).
 */
final class PulseDatabaseFingerprint
{
    /**
     * @return array{fingerprint: string, label: string}
     */
    public static function fromSql(string $sql): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($sql)) ?? trim($sql);
        $normalized = preg_replace("/'(?:''|[^'])*'/u", '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d+\b/u', '?', $normalized) ?? $normalized;
        $normalized = mb_strtolower($normalized);

        $fingerprint = hash('xxh128', $normalized);
        $label = mb_strlen($normalized) > 96
            ? mb_substr($normalized, 0, 93).'…'
            : $normalized;

        return [
            'fingerprint' => $fingerprint,
            'label' => $label,
        ];
    }
}
