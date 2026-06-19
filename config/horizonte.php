<?php

use App\Support\Horizonte\HorizonteReferenceYear;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizonte — mapa de oportunidade municipal
    |--------------------------------------------------------------------------
    |
    | Score indicativo (0–100) com base em dados públicos importados no hub
    | Dados públicos. Não substitui diagnóstico i-Educar / Consultoria.
    |
    */

    'enabled' => filter_var(env('HORIZONTE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'cache_seconds' => max(60, (int) env('HORIZONTE_CACHE_SECONDS', 900)),

    'reference_year' => HorizonteReferenceYear::resolve(),

    'high_opportunity_threshold' => max(1, min(99, (int) env('HORIZONTE_HIGH_THRESHOLD', 70))),

    'medium_opportunity_threshold' => max(1, min(98, (int) env('HORIZONTE_MEDIUM_THRESHOLD', 40))),

    'weights' => [
        'financial_pressure' => 0.30,
        'pedagogical_gap' => 0.25,
        'scale' => 0.20,
        'data_readiness' => 0.15,
        'benefit_scale' => 0.10,
    ],

    'saeb_disciplines' => ['LP', 'MAT', 'lp', 'mat', 'Língua Portuguesa', 'Matemática'],

    /*
    |--------------------------------------------------------------------------
    | Abastecimento quinzenal (rotina agendada + comando horizonte:fortnightly-feed)
    |--------------------------------------------------------------------------
    |
    | Sincroniza dados públicos nacionais para alimentar o mapa Horizonte:
    | FUNDEB (CSV receita FNDE), Censo matrículas, SAEB planilhas INEP, catálogo IBGE.
    |
    */

    'fortnightly_feed' => [
        'enabled' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_ENABLED', true), FILTER_VALIDATE_BOOL),

        'schedule' => [
            'enabled' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'days' => [1, 15],
            'time' => env('HORIZONTE_FORTNIGHTLY_FEED_TIME', '03:00'),
            'overlap_minutes' => max(60, (int) env('HORIZONTE_FORTNIGHTLY_FEED_OVERLAP_MINUTES', 2880)),
            'step_interval_minutes' => max(5, (int) env('HORIZONTE_FORTNIGHTLY_FEED_STEP_INTERVAL', 20)),
        ],

        'fundeb_years' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('HORIZONTE_FORTNIGHTLY_FUNDEB_YEARS', '')),
        ))),

        'fundeb_allow_empty' => filter_var(env('HORIZONTE_FORTNIGHTLY_FUNDEB_ALLOW_EMPTY', true), FILTER_VALIDATE_BOOL),

        'saeb_years' => null,

        'censo_skip_if_missing' => filter_var(env('HORIZONTE_FORTNIGHTLY_CENSO_SKIP_IF_MISSING', true), FILTER_VALIDATE_BOOL),
        'censo_allow_empty' => filter_var(env('HORIZONTE_FORTNIGHTLY_CENSO_ALLOW_EMPTY', false), FILTER_VALIDATE_BOOL),
        'snapshot_cache_ttl' => max(3600, (int) env('HORIZONTE_FORTNIGHTLY_SNAPSHOT_CACHE_TTL', 604800)),
        'pipeline_cache_ttl' => max(3600, (int) env('HORIZONTE_FORTNIGHTLY_PIPELINE_CACHE_TTL', 604800)),

        /** Executar uma fase por invocação Artisan (recomendado em produção). */
        'staged' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_STAGED', true), FILTER_VALIDATE_BOOL),
        'notify_phases' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_NOTIFY_PHASES', true), FILTER_VALIDATE_BOOL),
        'memory_limit' => env('HORIZONTE_FORTNIGHTLY_FEED_MEMORY_LIMIT', '512M'),
        'time_limit' => max(60, (int) env('HORIZONTE_FORTNIGHTLY_FEED_TIME_LIMIT', 900)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sistemas de gestão educacional (SGE) — registo opcional + catálogo local
    |--------------------------------------------------------------------------
    */

    'sge' => [
        'enabled' => filter_var(env('HORIZONTE_SGE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'registry_path' => env('HORIZONTE_SGE_REGISTRY_PATH', 'horizonte/sge_registry.json'),
        'registry_url' => env('HORIZONTE_SGE_REGISTRY_URL'),
        'registry_http_timeout' => max(5, min(60, (int) env('HORIZONTE_SGE_REGISTRY_HTTP_TIMEOUT', 15))),
        'registry_cache_ttl' => max(3600, (int) env('HORIZONTE_SGE_REGISTRY_CACHE_TTL', 604800)),
    ],

];
