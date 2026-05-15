<?php

namespace App\Support\Ieducar;

use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Sinais operacionais das abas Rede, Inclusão etc. integrados ao mapa de discrepâncias e ao Diagnóstico Geral.
 */
final class ConsultoriaOperationalSignals
{
    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @param  array<string, mixed>|null  $networkKpis
     * @return list<array<string, mixed>>
     */
    public static function append(array $dimensions, ?array $networkKpis, int $totalMat): array
    {
        $out = $dimensions;
        $existingIds = [];
        foreach ($dimensions as $d) {
            $existingIds[(string) ($d['id'] ?? '')] = true;
        }

        $rede = self::dimensionRedeVagasOciosas($networkKpis, $totalMat);
        if ($rede !== null && ! isset($existingIds[$rede['id']])) {
            $out[] = $rede;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $networkKpis
     * @return ?array<string, mixed>
     */
    private static function dimensionRedeVagasOciosas(?array $networkKpis, int $totalMat): ?array
    {
        if (! is_array($networkKpis)) {
            return null;
        }

        $vagas = (int) ($networkKpis['vagas_ociosas'] ?? 0);
        $taxa = $networkKpis['taxa_ociosidade_pct'] ?? null;
        $cap = (int) ($networkKpis['capacidade_total'] ?? 0);
        $mat = (int) ($networkKpis['matriculas'] ?? 0);

        if ($vagas <= 0 || $taxa === null) {
            return null;
        }

        $threshold = (float) config('ieducar.consultoria.rede_ociosidade_alerta_pct', 15.0);
        if ((float) $taxa < $threshold) {
            return null;
        }

        $id = 'rede_vagas_ociosas';
        $peso = (float) config('ieducar.discrepancies.peso_por_check.'.$id, 0.25);
        $vaa = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);
        $perda = round($vagas * $vaa * $peso, 2);

        return [
            'id' => $id,
            'title' => __('Rede — vagas ociosas elevadas'),
            'vaar_refs' => [__('Gestão da rede'), __('FUNDEB — eficiência da oferta')],
            'availability' => 'available',
            'has_issue' => true,
            'detected' => true,
            'total' => $vagas,
            'pct_rede' => $cap > 0 ? round(100.0 * $vagas / $cap, 1) : null,
            'perda_estimada_anual' => $perda,
            'ganho_potencial_anual' => $perda,
            'status' => (float) $taxa >= 30.0 ? 'danger' : 'warning',
            'unavailable_reason' => null,
            'severity' => (float) $taxa >= 30.0 ? 'danger' : 'warning',
            'operational_note' => __(
                'Capacidade nas turmas: :cap; matrículas: :mat; vagas ociosas: :v (:taxa%). Revise turnos, remanejamento e demanda na aba Rede e oferta.',
                [
                    'cap' => number_format($cap, 0, ',', '.'),
                    'mat' => number_format($mat, 0, ',', '.'),
                    'v' => number_format($vagas, 0, ',', '.'),
                    'taxa' => number_format((float) $taxa, 1, ',', '.'),
                ]
            ),
            'source_tab' => 'network',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function enrichChecksFromDimensions(array $dimensions, array $checks): array
    {
        $checkIds = [];
        foreach ($checks as $c) {
            $checkIds[(string) ($c['id'] ?? '')] = true;
        }

        $out = $checks;
        foreach ($dimensions as $d) {
            if (! ($d['has_issue'] ?? false)) {
                continue;
            }
            $id = (string) ($d['id'] ?? '');
            if ($id === '' || isset($checkIds[$id])) {
                continue;
            }
            if (($d['availability'] ?? '') !== 'available') {
                continue;
            }
            if (! str_starts_with($id, 'rede_')) {
                continue;
            }

            $total = (int) ($d['total'] ?? 0);
            $funding = DiscrepanciesFundingImpact::estimate($id, $total);
            $out[] = [
                'id' => $id,
                'title' => (string) ($d['title'] ?? ''),
                'explanation' => (string) ($d['operational_note'] ?? ''),
                'impact' => __('Ociosidade elevada reduz eficiência do uso dos recursos da rede e pode indicar desalinhamento entre oferta e demanda.'),
                'correction' => __('Analisar turnos, transporte, remanejamento de turmas e política de matrícula (aba Rede e oferta).'),
                'severity' => (string) ($d['severity'] ?? 'warning'),
                'status' => (string) ($d['status'] ?? 'warning'),
                'is_erro' => ($d['severity'] ?? '') === 'danger',
                'consultoria_prioridade' => ($d['severity'] ?? '') === 'danger' ? __('Erro crítico') : __('Atenção'),
                'vaar_refs' => is_array($d['vaar_refs'] ?? null) ? $d['vaar_refs'] : [],
                'total' => $total,
                'corrigivel' => $total,
                'pct_rede' => $d['pct_rede'] ?? null,
                'perda_estimada_anual' => $funding['perda_anual'],
                'ganho_potencial_anual' => $funding['ganho_potencial_anual'],
                'funding_formula' => $funding['formula'],
                'funding_explicacao' => $funding['explicacao'],
                'funding' => $funding,
                'school_rows' => [],
                'chart_rede' => null,
                'chart_escolas' => null,
                'chart_financeiro' => null,
            ];
            $checkIds[$id] = true;
        }

        return $out;
    }
}
