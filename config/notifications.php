<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centro de notificações (sino na barra)
    |--------------------------------------------------------------------------
    */

    'enabled' => filter_var(env('APP_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'poll_interval_seconds' => max(15, (int) env('APP_NOTIFICATIONS_POLL_SECONDS', 45)),

    'index_limit' => max(5, min(50, (int) env('APP_NOTIFICATIONS_INDEX_LIMIT', 25))),

    'queue' => env('APP_NOTIFICATIONS_QUEUE', 'default'),

];
