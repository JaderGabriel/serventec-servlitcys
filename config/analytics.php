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

    'lazy_tab_loading' => filter_var(env('ANALYTICS_LAZY_TABS', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Resumo financeiro no carregamento inicial do painel
    |--------------------------------------------------------------------------
    |
    | Quando false (recomendado), «Aplicar filtros» não executa o agregado pesado
    | de Discrepâncias — evita timeout/500. A faixa de saldo fica disponível nas
    | abas Diagnóstico, Discrepâncias e FUNDEB (lazy ou eager).
    |
    */

    'index_funding_context' => filter_var(env('ANALYTICS_INDEX_FUNDING_CONTEXT', false), FILTER_VALIDATE_BOOL),

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

    /*
    |--------------------------------------------------------------------------
    | Relatório PDF (aba Serventec)
    |--------------------------------------------------------------------------
    */

    'pdf_report' => [
        'queue' => (string) env('ANALYTICS_PDF_QUEUE', 'default'),
        'connection' => env('ANALYTICS_PDF_QUEUE_CONNECTION'),
        'job_timeout' => max(120, (int) env('ANALYTICS_PDF_JOB_TIMEOUT', 900)),
        'tries' => max(1, (int) env('ANALYTICS_PDF_TRIES', 2)),
        'disk' => (string) env('ANALYTICS_PDF_DISK', 'local'),
        'path_prefix' => 'analytics-reports',
        'max_exports_per_user' => max(1, (int) env('ANALYTICS_PDF_MAX_PER_USER', 10)),
        'brand' => [
            'serventec_name' => (string) env('ANALYTICS_PDF_SERVENTEC_NAME', 'Serventec Assessoria'),
            'serventec_url' => (string) env('ANALYTICS_PDF_SERVENTEC_URL', 'https://serventec.com.br'),
            'developer_name' => (string) env('ANALYTICS_PDF_DEVELOPER_NAME', 'Jader Gabriel'),
            'developer_github' => (string) env('ANALYTICS_PDF_DEVELOPER_GITHUB', 'https://github.com/jadergabriel'),
        ],
        'colors' => [
            'primary' => '#0f766e',
            'primary_light' => '#ccfbf1',
            'secondary' => '#4338ca',
            'accent' => '#0369a1',
            'danger' => '#be123c',
            'warning' => '#b45309',
            'success' => '#15803d',
            'muted' => '#64748b',
            'chart' => ['#0f766e', '#4338ca', '#0369a1', '#b45309', '#be123c', '#7c3aed'],
        ],
        'cover' => [
            'regional_image_base' => (string) env('ANALYTICS_PDF_REGIONAL_IMAGE', 'images/pdf/regional'),
            'map_zoom' => max(5, min(14, (int) env('ANALYTICS_PDF_MAP_ZOOM', 9))),
        ],
    ],

];
