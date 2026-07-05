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
                'valor' => (string) ($faixa['gap_fmt'] ?? number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.')),
                'detalhe' => __('CadÚnico: :c · Rede: :r · Cobertura: :cov · FUNDEB: :f', [
                    'c' => number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.'),
                    'r' => number_format((int) ($faixa['ieducar_estimado'] ?? 0), 0, ',', '.'),
                    'cov' => (string) ($faixa['cobertura_label'] ?? '—'),
                    'f' => (string) ($faixa['fundeb_gap_label'] ?? '—'),
                ]),
            ];
        }

        $escolarizacao = is_array($data['escolarizacao_card'] ?? null) ? $data['escolarizacao_card'] : [];
        foreach (is_array($escolarizacao['linhas'] ?? null) ? $escolarizacao['linhas'] : [] as $linha) {
            $rows[] = [
                'secao' => __('Escolarização'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($linha['faixa'] ?? ''),
                'valor' => (string) ($linha['fora_rede_municipal_fmt'] ?? ''),
                'detalhe' => __('CadÚnico: :c · Rede: :r · Censo: :cen · Fora escola: :fe · :dec', [
                    'c' => (string) ($linha['cadunico_fmt'] ?? ''),
                    'r' => (string) ($linha['na_rede_municipal_fmt'] ?? ''),
                    'cen' => (string) ($linha['no_municipio_censo_fmt'] ?? '—'),
                    'fe' => (string) ($linha['possivel_fora_escola_fmt'] ?? '—'),
                    'dec' => (string) ($linha['decisao'] ?? ''),
                ]),
            ];
        }

        $eja = is_array($escolarizacao['eja'] ?? null) ? $escolarizacao['eja'] : [];
        if ($eja['available'] ?? false) {
            $rows[] = [
                'secao' => __('EJA'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => __('Matrículas EJA'),
                'valor' => (string) ($eja['censo_total_fmt'] ?? '—'),
                'detalhe' => __('i-Educar: :i · Censo municipal: :m · Não municipal: :nm', [
                    'i' => (string) ($eja['ieducar_municipal_fmt'] ?? '—'),
                    'm' => (string) ($eja['censo_municipal_fmt'] ?? '—'),
                    'nm' => (string) ($eja['censo_nao_municipal_fmt'] ?? '—'),
                ]),
            ];
        }

        $cenarios = is_array($gap['cenarios_financeiros'] ?? null) ? $gap['cenarios_financeiros'] : [];
        foreach (is_array($cenarios['itens'] ?? null) ? $cenarios['itens'] : [] as $item) {
            $rows[] = [
                'secao' => __('Cenário financeiro'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($item['titulo'] ?? ''),
                'valor' => (string) ($item['valor_label'] ?? '—'),
                'detalhe' => isset($item['quantidade'])
                    ? number_format((int) $item['quantidade'], 0, ',', '.')
                    : '',
            ];
        }

        $vuln = is_array($gap['vulnerabilidade'] ?? null) ? $gap['vulnerabilidade'] : [];
        if (($vuln['available'] ?? false) && ($vuln['pct_criancas_pbf_label'] ?? null) !== null) {
            $rows[] = [
                'secao' => __('Vulnerabilidade'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => __('Crianças PBF (est.)'),
                'valor' => (string) $vuln['pct_criancas_pbf_label'],
                'detalhe' => (string) ($vuln['fonte'] ?? ''),
            ];
        }

        $territorial = is_array($data['territorial'] ?? null) ? $data['territorial'] : [];
        foreach (is_array($territorial['ranking'] ?? null) ? $territorial['ranking'] : [] as $row) {
            $rows[] = [
                'secao' => __('Território'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => (string) ($row['nome'] ?? ''),
                'valor' => (string) ($row['gap_fmt'] ?? '0'),
                'detalhe' => __('Pressão: :p · Dist. escola: :d', [
                    'p' => number_format((float) ($row['pressao'] ?? 0), 0, ',', '.'),
                    'd' => isset($row['distancia_escola_km'])
                        ? number_format((float) $row['distancia_escola_km'], 1, ',', '.').' km'
                        : '—',
                ]),
            ];
        }

        $demanda = is_array($data['demanda_oferta'] ?? null) ? $data['demanda_oferta'] : [];
        if ($demanda['available'] ?? false) {
            $rows[] = [
                'secao' => __('Demanda × oferta'),
                'cidade' => $city,
                'ano' => $year,
                'indicador' => __('Resumo'),
                'valor' => (string) ($demanda['demanda_fmt'] ?? '—'),
                'detalhe' => (string) ($demanda['mensagem'] ?? ''),
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
