<?php

namespace App\Support\Analytics;

/**
 * Linhas tabulares para exportação CSV/Excel do comparativo anual.
 */
final class ComparativoExportRowsBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, string>>
     */
    public static function fromReport(array $data): array
    {
        $rows = [];
        $city = (string) ($data['city_name'] ?? '');
        $baseYear = (string) ($data['base_year'] ?? '');
        $prevYear = (string) ($data['prev_year'] ?? '');
        $nextYear = (string) ($data['next_year'] ?? '');

        foreach (is_array($data['variacoes'] ?? null) ? $data['variacoes'] : [] as $row) {
            $rows[] = [
                'secao' => __('Variação ano a ano'),
                'cidade' => $city,
                'ano_base' => $baseYear,
                'ano_anterior' => $prevYear,
                'indicador' => (string) ($row['label'] ?? ''),
                'valor_ano_base' => (string) ($row['base_fmt'] ?? ''),
                'valor_ano_anterior' => (string) ($row['prev_fmt'] ?? ''),
                'variacao' => (string) ($row['delta_label'] ?? ''),
                'leitura' => (string) ($row['leitura'] ?? ''),
                'detalhe' => '',
            ];
        }

        $detail = is_array($data['base_year_detail'] ?? null) ? $data['base_year_detail'] : [];
        foreach (is_array($detail['por_etapa'] ?? null) ? $detail['por_etapa'] : [] as $etapa) {
            $rows[] = [
                'secao' => __('Matrículas por etapa (FUNDEB)'),
                'cidade' => $city,
                'ano_base' => $baseYear,
                'ano_anterior' => $prevYear,
                'indicador' => (string) ($etapa['etapa'] ?? ''),
                'valor_ano_base' => number_format((int) ($etapa['matriculas'] ?? 0), 0, ',', '.'),
                'valor_ano_anterior' => number_format((float) ($etapa['participacao_pct'] ?? 0), 1, ',', '.').'%',
                'variacao' => (string) ($etapa['fundeb_label'] ?? ''),
                'leitura' => __('FUNDEB indicativo no ano base'),
                'detalhe' => '',
            ];
        }

        $proj = is_array($data['next_year_projection'] ?? null) ? $data['next_year_projection'] : [];
        if ((bool) ($proj['available'] ?? false)) {
            $rows[] = [
                'secao' => __('Projeção próximo exercício'),
                'cidade' => $city,
                'ano_base' => $baseYear,
                'ano_anterior' => $prevYear,
                'indicador' => __('Exercício :ano', ['ano' => $nextYear]),
                'valor_ano_base' => (string) ($proj['previsao_label'] ?? ''),
                'valor_ano_anterior' => (string) ($proj['previsao_base_label'] ?? ''),
                'variacao' => (string) ($proj['delta_label'] ?? ''),
                'leitura' => (string) ($proj['note'] ?? ''),
                'detalhe' => __('Matrículas: :n · VAAF: :v', [
                    'n' => (string) ($proj['matriculas_fmt'] ?? ''),
                    'v' => (string) ($proj['vaaf_label'] ?? ''),
                ]),
            ];
        }

        foreach (is_array($data['fundeb_series'] ?? null) ? $data['fundeb_series'] : [] as $serie) {
            $rows[] = [
                'secao' => __('Série VAAF'),
                'cidade' => $city,
                'ano_base' => (string) ($serie['ano'] ?? ''),
                'ano_anterior' => '',
                'indicador' => __('VAAF'),
                'valor_ano_base' => (string) ($serie['vaaf'] ?? ''),
                'valor_ano_anterior' => (string) ($serie['variacao'] ?? ''),
                'variacao' => ! empty($serie['is_anchor']) ? __('Ano base') : '',
                'leitura' => (string) ($serie['fonte'] ?? ''),
                'detalhe' => '',
            ];
        }

        foreach (is_array($data['alerts'] ?? null) ? $data['alerts'] : [] as $alert) {
            $rows[] = [
                'secao' => __('Alerta consultoria'),
                'cidade' => $city,
                'ano_base' => $baseYear,
                'ano_anterior' => $prevYear,
                'indicador' => (string) ($alert['title'] ?? ''),
                'valor_ano_base' => '',
                'valor_ano_anterior' => '',
                'variacao' => (string) ($alert['tone'] ?? ''),
                'leitura' => (string) ($alert['message'] ?? ''),
                'detalhe' => '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function columnHeaders(): array
    {
        return [
            'secao',
            'cidade',
            'ano_base',
            'ano_anterior',
            'indicador',
            'valor_ano_base',
            'valor_ano_anterior',
            'variacao',
            'leitura',
            'detalhe',
        ];
    }

    /**
     * @return list<string>
     */
    public static function columnLabels(): array
    {
        return [
            __('Secção'),
            __('Cidade'),
            __('Ano base'),
            __('Ano anterior'),
            __('Indicador'),
            __('Valor ano base'),
            __('Valor ano anterior'),
            __('Variação'),
            __('Leitura'),
            __('Detalhe'),
        ];
    }
}
