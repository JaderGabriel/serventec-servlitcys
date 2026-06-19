<?php

return [

    'enabled' => filter_var(env('PUBLIC_DATA_DAILY_CHECK_ENABLED', true), FILTER_VALIDATE_BOOL),

    'schedule' => [
        'enabled' => filter_var(env('PUBLIC_DATA_DAILY_CHECK_SCHEDULE', true), FILTER_VALIDATE_BOOL),
        'time' => env('PUBLIC_DATA_DAILY_CHECK_TIME', '07:00'),
    ],

    /** Timeout HTTP por fonte (segundos). */
    'http_timeout' => max(5, (int) env('PUBLIC_DATA_DAILY_CHECK_HTTP_TIMEOUT', 12)),

];
