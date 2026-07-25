<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Storage;

/** Lê o snapshot mensal de alertas Canteiro (só consultoria). */
final class HorizonteCanteiroAlertsCache
{
    /**
     * @return array{
     *   generated_at: ?string,
     *   simec_painel_url: string,
     *   by_ibge: array<string, array<string, mixed>>
     * }
     */
    public static function load(): array
    {
        $path = trim((string) config('horizonte.obras.alerts.snapshot_path', 'horizonte/canteiro_alerts_snapshot.json'));
        $empty = [
            'generated_at' => null,
            'simec_painel_url' => (string) config('horizonte.obras.alerts.simec_painel_url', 'https://simec.mec.gov.br/painelObras/'),
            'by_ibge' => [],
        ];

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return $empty;
        }

        try {
            $raw = Storage::disk('local')->get($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
        } catch (\Throwable) {
            return $empty;
        }

        if (! is_array($data)) {
            return $empty;
        }

        $snap = is_array($data['snapshot'] ?? null) ? $data['snapshot'] : [];
        $byIbge = [];
        foreach ($snap as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $ibge = preg_replace('/\D/', '', (string) ($entry['ibge'] ?? $key));
            if ($ibge === null || strlen($ibge) !== 7) {
                continue;
            }
            $byIbge[$ibge] = $entry;
        }

        return [
            'generated_at' => isset($data['generated_at']) ? (string) $data['generated_at'] : null,
            'simec_painel_url' => (string) ($data['simec_painel_url'] ?? $empty['simec_painel_url']),
            'by_ibge' => $byIbge,
        ];
    }
}
