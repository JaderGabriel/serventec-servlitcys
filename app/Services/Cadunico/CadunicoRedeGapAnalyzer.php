<?php

namespace App\Services\Cadunico;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Estima crianças/jovens em idade escolar no CadÚnico não refletidos como matrículas na rede i-Educar.
 */
final class CadunicoRedeGapAnalyzer
{
    /**
     * @param  list<array{etapa: string, matriculas: int}>  $ieducarPorEtapa
     * @return array<string, mixed>
     */
    public function analyze(
        City $city,
        IeducarFilterState $filters,
        int $matriculasIeducar,
        int $alunosIeducar,
        array $ieducarPorEtapa,
        ?CadunicoMunicipioSnapshot $cadunico,
        ?int $censoMatriculas,
        float $vaaf,
    ): array {
        $cfg = config('ieducar.cadunico', []);
        $coberturaAlerta = (float) ($cfg['cobertura_alerta_pct'] ?? 92.0);

        if ($cadunico === null) {
            return [
                'available' => false,
                'cadunico_total_escolar' => null,
                'ieducar_matriculas' => $matriculasIeducar,
                'gap_total' => null,
                'cobertura_pct' => null,
                'status' => 'missing_cadunico',
                'status_label' => __('Sem dados CadÚnico importados'),
                'por_faixa' => [],
                'por_etapa' => [],
                'nota' => __('Sincronize CadÚnico em Admin → CadÚnico/Cecad (API → cache → CSV) ou `cadunico:sync-city`.'),
            ];
        }

        $cadTotal = $cadunico->totalCriancasEscolaridade();
        $gapTotal = $cadTotal > 0 && $matriculasIeducar >= 0
            ? max(0, $cadTotal - $matriculasIeducar)
            : null;

        $cobertura = ($cadTotal > 0 && $matriculasIeducar >= 0)
            ? round(min(100.0, 100.0 * $matriculasIeducar / $cadTotal), 1)
            : null;

        $status = match (true) {
            $gapTotal === null || $cadTotal <= 0 => 'neutral',
            $cobertura !== null && $cobertura >= $coberturaAlerta => 'success',
            $gapTotal > 0 => 'warning',
            default => 'neutral',
        };

        $porFaixa = $this->faixasCadunico($cadunico);
        $porEtapa = $this->distribuirGapPorEtapa($porFaixa, $ieducarPorEtapa, $matriculasIeducar, $cadTotal, $gapTotal ?? 0, $vaaf);

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $impactoGap = ($gapTotal !== null && $gapTotal > 0 && $vaaf > 0)
            ? MoneyMath::multiplyVaaf($gapTotal, $vaaf)
            : null;

        $censoGap = null;
        if ($censoMatriculas !== null && $censoMatriculas > 0 && $matriculasIeducar > 0) {
            $censoGap = max(0, $censoMatriculas - $matriculasIeducar);
        }

        return [
            'available' => true,
            'cadunico_ano' => (int) $cadunico->ano_referencia,
            'cadunico_total_escolar' => $cadTotal,
            'cadunico_fonte' => (string) ($cadunico->fonte ?? ''),
            'cadunico_imported_at' => $cadunico->imported_at?->format('d/m/Y H:i'),
            'ieducar_matriculas' => $matriculasIeducar,
            'ieducar_alunos' => $alunosIeducar,
            'censo_matriculas' => $censoMatriculas,
            'censo_gap' => $censoGap,
            'gap_total' => $gapTotal,
            'gap_total_fmt' => $gapTotal !== null ? number_format($gapTotal, 0, ',', '.') : '—',
            'cobertura_pct' => $cobertura,
            'cobertura_label' => $cobertura !== null ? number_format($cobertura, 1, ',', '.').'%' : '—',
            'status' => $status,
            'status_label' => match ($status) {
                'success' => __('Cobertura elevada face ao CadÚnico'),
                'warning' => __('Potencial fora da rede municipal'),
                default => __('Leitura indicativa'),
            },
            'por_faixa' => $porFaixa,
            'por_etapa' => $porEtapa,
            'impacto_financeiro' => [
                'vaaf' => $vaaf,
                'vaaf_label' => $vaaf > 0 ? $fmt($vaaf) : '—',
                'gap_anual' => $impactoGap,
                'gap_anual_label' => $impactoGap !== null ? $fmt($impactoGap) : '—',
                'formula' => ($gapTotal !== null && $gapTotal > 0 && $vaaf > 0)
                    ? __(':n crianças × :vaaf ≈ :total/ano (FUNDEB indicativo, matrículas adicionais se integradas à rede).', [
                        'n' => number_format($gapTotal, 0, ',', '.'),
                        'vaaf' => $fmt($vaaf),
                        'total' => $fmt($impactoGap),
                    ])
                    : null,
            ],
            'nota' => __(
                'CadÚnico mede famílias em vulnerabilidade no município; nem toda criança cadastrada deveria estar na rede municipal (escolas estaduais, privadas, EJA). Use como busca ativa e planeamento, não como meta automática de matrícula.'
            ),
        ];
    }

