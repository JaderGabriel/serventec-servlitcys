<?php

namespace App\Services\Cadunico;

/**
 * Fatia «demanda × oferta» (INT-01) ligada à lacuna CadÚnico.
 */
final class CadunicoDemandaOfertaSlice
{
    /**
     * @param  array<string, mixed>  $gap
     * @param  array<string, mixed>  $territorial
     * @return array<string, mixed>
     */
    public static function build(array $gap, array $territorial): array
    {
        $gapTotal = (int) ($gap['gap_total'] ?? 0);
        $cad = (int) ($gap['cadunico_total_escolar'] ?? 0);
        $mat = (int) ($gap['ieducar_matriculas'] ?? 0);
        $cobertura = $gap['cobertura_pct'] ?? null;

        $top = [];
        foreach (is_array($territorial['ranking'] ?? null) ? $territorial['ranking'] : [] as $row) {
            $top[] = $row;
            if (count($top) >= 5) {
                break;
            }
        }

        return [
            'available' => ($gap['available'] ?? false) && $cad > 0,
            'demanda_potencial' => $gapTotal,
            'demanda_fmt' => $gap['gap_total_fmt'] ?? '—',
            'oferta_matriculas' => $mat,
            'oferta_alunos' => $gap['ieducar_alunos'] ?? null,
            'cobertura_pct' => $cobertura,
            'cobertura_label' => $gap['cobertura_label'] ?? '—',
            'territorios_prioritarios' => $top,
            'mensagem' => $gapTotal > 0
                ? __('Demanda indicativa (fora da rede): :d crianças/jovens. Oferta municipal: :m matrículas. Priorize territórios com maior pressão no mapa.', [
                    'd' => $gap['gap_total_fmt'] ?? '0',
                    'm' => number_format($mat, 0, ',', '.'),
                ])
                : __('Cobertura municipal alinhada ou superior ao agregado CadÚnico no recorte.'),
            'backlog_ref' => 'INT-01',
        ];
    }
}
