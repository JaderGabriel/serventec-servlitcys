<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diagnóstico de bases de dados no Pulse
    |--------------------------------------------------------------------------
    |
    | Métricas estruturadas: base Laravel (MySQL/MariaDB) vs bases i-Educar
    | municipais (ligações city_data_*), consultas lentas e tempo total por pedido.
    |
    */

    'enabled' => env('PULSE_DB_DIAGNOSTICS_ENABLED', true),

    /** Consultas individuais acima deste valor (ms) entram em db_slow_scope / db_slow_fp. */
    'slow_query_ms' => max(50, (int) env('PULSE_DB_DIAGNOSTICS_SLOW_MS', 300)),

    /** Blocos CityDataConnection::run acima deste valor (ms) reforçam db_muni_run. */
    'slow_municipal_run_ms' => max(100, (int) env('PULSE_DB_DIAGNOSTICS_SLOW_RUN_MS', 1500)),

    /** Acumular tempo total SQL por âmbito (system / municipal) por pedido HTTP. */
    'accumulate_request_totals' => env('PULSE_DB_DIAGNOSTICS_REQUEST_TOTALS', true),

    /*
    |--------------------------------------------------------------------------
    | Operações da aplicação (HTTP, jobs, imports, RX, mapa)
    |--------------------------------------------------------------------------
    */

    'operations_enabled' => env('PULSE_OPERATIONS_ENABLED', true),

    /** Operações acima deste valor (ms) duplicam em app_operation_slow. */
    'slow_operation_ms' => max(100, (int) env('PULSE_OPERATIONS_SLOW_MS', 750)),

    /** Registar duração de pedidos HTTP por rota nomeada (complementa Slow requests). */
    'http_routes_enabled' => env('PULSE_OPERATIONS_HTTP', true),

];
