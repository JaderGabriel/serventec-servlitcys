<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centro de notificações (sino na barra)
    |--------------------------------------------------------------------------
    */

    'enabled' => filter_var(env('APP_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'poll_interval_seconds' => max(15, (int) env('APP_NOTIFICATIONS_POLL_SECONDS', 30)),

    'index_limit' => max(5, min(80, (int) env('APP_NOTIFICATIONS_INDEX_LIMIT', 40))),

    'queue' => env('APP_NOTIFICATIONS_QUEUE', 'default'),

    /** Minutos sem repetir a mesma notificação (dedupe_key) para o mesmo usuário. */
    'dedupe_ttl_minutes' => max(5, (int) env('APP_NOTIFICATIONS_DEDUPE_MINUTES', 360)),

    /*
    |--------------------------------------------------------------------------
    | Alertas operacionais (informes críticos para administradores)
    |--------------------------------------------------------------------------
    */

    'operational_alerts' => [
        'enabled' => filter_var(env('APP_NOTIFICATIONS_OPERATIONAL', true), FILTER_VALIDATE_BOOL),
        /** Falhas de sync nas últimas 24 h que disparam alerta crítico. */
        'sync_failures_threshold' => max(1, (int) env('APP_NOTIFICATIONS_SYNC_FAIL_THRESHOLD', 1)),
        /** PDF em pending/processing há mais de N horas. */
        'pdf_stale_hours' => max(1, (int) env('APP_NOTIFICATIONS_PDF_STALE_HOURS', 2)),
        /** Jobs na fila (tabela jobs) acima deste valor. */
        'queue_pending_threshold' => max(10, (int) env('APP_NOTIFICATIONS_QUEUE_PENDING_THRESHOLD', 25)),
        /**
         * Avaliação automática via `php artisan schedule:run` (não depende de abrir a dashboard).
         */
        'schedule' => [
            'enabled' => filter_var(env('APP_NOTIFICATIONS_OPERATIONAL_SCHEDULE', true), FILTER_VALIDATE_BOOL),
            'interval_minutes' => max(5, min(120, (int) env('APP_NOTIFICATIONS_OPERATIONAL_INTERVAL_MINUTES', 15))),
        ],
    ],

    /** Notificar usuário ao abrir painel analítico com erros parciais. */
    'analytics_partial_errors' => filter_var(env('APP_NOTIFICATIONS_ANALYTICS_ERRORS', true), FILTER_VALIDATE_BOOL),

];
