<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

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
    public static function append(
        array $dimensions,
        ?array $networkKpis,
        int $totalMat,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
        ?int $fundebAnchorAno = null,
    ): array {
        $out = [];
        $existingIds = [];
        foreach ($dimensions as $d) {
            $id = (string) ($d['id'] ?? '');
            if ($id !== '') {
                $existingIds[$id] = true;
            }
            $out[] = self::normalizeDimension($d, $totalMat, $city, $filters);
        }

        $rede = self::dimensionRedeVagasOciosas($networkKpis, $totalMat, $city, $filters);
        if ($rede !== null && ! isset($existingIds[$rede['id']])) {
            $out[] = self::normalizeDimension($rede, $totalMat, $city, $filters);
            $existingIds[$rede['id']] = true;
        }

        foreach (FundebOperationalSignals::append([], $city, $filters, $fundebAnchorAno) as $signal) {
            $id = (string) ($signal['id'] ?? '');
            if ($id === '' || isset($existingIds[$id])) {
                continue;
            }
            $out[] = self::normalizeDimension($signal, $totalMat, $city, $filters);
            $existingIds[$id] = true;
        }

        foreach (CadunicoOperationalSignals::append([], $totalMat, $city, $filters, $fundebAnchorAno) as $signal) {
            $id = (string) ($signal['id'] ?? '');
            if ($id === '' || isset($existingIds[$id])) {
                continue;
            }
            $out[] = self::normalizeDimension($signal, $totalMat, $city, $filters);
            $existingIds[$id] = true;
        }

        return $out;
    }

    /**
     * Metadados de rotinas operacionais (sem query i-Educar dedicada).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function operationalMeta(): array
    {
        return [
            'rede_vagas_ociosas' => [
                'id' => 'rede_vagas_ociosas',
                'title' => __('Rede — vagas ociosas elevadas'),
                'explanation' => __('Capacidade declarada nas turmas excede matrículas de forma relevante no filtro.'),
                'impact' => __('Ociosidade elevada reduz eficiência do uso dos recursos da rede e pode indicar desalinhamento entre oferta e demanda.'),
                'correction' => __('Analisar turnos, transporte, remanejamento de turmas e política de matrícula (aba Rede e oferta).'),
                'severity' => 'warning',
                'vaar_refs' => [__('Gestão da rede'), __('FUNDEB — eficiência da oferta')],
            ],
            'fundeb_vaaf_fonte_censo' => [
                'id' => 'fundeb_vaaf_fonte_censo',
                'title' => __('FUNDEB — VAAF estimado com Censo INEP'),
                'explanation' => __('A referência FUNDEB importada usa matrículas do Censo INEP com i-Educar = 0 na sincronização.'),
                'impact' => __('Projeções de Finanças e o comparativo VAAF podem divergir das matrículas activas no painel municipal.'),
                'correction' => __('Reimporte a portaria com matrículas i-Educar alinhadas ou regularize o cadastro antes do Educacenso.'),
                'severity' => 'warning',
                'vaar_refs' => [__('Referência FUNDEB'), __('Matrículas i-Educar')],
            ],
            'fundeb_ibge_nome_divergente' => [
                'id' => 'fundeb_ibge_nome_divergente',
                'title' => __('FUNDEB — IBGE × nome oficial divergente'),
                'explanation' => __('O nome do município no cadastro local difere do nome oficial na portaria FNDE para o mesmo IBGE.'),
                'impact' => __('Risco de confusão em mapas, relatórios territoriais e cruzamentos com dados públicos.'),
                'correction' => __('Revise IBGE e nome no cadastro da cidade; confira geo-sync e importações territoriais.'),
                'severity' => 'warning',
                'vaar_refs' => [__('Território'), __('Portaria FNDE')],
            ],
            'cadunico_snapshot_ausente' => [
                'id' => 'cadunico_snapshot_ausente',
                'title' => __('CadÚnico — snapshot municipal ausente'),
                'explanation' => __('Agregados Cecad/SAGI do CadÚnico (4–17 anos) não gravados para o exercício analisado.'),
                'impact' => __('Sem CadÚnico não há estimativa de crianças fora da rede nem cenários de busca ativa na consultoria.'),
                'correction' => __('Sincronize em Admin → CadÚnico/Cecad ou execute `cadunico:sync-city` / `cadunico:auto-sync`.'),
                'severity' => 'warning',
                'vaar_refs' => [__('CadÚnico'), __('Busca ativa')],
            ],
            'cadunico_rede_lacuna' => [
                'id' => 'cadunico_rede_lacuna',
                'title' => __('CadÚnico — crianças fora da rede municipal'),
                'explanation' => __('Compara população escolar no CadÚnico com matrículas i-Educar no filtro; lacuna elevada sugere busca ativa.'),
                'impact' => __('Potencial subdimensionamento de matrículas e cenário indicativo de ganho FUNDEB se integradas à rede (não inclui estadual/privada).'),
                'correction' => __('Cruze faixas etárias na aba CadÚnico, valide NEE/AEE e política de matrícula antes do Censo.'),
                'severity' => 'warning',
                'vaar_refs' => [__('CadÚnico'), __('FUNDEB — matrícula')],
            ],
        ];
    }

    /**
     * Garante campos de dimensão completos (analyzed, schools_count, status_label…) para o hub modular.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeDimension(
        array $row,
        int $totalMat,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): array {
        if (! empty($row['analyzed']) && isset($row['status_label'], $row['schools_count'])) {
            return $row;
        }

        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            return $row;
        }

        $catalog = DiscrepanciesCheckCatalog::definitions();
        $meta = $catalog[$id] ?? self::operationalMeta()[$id] ?? [
            'id' => $id,
            'title' => (string) ($row['title'] ?? $id),
            'severity' => (string) ($row['severity'] ?? 'warning'),
            'vaar_refs' => is_array($row['vaar_refs'] ?? null) ? $row['vaar_refs'] : [],
        ];

        $hasIssue = (bool) ($row['has_issue'] ?? false);
        $availability = (string) ($row['availability'] ?? 'available');
        $total = (int) ($row['total'] ?? 0);

        $eval = [
            'availability' => $availability,
            'has_issue' => $hasIssue,
            'rows' => [],
            'unavailable_reason' => $row['unavailable_reason'] ?? null,
        ];

        if ($hasIssue) {
            $eval['rows'] = match ($id) {
                'fundeb_vaaf_fonte_censo' => [[
                    'escola_id' => 'fundeb',
                    'escola' => __('Referência FUNDEB'),
                    'total' => max(1, $total),
                ]],
                'fundeb_ibge_nome_divergente' => [[
                    'escola_id' => 'ibge',
                    'escola' => (string) ($city?->name ?? __('Município')),
                    'total' => 1,
                ]],
                'cadunico_snapshot_ausente' => [[
                    'escola_id' => 'cadunico',
                    'escola' => __('CadÚnico municipal'),
                    'total' => max(1, $total),
                ]],
                'cadunico_rede_lacuna' => [[
                    'escola_id' => 'cadunico',
                    'escola' => __('CadÚnico municipal'),
                    'total' => max(1, $total),
                ]],
                default => [[
                    'escola_id' => '0',
                    'escola' => (string) ($row['title'] ?? $meta['title'] ?? $id),
                    'total' => max(1, $total),
                ]],
            };
        }

        if ($city === null || $filters === null) {
            return array_merge($row, [
                'analyzed' => $availability === 'available' || $hasIssue,
                'schools_count' => $hasIssue ? 1 : 0,
                'occurrences_total' => $hasIssue ? max(1, $total) : 0,
            ]);
        }

        $dim = DiscrepanciesRoutineMetrics::dimensionFromEval($id, $meta, $eval, $totalMat, $city, $filters);

        return array_merge($dim, array_filter([
            'operational_note' => $row['operational_note'] ?? $dim['operational_note'] ?? null,
            'source_tab' => $row['source_tab'] ?? null,
            'correction_tab' => $row['correction_tab'] ?? $dim['correction_tab'] ?? null,
            'correction_label' => $row['correction_label'] ?? $dim['correction_label'] ?? null,
            'explanation' => $row['explanation'] ?? ($meta['explanation'] ?? ''),
            'impact' => $row['impact'] ?? ($meta['impact'] ?? ''),
            'correction' => $row['correction'] ?? ($meta['correction'] ?? ''),
            'pct_rede' => $row['pct_rede'] ?? $dim['pct_rede'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @param  array<string, mixed>|null  $networkKpis
     * @return ?array<string, mixed>
     */
    private static function dimensionRedeVagasOciosas(
        ?array $networkKpis,
        int $totalMat,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): ?array {
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
        $vaa = DiscrepanciesFundingImpact::vaaReferencia($city, $filters);
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
            'correction_tab' => 'network',
            'correction_label' => __('Ver Rede'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function enrichChecksFromDimensions(
        array $dimensions,
        array $checks,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): array {
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

            $catalog = DiscrepanciesCheckCatalog::definitions();
            $meta = $catalog[$id] ?? [];
            $occurrences = (int) ($d['occurrences_total'] ?? $d['total'] ?? 0);
            $schoolsCount = (int) ($d['schools_count'] ?? $d['row_count'] ?? 0);
            $impactUnits = $id === 'escola_sem_geo'
                ? max(1, $schoolsCount)
                : ($occurrences > 0 ? $occurrences : max(1, $schoolsCount));
            $funding = DiscrepanciesFundingImpact::estimate($id, $impactUnits, $city, $filters);

            $defaultImpact = str_starts_with($id, 'rede_')
                ? __('Ociosidade elevada reduz eficiência do uso dos recursos da rede e pode indicar desalinhamento entre oferta e demanda.')
                : (string) ($meta['impact'] ?? '');
            $defaultCorrection = str_starts_with($id, 'rede_')
                ? __('Analisar turnos, transporte, remanejamento de turmas e política de matrícula (aba Rede e oferta).')
                : (string) ($meta['correction'] ?? $d['correction_label'] ?? '');

            $out[] = [
                'id' => $id,
                'title' => (string) ($d['title'] ?? $meta['title'] ?? ''),
                'explanation' => (string) ($d['operational_note'] ?? $meta['explanation'] ?? ''),
                'impact' => $defaultImpact,
                'correction' => $defaultCorrection,
                'severity' => (string) ($d['severity'] ?? $meta['severity'] ?? 'warning'),
                'status' => (string) ($d['status'] ?? 'warning'),
                'is_erro' => ($d['severity'] ?? $meta['severity'] ?? '') === 'danger',
                'consultoria_prioridade' => ($d['severity'] ?? $meta['severity'] ?? '') === 'danger' ? __('Erro crítico') : __('Atenção'),
                'vaar_refs' => is_array($d['vaar_refs'] ?? null) ? $d['vaar_refs'] : (is_array($meta['vaar_refs'] ?? null) ? $meta['vaar_refs'] : []),
                'total' => $id === 'escola_sem_geo' ? $occurrences : $occurrences,
                'corrigivel' => $id === 'escola_sem_geo' ? $schoolsCount : $occurrences,
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
