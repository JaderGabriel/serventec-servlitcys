<?php

namespace App\Support\Horizonte;

use App\Models\InepCensoMunicipioMatricula;

/** Indicadores Educacenso derivados para o modal Horizonte. */
final class HorizonteCensoIndicators
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRow(?InepCensoMunicipioMatricula $row): array
    {
        if ($row === null) {
            return ['available' => false];
        }

        $total = max(0, (int) $row->matriculas_total);
        $municipal = max(0, (int) ($row->matriculas_municipal ?? 0));
        $naoMunicipal = max(0, (int) ($row->matriculas_nao_municipal ?? 0));
        if ($municipal <= 0 && $naoMunicipal <= 0 && $total > 0) {
            $municipal = $total;
        }

        $complementar = max(0, (int) ($row->matriculas_complementar ?? 0));
        $profissional = max(0, (int) ($row->matriculas_profissional ?? 0));
        $docentesTotal = max(0, (int) ($row->docentes_total ?? 0));
        $docentesMunicipal = max(0, (int) ($row->docentes_municipal ?? 0));
        $docentesNaoMunicipal = max(0, (int) ($row->docentes_nao_municipal ?? 0));

        $pctMunicipal = $total > 0 ? round(100.0 * $municipal / $total, 1) : null;
        $pctIntegral = $total > 0 ? round(100.0 * $complementar / $total, 1) : null;
        $pctProfissional = $total > 0 ? round(100.0 * $profissional / $total, 1) : null;
        $alunoDocenteTotal = ($docentesTotal > 0 && $total > 0) ? round($total / $docentesTotal, 1) : null;
        $alunoDocenteMunicipal = ($docentesMunicipal > 0 && $municipal > 0) ? round($municipal / $docentesMunicipal, 1) : null;

        return [
            'available' => true,
            'ano' => (int) $row->ano,
            'matriculas_total' => $total,
            'matriculas_municipal' => $municipal,
            'matriculas_nao_municipal' => $naoMunicipal,
            'pct_municipal' => $pctMunicipal,
            'pct_integral' => $pctIntegral,
            'pct_profissional' => $pctProfissional,
            'aluno_docente_total' => $alunoDocenteTotal,
            'aluno_docente_municipal' => $alunoDocenteMunicipal,
            'escolas_contagem' => max(0, (int) ($row->escolas_contagem ?? 0)),
            'dependency_label' => self::dependencyLabel($pctMunicipal),
        ];
    }

    /**
     * @param  list<array{ano: int, matriculas_total: int}>  $series
     */
    public static function enrollmentMomentum(array $series): array
    {
        if (count($series) < 2) {
            return [
                'trend' => HorizonteSaebTrend::TREND_UNKNOWN,
                'trend_label' => __('Sem série'),
                'delta_pct' => null,
                'momentum_score' => 35,
            ];
        }

        usort($series, static fn (array $a, array $b): int => ($b['ano'] <=> $a['ano']));
        $newest = (int) ($series[0]['matriculas_total'] ?? 0);
        $oldest = (int) ($series[count($series) - 1]['matriculas_total'] ?? 0);
        if ($oldest <= 0) {
            return [
                'trend' => HorizonteSaebTrend::TREND_UNKNOWN,
                'trend_label' => __('Sem série'),
                'delta_pct' => null,
                'momentum_score' => 35,
            ];
        }

        $deltaPct = round(100.0 * ($newest - $oldest) / $oldest, 2);
        $trend = HorizonteSaebTrend::TREND_STABLE;
        if ($deltaPct <= -5) {
            $trend = HorizonteSaebTrend::TREND_DOWN;
        } elseif ($deltaPct >= 5) {
            $trend = HorizonteSaebTrend::TREND_UP;
        }

        $score = match ($trend) {
            HorizonteSaebTrend::TREND_DOWN => min(100, 55 + (int) round(min(40, abs($deltaPct)))),
            HorizonteSaebTrend::TREND_UP => max(10, 35 - (int) round(min(20, $deltaPct / 2))),
            default => 40,
        };

        return [
            'trend' => $trend,
            'trend_label' => HorizonteSaebTrend::label($trend),
            'delta_pct' => $deltaPct,
            'momentum_score' => max(0, min(100, $score)),
        ];
    }

    private static function dependencyLabel(?float $pctMunicipal): string
    {
        if ($pctMunicipal === null) {
            return __('Indeterminada');
        }

        if ($pctMunicipal >= 70) {
            return __('Predominantemente municipal');
        }
        if ($pctMunicipal >= 40) {
            return __('Misto municipal / estadual');
        }

        return __('Predominantemente estadual/privada');
    }
}
