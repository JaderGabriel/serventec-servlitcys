<?php

namespace App\Support\Analytics;

/**
 * Secção CadÚnico no PDF da consultoria — tabelas apenas (sem mapa territorial).
 */
final class AnalyticsReportCadunicoSection
{
    /**
     * @param  array<string, mixed>  $report  Payload de CadunicoPrevisaoRepository::buildReport
     * @return array{
     *   available: bool,
     *   kpis: list<array{label: string, value: string}>,
     *   tables: list<array{title: string, headers: list<string>, rows: list<list<string>>}>,
     *   notes: list<string>
     * }
     */
    public static function scopeFromReport(array $report): array
    {
        if (! ($report['available'] ?? false)) {
            return [
                'available' => false,
                'kpis' => [],
                'tables' => [],
                'notes' => [],
            ];
        }

        $gap = is_array($report['gap'] ?? null) ? $report['gap'] : [];
        $territorial = is_array($report['territorial'] ?? null) ? $report['territorial'] : [];
        $kpis = [];
        $tables = [];
        $notes = [];

        foreach (is_array($report['kpis'] ?? null) ? $report['kpis'] : [] as $kpi) {
            if (! is_array($kpi)) {
                continue;
            }
            $kpis[] = [
                'label' => (string) ($kpi['label'] ?? ''),
                'value' => (string) ($kpi['value'] ?? '—'),
            ];
            if (count($kpis) >= 6) {
                break;
            }
        }

        if (($gap['cobertura_label'] ?? null) !== null) {
            $kpis[] = ['label' => __('Cobertura rede'), 'value' => (string) $gap['cobertura_label']];
        }
        if (($gap['gap_total_fmt'] ?? null) !== null) {
            $kpis[] = ['label' => __('Lacuna total'), 'value' => (string) $gap['gap_total_fmt']];
        }

        $faixaRows = self::faixaRows($gap);
        if ($faixaRows !== []) {
            $tables[] = [
                'title' => __('Faixas etárias — lacuna e FUNDEB indicativo'),
                'headers' => [
                    __('Faixa'),
                    __('CadÚnico'),
                    __('Rede (est.)'),
                    __('Lacuna'),
                    __('Cobertura'),
                    __('FUNDEB'),
                ],
                'rows' => $faixaRows,
            ];
        }

        $etapaRows = self::etapaRows($gap);
        if ($etapaRows !== []) {
            $tables[] = [
                'title' => __('Lacuna por nível de ensino'),
                'headers' => [
                    __('Nível'),
                    __('CadÚnico'),
                    __('i-Educar'),
                    __('Fora da rede'),
                    __('FUNDEB indic.'),
                ],
                'rows' => $etapaRows,
            ];
        }

        $territoryRows = self::territoryRows($territorial);
        if ($territoryRows !== []) {
            $tables[] = [
                'title' => __('Territórios — distância à escola, pressão e lacuna'),
                'headers' => [
                    __('Território'),
                    __('Código'),
                    __('Tipo'),
                    __('CadÚnico'),
                    __('Lacuna est.'),
                    __('Dist. escola'),
                    __('Pressão'),
                ],
                'rows' => $territoryRows,
            ];
            $notes[] = __('Pressão = lacuna × vulnerabilidade × distância à escola mais próxima. Sem mapa neste PDF — use o painel web para visualização geográfica.');
        }

        $cenarios = is_array($gap['cenarios_financeiros'] ?? null) ? $gap['cenarios_financeiros'] : [];
        if (($cenarios['available'] ?? false) && is_array($cenarios['itens'] ?? null)) {
            $cenarioRows = [];
            foreach ($cenarios['itens'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $cenarioRows[] = [
                    (string) ($item['titulo'] ?? '—'),
                    (string) ($item['valor_label'] ?? '—'),
                ];
            }
            if ($cenarioRows !== []) {
                $tables[] = [
                    'title' => __('Cenários financeiros (NEE/AEE)'),
                    'headers' => [__('Cenário'), __('Valor/ano')],
                    'rows' => $cenarioRows,
                ];
            }
        }

        $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];
        if (($impacto['gap_anual_label'] ?? null) !== null) {
            $notes[] = (string) ($impacto['formula'] ?? __('Impacto FUNDEB indicativo com VAAF municipal.'));
        }

        if (filled($gap['nota'] ?? null)) {
            $notes[] = (string) $gap['nota'];
        }

        $notes[] = (string) ($report['footnote'] ?? __('Agregados Cecad — sem CPF/NIS individuais.'));

        return [
            'available' => $kpis !== [] || $tables !== [],
            'kpis' => $kpis,
            'tables' => $tables,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return list<list<string>>
     */
    private static function faixaRows(array $gap): array
    {
        $rows = [];
        foreach (is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [] as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $rows[] = [
                (string) ($faixa['faixa'] ?? '—'),
                number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.'),
                number_format((int) ($faixa['ieducar_estimado'] ?? 0), 0, ',', '.'),
                (string) ($faixa['gap_fmt'] ?? '0'),
                (string) ($faixa['cobertura_label'] ?? '—'),
                (string) ($faixa['fundeb_gap_label'] ?? '—'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return list<list<string>>
     */
    private static function etapaRows(array $gap): array
    {
        $rows = [];
        foreach (is_array($gap['por_etapa'] ?? null) ? $gap['por_etapa'] : [] as $etapa) {
            if (! is_array($etapa)) {
                continue;
            }
            $rows[] = [
                (string) ($etapa['etapa'] ?? '—'),
                isset($etapa['cadunico_estimado'])
                    ? number_format((int) $etapa['cadunico_estimado'], 0, ',', '.')
                    : '—',
                number_format((int) ($etapa['ieducar_matriculas'] ?? 0), 0, ',', '.'),
                (string) ($etapa['gap_fmt'] ?? '0'),
                (string) ($etapa['fundeb_gap_label'] ?? '—'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $territorial
     * @return list<list<string>>
     */
    private static function territoryRows(array $territorial): array
    {
        $rows = [];
        $ranking = is_array($territorial['ranking'] ?? null) ? $territorial['ranking'] : [];
        $limit = max(5, min(40, (int) config('analytics.pdf_report.cadunico_territory_rows', 25)));

        foreach (array_slice($ranking, 0, $limit) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $distKm = $row['distancia_escola_km'] ?? null;
            $rows[] = [
                (string) ($row['nome'] ?? '—'),
                (string) ($row['codigo'] ?? $row['ibge_territorio'] ?? '—'),
                (string) ($row['tipo'] ?? $row['territorio_fonte'] ?? '—'),
                isset($row['cadunico'])
                    ? number_format((int) $row['cadunico'], 0, ',', '.')
                    : '—',
                (string) ($row['gap_fmt'] ?? number_format((int) ($row['gap_estimado'] ?? 0), 0, ',', '.')),
                $distKm !== null && is_numeric($distKm)
                    ? number_format((float) $distKm, 1, ',', '.').' km'
                    : '—',
                number_format((float) ($row['pressao'] ?? 0), 0, ',', '.'),
            ];
        }

        return $rows;
    }
}
