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

];