    /**
     * @return list<array{faixa: string, key: string, cadunico: int}>
     */
    private function faixasCadunico(CadunicoMunicipioSnapshot $snap): array
    {
        $faixas = config('ieducar.cadunico.faixas_etarias', []);
        if (! is_array($faixas)) {
            return [];
        }

        $out = [];
        foreach ($faixas as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $key = (string) ($faixa['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[] = [
                'faixa' => (string) ($faixa['label'] ?? $key),
                'key' => $key,
                'cadunico' => (int) ($snap->{$key} ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{faixa: string, key: string, cadunico: int}>  $porFaixa
     * @param  list<array{etapa: string, matriculas: int}>  $ieducarPorEtapa
     * @return list<array<string, mixed>>
     */
    private function distribuirGapPorEtapa(
        array $porFaixa,
        array $ieducarPorEtapa,
        int $matTotal,
        int $cadTotal,
        int $gapTotal,
        float $vaaf,
    ): array {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $rows = [];

        $prelim = [];
        foreach ($ieducarPorEtapa as $etapaRow) {
            $etapa = (string) ($etapaRow['etapa'] ?? '');
            $mat = (int) ($etapaRow['matriculas'] ?? 0);
            $cadEst = $this->matchCadunicoForEtapa($etapa, $porFaixa, $cadTotal, $matTotal);
            if ($cadEst <= 0 && $cadTotal > 0 && $matTotal > 0 && $mat > 0) {
                $cadEst = (int) round($cadTotal * ($mat / $matTotal));
            }
            $prelim[] = ['etapa' => $etapa, 'mat' => $mat, 'cad_est' => $cadEst];
        }

        foreach ($prelim as $item) {
            $etapa = $item['etapa'];
            $mat = $item['mat'];
            $cadEst = $item['cad_est'];
            $gap = max(0, $cadEst - $mat);
            $fundebGap = $vaaf > 0 ? MoneyMath::multiplyVaaf($gap, $vaaf) : 0.0;

            $rows[] = [
                'etapa' => $etapa,
                'cadunico_estimado' => $cadEst,
                'ieducar_matriculas' => $mat,
                'gap' => $gap,
                'gap_fmt' => $gap > 0 ? number_format($gap, 0, ',', '.') : '0',
                'fundeb_gap_label' => $fundebGap > 0 ? $fmt($fundebGap) : '—',
                'tone' => $gap > 0 ? 'amber' : 'emerald',
            ];
        }

        if ($rows === [] && $gapTotal > 0) {
            $rows[] = [
                'etapa' => __('Rede municipal (total)'),
                'cadunico_estimado' => null,
                'ieducar_matriculas' => $matTotal,
                'gap' => $gapTotal,
                'gap_fmt' => number_format($gapTotal, 0, ',', '.'),
                'fundeb_gap_label' => $vaaf > 0 ? $fmt(MoneyMath::multiplyVaaf($gapTotal, $vaaf)) : '—',
                'tone' => 'amber',
            ];
        }

        usort($rows, static fn ($a, $b) => ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0));

        return $rows;
    }

    /**
     * @param  list<array{faixa: string, key: string, cadunico: int}>  $porFaixa
     */
    private function matchCadunicoForEtapa(string $etapa, array $porFaixa, int $cadTotal, int $matTotal): int
    {
        $etapaLower = mb_strtolower($etapa);
        $faixasCfg = config('ieducar.cadunico.faixas_etarias', []);
        if (! is_array($faixasCfg)) {
            $faixasCfg = [];
        }

        foreach ($faixasCfg as $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $keywords = $cfg['etapa_keywords'] ?? [];
            if (! is_array($keywords)) {
                continue;
            }
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($etapaLower, mb_strtolower((string) $kw))) {
                    $key = (string) ($cfg['key'] ?? '');
                    foreach ($porFaixa as $f) {
                        if (($f['key'] ?? '') === $key) {
                            return (int) ($f['cadunico'] ?? 0);
                        }
                    }
                }
            }
        }

        return 0;
    }
}
