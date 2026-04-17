<?php

namespace App\Support\Dashboard;

/**
 * Payloads vazios alinhados ao que as vistas esperam quando o carregamento lazy
 * ainda não correu o repositório correspondente.
 */
final class AnalyticsEmptyPayloads
{
    /**
     * @return array<string, mixed>
     */
    public static function enrollment(): array
    {
        return [
            'rows' => [],
            'kpis' => null,
            'distorcao' => null,
            'distorcao_cartao_motivo' => null,
            'fluxo_taxas' => null,
            'unidades_escolares' => null,
            'error' => null,
            'chart' => null,
            'charts' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function performance(): array
    {
        return [
            'rows' => [],
            'message' => '',
            'error' => null,
            'chart' => null,
            'charts' => [],
            'kpis' => [],
            'kpi_meta' => [
                'total_matriculas' => 0,
                'campo_situacao' => '',
                'denominador_texto' => '',
                'alerta_ano_encerrado' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attendance(): array
    {
        return ['rows' => [], 'message' => '', 'error' => null, 'chart' => null, 'charts' => []];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inclusion(): array
    {
        return [
            'charts' => [],
            'nee_charts_count' => 0,
            'nee_detalhe_catalogo' => null,
            'aee_cross' => null,
            'gauges' => [],
            'notes' => [],
            'error' => null,
            'total_matriculas' => null,
            'equidade_fonte' => null,
            'methodology' => [],
            'nee_grupo_resumo' => null,
            'chart_raca_por_escola_stacked' => null,
            'nee_matriculas_por_escola' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function network(): array
    {
        return ['charts' => [], 'vagas_por_unidade_chart' => null, 'kpis' => null, 'notes' => [], 'error' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fundeb(): array
    {
        return [
            'year_label' => '',
            'city_name' => '',
            'intro' => '',
            'footnote' => '',
            'modules' => [],
        ];
    }
}
