<?php

namespace App\Support\Analytics;

/**
 * Linhas tabulares para exportação CSV/Excel da aba CadÚnico.
 */
final class CadunicoPrevisaoExportRowsBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, string>>
     */
    public static function fromReport(array $data): array
    {
        $rows = [];
        $city = (string) ($data['city_name'] ?? '');
        $year = (string) ($data['year_label'] ?? '');
        $gap = is_array($data['gap'] ?? null) ? $data['gap'] : [];

        foreach (is_array($data['kpis'] ?? null) ? $data['kpis'] : [] as $kpi) {
            $rows[] = [
                'secao' => __('Resumo'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($kpi['label'] ?? ''),
                'valor' => (string) ($kpi['value'] ?? ''),
                'detalhe' => (string) ($kpi['explicacao_resumo'] ?? ''),
            ];
        }

        foreach (is_array($gap['por_etapa'] ?? null) ? $gap['por_etapa'] : [] as $etapa) {
            $rows[] = [
                'secao' => __('Por etapa'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($etapa['etapa'] ?? ''),
                'valor' => (string) ($etapa['gap_fmt'] ?? ''),
                'detalhe' => __('CadÚnico: :c · i-Educar: :i · FUNDEB: :f', [
                    'c' => isset($etapa['cadunico_estimado']) ? number_format((int) $etapa['cadunico_estimado'], 0, ',', '.') : '—',
                    'i' => number_format((int) ($etapa['ieducar_matriculas'] ?? 0), 0, ',', '.'),
                    'f' => (string) ($etapa['fundeb_gap_label'] ?? '—'),
                ]),
            ];
        }

        foreach (is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [] as $faixa) {
            $rows[] = [
                'secao' => __('Faixa etária'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($faixa['faixa'] ?? ''),
                'valor' => number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.'),
                'detalhe' => '',
            ];
        }

        $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];
        if ($impacto !== []) {
            $rows[] = [
                'secao' => __('Impacto FUNDEB'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => __('Lacuna anual indicativa'),
                'valor' => (string) ($impacto['gap_anual_label'] ?? '—'),
                'detalhe' => (string) ($impacto['formula'] ?? ''),
            ];
        }

        foreach (is_array($data['alerts'] ?? null) ? $data['alerts'] : [] as $alert) {
            $rows[] = [
                'secao' => __('Alerta'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($alert['title'] ?? ''),
                'valor' => (string) ($alert['tone'] ?? ''),
                'detalhe' => (string) ($alert['message'] ?? ''),
            ];
        }

        $informe = is_array($data['informe'] ?? null) ? $data['informe'] : [];
        foreach (is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [] as $bloco) {
            $paragrafos = is_array($bloco['paragrafos'] ?? null) ? implode(' ', $bloco['paragrafos']) : '';
            $rows[] = [
                'secao' => __('Informe'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($bloco['titulo'] ?? ''),
                'valor' => (string) ($bloco['status_label'] ?? ''),
                'detalhe' => $paragrafos,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function columnHeaders(): array
    {
        return ['secao', 'cidade', 'ano', 'indicador', 'valor', 'detalhe'];
    }

    /**
     * @return list<string>
     */
    public static function columnLabels(): array
    {
        return [
            __('Secção'),
            __('Cidade'),
            __('Ano'),
            __('Indicador'),
            __('Valor'),
            __('Detalhe'),
        ];
    }
}
