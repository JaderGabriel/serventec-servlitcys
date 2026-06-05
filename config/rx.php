<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RX — painel operacional multi-município (cadastro + Censo)
    |--------------------------------------------------------------------------
    |
    | Ano vigente: ano civil corrente (comparação com ano anterior).
    | Prazos do Censo Escolar (INEP): ajuste por exercício em censo_deadlines.
    |
    */

    /** Ano letivo vigente (0 = ano civil actual). */
    'vigente_year' => (int) env('RX_VIGENTE_YEAR', 0) ?: (int) date('Y'),

    /**
     * Exercício FUNDEB para o gráfico de complementações no RX (0 = mesmo que vigente_year).
     * Dados consolidados da portaria — distintos do cadastro em andamento no painel.
     */
    'fundeb_portaria_exercicio' => (int) env('RX_FUNDEB_PORTARIA_EXERCICIO', 0),

    /**
     * Prazos operacionais do Censo Escolar por ano de referência.
     * collect_end: fim da janela de preenchimento/exportação no i-Educar.
     * validate_end: data limite indicativa de validação/revisão (opcional).
     */
    'censo_deadlines' => [
        2024 => ['collect_end' => '2024-06-28', 'validate_end' => '2024-07-15'],
        2025 => ['collect_end' => '2025-06-27', 'validate_end' => '2025-07-15'],
        2026 => ['collect_end' => '2026-06-30', 'validate_end' => '2026-07-15'],
    ],

    /** Fallback quando o ano não está em censo_deadlines (mês-dia). */
    'censo_collect_end_default' => env('RX_CENSO_COLLECT_END_DEFAULT', '06-30'),

    /** Timeout por município ao consultar a base i-Educar (segundos). */
    'city_query_timeout' => max(5, (int) env('RX_CITY_QUERY_TIMEOUT', 25)),

    /** Anos máximos para trás ao procurar meta quando Y-1 tem turmas e matrículas zeradas. */
    'meta_lookback_years' => max(1, (int) env('RX_META_LOOKBACK_YEARS', 10)),

    /** Acréscimo percentual na meta por cada «salto» (ano a mais para trás face a Y-1). Ex.: 5 → ×1,05 por salto. */
    'meta_pct_per_salto' => (float) env('RX_META_PCT_PER_SALTO', 5),

    /** Limiares do semáforo de cumprimento da meta (progresso sobre matrículas). */
    'semaphore' => [
        'yellow_min_progress' => (float) env('RX_SEMAPHORE_YELLOW_MIN', 75),
    ],

];
