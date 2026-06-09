<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auditoria de login
    |--------------------------------------------------------------------------
    |
    | Quando true, o insert em admin_user_logs ocorre após enviar a resposta
    | HTTP (não bloqueia o redirect pós-login).
    |
    */
    'defer_login_audit' => env('PERFORMANCE_DEFER_LOGIN_AUDIT', true),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap SMTP em rotas de autenticação
    |--------------------------------------------------------------------------
    |
    | Evita consulta/cache a mail_settings no GET/POST de login e recuperação
    | de senha (o e-mail só é necessário ao enviar mensagens).
    |
    */
    'skip_mail_on_auth_routes' => env('PERFORMANCE_SKIP_MAIL_ON_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Cache de city_ids por utilizador municipal
    |--------------------------------------------------------------------------
    |
    | Reduz consultas repetidas à tabela pivot city_user. 0 desativa o cache.
    |
    */
    'user_city_ids_cache' => (int) env('PERFORMANCE_USER_CITY_IDS_CACHE', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache das definições SMTP (mail_settings)
    |--------------------------------------------------------------------------
    */
    'mail_settings_cache' => (int) env('PERFORMANCE_MAIL_SETTINGS_CACHE', 3600),

    /*
    |--------------------------------------------------------------------------
    | Pulse em rotas de autenticação
    |--------------------------------------------------------------------------
    |
    | Evita gravações Pulse em login/logout/recuperação de senha.
    |
    */
    'pulse_skip_auth_routes' => env('PERFORMANCE_PULSE_SKIP_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Início (/dashboard) — snapshot RX do mapa
    |--------------------------------------------------------------------------
    |
    | Quando true, a página Início não consulta o i-Educar por município no
    | servidor; o mapa carrega cores RX via AJAX (cadastro-snapshot). Evita
    | bloquear o login→Início enquanto o cache RX (20 min) está frio.
    |
    */
    'home_defer_map_rx_snapshot' => filter_var(env('PERFORMANCE_HOME_DEFER_MAP_RX', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Cache do mapa «Municípios implementados» (marcadores + snapshot RX)
    |--------------------------------------------------------------------------
    |
    | TTL mínimo efectivo: 3600 s (1 h). Preferência: store redis quando
    | REDIS_* está acessível; caso contrário usa CACHE_STORE.
    |
    */
    'home_map_cache_ttl' => max(3600, (int) env('PERFORMANCE_HOME_MAP_CACHE_TTL', 3600)),
    'home_map_cache_store' => env('PERFORMANCE_HOME_MAP_CACHE_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Alertas operacionais no Início
    |--------------------------------------------------------------------------
    |
    | Quando true, a avaliação de filas/sync/PDF corre após enviar a resposta.
    |
    */
    'defer_operational_alerts_on_home' => filter_var(env('PERFORMANCE_DEFER_OPS_ALERTS_HOME', true), FILTER_VALIDATE_BOOL),

];
