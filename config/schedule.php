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

];
