<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carregamento lazy das abas do painel de análise educacional
    |--------------------------------------------------------------------------
    |
    | Quando true, a página inicial só executa os repositórios necessários à
    | aba «Visão geral» e «Unidades escolares» (dados partilhados). As restantes
    | abas são obtidas via GET /dashboard/analytics/tab?tab=… (aparecem no Pulse
    | como pedidos separados para análise de tempo por aba).
    |
    */

    'lazy_tab_loading' => env('ANALYTICS_LAZY_TABS', true),

    /*
    |--------------------------------------------------------------------------
    | Resumo financeiro (perda/ganho) na aba FUNDEB (carregamento lazy)
    |--------------------------------------------------------------------------
    |
    | Quando true, a aba FUNDEB obtém apenas o agregado de discrepâncias
    | (sem listas por escola nem gráficos), com cache opcional.
    | Desative (false) se a aba FUNDEB ainda ficar lenta na primeira visita.
    |
    */

    'fundeb_load_discrepancies_summary' => filter_var(env('ANALYTICS_FUNDEB_DISC_SUMMARY', true), FILTER_VALIDATE_BOOL),

    /** Segundos de cache do resumo (0 = sem cache). Reutiliza após visitar Discrepâncias. */
    'funding_summary_cache_seconds' => max(0, (int) env('ANALYTICS_FUNDING_SUMMARY_CACHE', 600)),

];
