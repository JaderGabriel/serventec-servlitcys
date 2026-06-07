<?php

namespace App\Support\Rx;

/**
 * Indicador visual de cumprimento da meta de cadastro (por município no RX).
 */
final class RxSemaphore
{
    /**
     * @return array{status: string, label: string, title: string}
     */
    public static function fromRow(array $row): array
    {
        if (! ($row['ok'] ?? false)) {
            return [
                'status' => 'error',
                'label' => __('Erro'),
                'title' => (string) ($row['error'] ?? __('Falha na consulta')),
            ];
        }

        if (! ($row['meta_encontrou_referencia'] ?? false)) {
            return [
                'status' => 'neutral',
                'label' => __('Sem base'),
                'title' => __('Não há turmas nem matrículas nos anos anteriores consultados para definir meta.'),
            ];
        }

        $prog = $row['progresso_cadastro_pct'] ?? null;
        $rest = (int) ($row['registros_restantes'] ?? 0);
        $faltaTur = (int) ($row['falta_turmas'] ?? 0);
        $faltaMat = (int) ($row['falta_matriculas'] ?? 0);
        $metaMat = (int) ($row['meta_matriculas_alvo'] ?? 0);
        $metaTur = (int) ($row['meta_turmas_alvo'] ?? 0);
        $hasMeta = $metaMat > 0 || $metaTur > 0;

        $anoImediatoZerado = (bool) ($row['meta_ano_imediato_zerado'] ?? false);
        $saltos = (int) ($row['meta_saltos'] ?? 0);
        $refAno = (int) ($row['meta_referencia_ano'] ?? 0);
        $anteriorAno = (int) ($row['anterior_ano'] ?? 0);

        if ($hasMeta && ($prog !== null && (float) $prog >= 100.0 || $rest === 0)) {
            $title = $anoImediatoZerado && $saltos > 0 && $refAno > 0
                ? __('Meta atingida com base em :ref (+:n salto(s), +:pct% sobre o volume histórico). O ano :ant não tinha turmas nem matrículas — o alvo não é só repetir o vigente face a :ant.', [
                    'ref' => (string) $refAno,
                    'n' => $saltos,
                    'pct' => number_format((float) ($row['meta_acrescimo_pct'] ?? 0), 1, ',', '.'),
                    'ant' => $anteriorAno > 0 ? (string) $anteriorAno : __('anterior'),
                ])
                : __('Volume vigente atinge ou supera a meta de cadastro (turmas e matrículas com alvo definido).');

            return [
                'status' => 'green',
                'label' => $anoImediatoZerado && $saltos > 0
                    ? __('Meta OK (ref. :ano)', ['ano' => (string) $refAno])
                    : __('Meta OK'),
                'title' => $title,
            ];
        }

        $yellowMin = (float) config('rx.semaphore.yellow_min_progress', 75.0);
        $progF = $prog !== null ? (float) $prog : 0.0;

        if ($progF >= $yellowMin) {
            $title = __('Progresso :pct% em relação à meta; ainda há registos em falta.', ['pct' => number_format($progF, 1, ',', '.')]);
            if ($anoImediatoZerado && $saltos > 0 && $refAno > 0) {
                $title .= ' '.__('Referência em :ref (+:n salto(s)); :ant sem cadastro.', [
                    'ref' => (string) $refAno,
                    'n' => $saltos,
                    'ant' => $anteriorAno > 0 ? (string) $anteriorAno : __('ano anterior'),
                ]);
            }

            return [
                'status' => 'yellow',
                'label' => __('Em curso'),
                'title' => $title,
            ];
        }

        return [
            'status' => 'red',
            'label' => __('Atenção'),
            'title' => __('Abaixo da meta (:pct% concluído). Em falta: :tur turma(s), :mat matrícula(s).', [
                'pct' => $prog !== null ? number_format($progF, 1, ',', '.') : '0',
                'tur' => number_format($faltaTur, 0, ',', '.'),
                'mat' => number_format($faltaMat, 0, ',', '.'),
            ]),
        ];
    }
}
