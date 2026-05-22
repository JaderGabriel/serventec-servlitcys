<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Intervalo do cron `php artisan schedule:run`
    |--------------------------------------------------------------------------
    |
    | Deve coincidir com a expressão cron no servidor (ex.: de 3 em 3 minutos).
    | O Laravel só avalia tarefas quando o scheduler é invocado; tarefas
    | «a cada minuto» passam a correr na cadência deste intervalo.
    |
    */

    'runner_interval_minutes' => max(1, min(59, (int) env('SCHEDULE_RUN_INTERVAL_MINUTES', 3))),

    /*
    |--------------------------------------------------------------------------
    | Log do scheduler (cron)
    |--------------------------------------------------------------------------
    |
    | Quando true, pulse:check e pulse:work no schedule gravam saída em
    | storage/logs/scheduler.log (útil se o cron redireciona para /dev/null).
    |
    */

    'log_to_file' => filter_var(env('SCHEDULE_LOG_TO_FILE', false), FILTER_VALIDATE_BOOL),

    'log_path' => env('SCHEDULE_LOG_PATH', storage_path('logs/scheduler.log')),

];
