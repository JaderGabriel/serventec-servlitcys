<?php

namespace App\Support\Rx;

/**
 * Semáforo de cumprimento da meta de cadastro (por município no RX).
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
        $metaMat = (int) ($row['meta_matriculas_alvo'] ?? 0);

        if ($metaMat > 0 && ($prog !== null && (float) $prog >= 100.0 || $rest === 0)) {
            return [
                'status' => 'green',
                'label' => __('Meta OK'),
                'title' => __('Volume vigente atinge ou supera a meta de cadastro (turmas, matrículas e enturmações).'),
            ];
        }

        $yellowMin = (float) config('rx.semaphore.yellow_min_progress', 75.0);
        $progF = $prog !== null ? (float) $prog : 0.0;

        if ($progF >= $yellowMin) {
            return [
                'status' => 'yellow',
                'label' => __('Em curso'),
                'title' => __('Progresso :pct% em relação à meta; ainda há registos em falta.', ['pct' => number_format($progF, 1, ',', '.')]),
            ];
        }

        return [
            'status' => 'red',
            'label' => __('Atenção'),
            'title' => __('Abaixo da meta de cadastro (:pct% concluído; :rest registos em falta).', [
                'pct' => $prog !== null ? number_format($progF, 1, ',', '.') : '0',
                'rest' => number_format($rest, 0, ',', '.'),
            ]),
        ];
    }
}
