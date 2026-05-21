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
            'chart_nee_por_raca_stacked' => null,
            'nee_matriculas_por_escola' => [],
            'recurso_prova' => null,
            'inclusion_filters_active' => [],
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
            'complementacao_informe' => [
                'available' => false,
                'aviso' => '',
                'blocos' => [],
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

    public static function otherFunding(): array
    {
        return [
            'year_label' => '',
            'city_name' => '',
            'intro' => '',
            'footnote' => '',
            'programs' => [],
            'transport' => null,
            'total_matriculas' => null,
            'funding_pillars' => [],
            'chart_programas' => null,
            'public_municipal' => ['enabled' => true, 'available' => false, 'queries' => []],
            'error' => null,
        ];
    }

    public static function workDone(): array
    {
        return [
            'year_label' => '',
            'city_name' => '',
            'intro' => '',
            'footnote' => '',
            'period_labels' => [],
            'periods' => ['day' => 0, 'week' => 0, 'fortnight' => 0],
            'by_user' => [],
            'baseline' => ['turmas' => 0, 'matriculas' => 0, 'enturmacoes' => 0, 'ano' => 0],
            'turmas_ano_atual' => 0,
            'enturmacoes_ano_atual' => 0,
            'matriculas_ativas' => 0,
            'estimativa' => [],
            'chart_cadastro_meta' => null,
            'exclusion_notes' => [],
            'activity_available' => false,
            'activity_note' => null,
            'chart_periods' => null,
            'chart_users' => null,
            'chart_censo' => null,
            'censo' => [
                'available' => false,
                'source_label' => null,
                'note' => null,
                'exported' => [],
                'closed' => [],
                'pending' => [],
                'summary' => ['total_escolas' => 0, 'exportadas' => 0, 'fechadas' => 0, 'pendentes' => 0],
            ],
            'methodology' => [],
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
            'export_params' => [],
            'error' => null,
        ];
    }
}
