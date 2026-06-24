<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carregamento lazy das abas do painel de análise educacional
    |--------------------------------------------------------------------------
    |
    | Quando true, a página inicial só executa o repositório da aba «Visão geral».
    | Unidades escolares (mapa/geo pesado) e as demais abas vêm via
    | GET /dashboard/analytics/tab?tab=… (pedidos separados no Pulse).
    |
    */

    'lazy_tab_loading' => filter_var(env('ANALYTICS_LAZY_TABS', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Filtros secundários no «Aplicar filtros» (index)
    |--------------------------------------------------------------------------
    |
    | Quando true (recomendado), o pedido inicial só carrega anos letivos na BD
    | remota; escolas, cursos e turnos vêm via AJAX após a página abrir.
    | Evita timeout/500 ao seleccionar ano letivo em bases i-Educar lentas.
    |
    */

    'index_light_filters' => filter_var(env('ANALYTICS_INDEX_LIGHT_FILTERS', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Visão geral no «Aplicar filtros»
    |--------------------------------------------------------------------------
    |
    | Quando false (recomendado com ANALYTICS_LAZY_TABS), o index não consulta a
    | BD remota para a aba Visão geral — evita 500/timeout; a aba carrega via AJAX.
    |
    */

    'index_load_overview' => filter_var(env('ANALYTICS_INDEX_LOAD_OVERVIEW', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Log de performance do painel analítico
    |--------------------------------------------------------------------------
    |
    | Quando true, regista analytics.profile e analytics.profile_summary no log
    | (storage/logs). Útil para diagnosticar 500/timeout ao filtrar por ano.
    |
    */

    'debug_log' => filter_var(env('ANALYTICS_DEBUG_LOG', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Rota de diagnóstico (erro 500 / timeout)
    |--------------------------------------------------------------------------
    |
    | GET /admin/analytics-diagnostics — bateria de testes com debug completo.
    | Activar em local/staging ou com ANALYTICS_DIAGNOSTICS_FORCE=true.
    | Opcional: ANALYTICS_DIAGNOSTICS_TOKEN na query ?token=
    |
    */

    'diagnostics_route_enabled' => filter_var(env('ANALYTICS_DIAGNOSTICS_FORCE', false), FILTER_VALIDATE_BOOL)
        || filter_var(env('ANALYTICS_DIAGNOSTICS_ROUTE', false), FILTER_VALIDATE_BOOL),

    'diagnostics_token' => (string) env('ANALYTICS_DIAGNOSTICS_TOKEN', ''),

    'diagnostics_max_step_seconds' => max(30, (int) env('ANALYTICS_DIAGNOSTICS_MAX_STEP_SECONDS', 120)),

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

    /*
    |--------------------------------------------------------------------------
    | Faixa de impacto na aba Diagnóstico (lazy)
    |--------------------------------------------------------------------------
    |
    | Quando true, o contexto municipal (saldo, pendências) vem do snapshot do
    | Diagnóstico já carregado — não repete Visão geral nem fundingImpactSnapshot.
    |
    */

    'municipality_health_reuse_funding_context' => filter_var(
        env('ANALYTICS_MUNICIPALITY_HEALTH_REUSE_CONTEXT', true),
        FILTER_VALIDATE_BOOL,
    ),

    /** Segundos de cache do snapshot do Diagnóstico (0 = sem cache). */
    'municipality_health_cache_seconds' => max(0, (int) env('ANALYTICS_MUNICIPALITY_HEALTH_CACHE', 300)),

    /*
    |--------------------------------------------------------------------------
    | Diagnóstico — modo de carregamento
    |--------------------------------------------------------------------------
    |
    | strategic (defeito): um pedido leve — Discrepâncias (sem checks por escola),
    | fatia FUNDEB (sem perfil VAAF multi-ano), reutiliza cache de outras abas.
    | full: snapshot completo (pedagógico + Censo + INEP).
    | progressive: shell + secções AJAX (legado).
    |
    */

    'municipality_health_mode' => env('ANALYTICS_MUNICIPALITY_HEALTH_MODE', 'strategic'),

    'municipality_health_progressive_sections' => filter_var(
        env('ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE', false),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Abas de Finanças — reutilizar dados já carregados no contexto municipal
    |--------------------------------------------------------------------------
    |
    | Discrepâncias e FUNDEB: evita segunda passagem em Visão geral + fundingImpact.
    | Financiamentos / Censo: só o resumo financeiro em cache (faixa de impacto).
    |
    */

    'finance_tabs_reuse_funding_context' => filter_var(
        env('ANALYTICS_FINANCE_TABS_REUSE_CONTEXT', true),
        FILTER_VALIDATE_BOOL,
    ),

    'finance_tabs_strip_funding_context' => filter_var(
        env('ANALYTICS_FINANCE_TABS_STRIP_CONTEXT', true),
        FILTER_VALIDATE_BOOL,
    ),

    /** Segundos de cache do resumo (0 = sem cache). Reutiliza após visitar Discrepâncias. */
    'funding_summary_cache_seconds' => max(0, (int) env('ANALYTICS_FUNDING_SUMMARY_CACHE', 600)),

    /*
    |--------------------------------------------------------------------------
    | Finanças — contexto VAAF leve (sem rotinas Discrepâncias)
    |--------------------------------------------------------------------------
    |
    | true (defeito): fundingImpactSnapshot delega a lightFundingContext (rápido).
    | Tempo Real / Comparativo (preload) usam lightFundingContext() directamente.
    | false: executa todas as rotinas com fundingOnly (lento; use só para debug).
    |
    */

    'finance_use_light_funding_context' => filter_var(
        env('ANALYTICS_FINANCE_LIGHT_FUNDING', true),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Aba FUNDEB (lazy) — bundle leve
    |--------------------------------------------------------------------------
    |
    | true (defeito): matrículas do snapshot financeiro leve; não chama Visão geral
    | nem amostra de Matrículas; omitir perfil VAAF multi-ano FNDE na primeira carga.
    |
    */

    'fundeb_tab_light_bundle' => filter_var(env('ANALYTICS_FUNDEB_LIGHT_TAB', true), FILTER_VALIDATE_BOOL),

    'fundeb_skip_vaaf_profile_on_tab' => filter_var(env('ANALYTICS_FUNDEB_SKIP_VAAF_PROFILE', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Medidor FUNDEB no rodapé fixo (consultoria)
    |--------------------------------------------------------------------------
    |
    | Compara projeção base (matrículas × VAAF) entre ano anterior, actual e
    | seguinte no recorte activo. Consulta leve (3 exercícios), independente da
    | aba FUNDEB lazy.
    |
    */

    'fundeb_dock_meter' => filter_var(env('ANALYTICS_FUNDEB_DOCK_METER', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Índice de qualidade no rodapé fixo (consultoria)
    |--------------------------------------------------------------------------
    |
    | Mesmo índice 0–100 do Painel de decisão (Diagnóstico). Reutiliza snapshot
    | do Diagnóstico quando já carregado; senão estima pelo resumo financeiro.
    |
    */

    'quality_dock_indicator' => filter_var(env('ANALYTICS_QUALITY_DOCK_INDICATOR', true), FILTER_VALIDATE_BOOL),

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
        /*
         * Processamento via `php artisan schedule:run` (cron a cada SCHEDULE_RUN_INTERVAL_MINUTES).
         * Quando há exportações pendentes ou jobs na fila, dispara analytics-pdf:work (on_demand).
         * Desactive se usar `analytics-pdf:work` ou `queue:work` contínuo em Supervisor.
         */
        'schedule' => [
            'enabled' => filter_var(env('ANALYTICS_PDF_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'on_demand' => filter_var(env('ANALYTICS_PDF_SCHEDULE_ON_DEMAND', true), FILTER_VALIDATE_BOOL),
            'on_demand_max_seconds' => max(60, (int) env('ANALYTICS_PDF_SCHEDULE_ON_DEMAND_MAX_SECONDS', 900)),
        ],
        'disk' => (string) env('ANALYTICS_PDF_DISK', 'local'),
        'path_prefix' => 'analytics-reports',
        'max_exports_per_user' => max(1, (int) env('ANALYTICS_PDF_MAX_PER_USER', 10)),
        'brand' => [
            'system_name' => (string) env('ANALYTICS_PDF_SYSTEM_NAME', env('APP_NAME', 'SERVLITCYS')),
            'system_tagline' => (string) env('ANALYTICS_PDF_SYSTEM_TAGLINE', 'Consultoria, gráficos e Horizonte municipal'),
            'icon_path' => (string) env('ANALYTICS_PDF_ICON_PATH', 'favicon.svg'),
            'serventec_name' => (string) env('ANALYTICS_PDF_SERVENTEC_NAME', 'Serventec Assessoria'),
            'serventec_url' => (string) env('ANALYTICS_PDF_SERVENTEC_URL', 'https://analise.serventecassessoria.com.br/'),
            'serventec_display_url' => (string) env('ANALYTICS_PDF_SERVENTEC_DISPLAY_URL', 'analise.serventecassessoria.com.br'),
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
            'map_zoom' => max(5, min(14, (int) env('ANALYTICS_PDF_MAP_ZOOM', 10))),
            'nominatim_user_agent' => (string) env('ANALYTICS_PDF_NOMINATIM_USER_AGENT', 'servlitcys-pdf-cover/1.0 (contact: analise.serventecassessoria.com.br)'),
        ],
        /** Largura útil A4 retrato (pt) — evita mapas/tabelas a ultrapassar a margem no DomPDF. */
        'content_width_pt' => max(400, (int) env('ANALYTICS_PDF_CONTENT_WIDTH_PT', 520)),
        'school_map_height_pt' => max(220, (int) env('ANALYTICS_PDF_SCHOOL_MAP_HEIGHT_PT', 292)),
    ],

];
