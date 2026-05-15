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
            'public_data_sources' => ['intro' => '', 'categories' => []],
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
            'public_data_sources' => ['intro' => '', 'categories' => []],
            'resource_projection' => [
                'available' => false,
                'kpis' => [],
                'totais' => [],
                'distribuicao_legal' => ['itens' => [], 'chart' => null],
                'por_etapa' => [],
                'chart_previsao' => null,
                'chart_distribuicao' => null,
                'chart_etapa' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function municipalityHealth(): array
    {
        return [
            'intro' => '',
            'footnote' => '',
            'year_label' => '',
            'city_name' => '',
            'compliance_score' => null,
            'compliance_status' => 'neutral',
            'compliance_label' => '',
            'summary' => [
                'pendencias_cadastro' => 0,
                'modulos_fundeb_alerta' => 0,
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
                'escolas_afetadas' => 0,
                'total_matriculas' => null,
            ],
            'cadastro_dimensions' => [],
            'thematic_blocks' => [],
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_pendencias' => null,
            'active_check_ids' => [],
            'funding_metodologia' => null,
            'funding_resumo_explicacao' => null,
            'public_data_sources' => ['intro' => '', 'categories' => []],
            'error' => null,
        ];
    }

    public static function discrepancies(): array
    {
        return [
            'intro' => '',
            'footnote' => '',
            'funding_aviso' => '',
            'year_label' => '',
            'city_name' => '',
            'total_matriculas' => null,
            'funding_reference' => null,
            'funding_metodologia' => null,
            'funding_resumo_explicacao' => null,
            'summary' => [
                'com_problema' => 0,
                'corrigiveis' => 0,
                'escolas_afetadas' => 0,
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
            ],
            'chart_resumo' => null,
            'chart_financeiro' => null,
            'funding_pillars' => [],
            'dimensions' => [],
            'active_check_ids' => [],
            'checks' => [],
            'notes' => [],
            'public_data_sources' => ['intro' => '', 'categories' => []],
            'error' => null,
        ];
    }
}
