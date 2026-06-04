<?php

namespace App\Services\Cadunico;

use App\Models\CadunicoMunicipioSnapshot;

/**
 * Indicadores agregados de vulnerabilidade a partir do snapshot municipal (Misocial/Cecad).
 */
final class CadunicoVulnerabilidadeIndicators
{
    /**
     * @return array<string, mixed>
     */
    public static function fromSnapshot(?CadunicoMunicipioSnapshot $snap): array
    {
        if ($snap === null) {
            return ['available' => false];
        }

        $meta = is_array($snap->metadados) ? $snap->metadados : [];
        $vuln = is_array($meta['vulnerabilidade'] ?? null) ? $meta['vulnerabilidade'] : [];

        if ($vuln !== []) {
            return array_merge(['available' => true], $vuln);
        }

        $escolar = $snap->totalCriancasEscolaridade();
        $pbf = self::estimateCriancasPbfFromBands($snap);
        $pctPbf = ($escolar > 0 && $pbf > 0) ? round(100.0 * $pbf / $escolar, 1) : null;

        return [
            'available' => true,
            'pessoas_cadastradas' => (int) $snap->pessoas_cadastradas,
            'familias_cadastradas' => (int) $snap->familias_cadastradas,
            'criancas_escolar_cadunico' => $escolar,
            'criancas_pbf_estimada' => $pbf > 0 ? $pbf : null,
            'pct_criancas_pbf' => $pctPbf,
            'pct_criancas_pbf_label' => $pctPbf !== null
                ? number_format($pctPbf, 1, ',', '.').'%'
                : null,
            'fonte' => (string) ($snap->fonte ?? ''),
        ];
    }

    private static function estimateCriancasPbfFromBands(CadunicoMunicipioSnapshot $snap): int
    {
        $meta = is_array($snap->metadados) ? $snap->metadados : [];
        $raw = $meta['misocial_pbf_criancas'] ?? null;
        if (is_numeric($raw) && (int) $raw > 0) {
            return (int) $raw;
        }

        return 0;
    }
}
