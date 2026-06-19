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
        'financial_pressure' => 0.22,
        'pedagogical_gap' => 0.18,
        'scale' => 0.12,
        'social_demand' => 0.18,
        'transfer_dependency' => 0.10,
        'data_readiness' => 0.10,
        'benefit_scale' => 0.10,
    ],

    'sidra' => [
        'enabled' => filter_var(env('HORIZONTE_SIDRA_ENABLED', true), FILTER_VALIDATE_BOOL),
        'agregado' => env('HORIZONTE_SIDRA_AGREGADO', '9514'),
        'variavel' => env('HORIZONTE_SIDRA_VARIAVEL', '93'),
        'periodo' => max(2010, (int) env('HORIZONTE_SIDRA_PERIODO', 2022)),
        'ufs_per_step' => max(1, min(3, (int) env('HORIZONTE_SIDRA_UFS_PER_STEP', 1))),
        'base_url' => env('HORIZONTE_SIDRA_BASE_URL', 'https://servicodados.ibge.gov.br/api/v3/agregados'),
        'http_timeout' => max(15, (int) env('HORIZONTE_SIDRA_HTTP_TIMEOUT', 90)),
        'uf_n3_codes' => [
            'AC' => '12', 'AL' => '27', 'AM' => '13', 'AP' => '16', 'BA' => '29', 'CE' => '23',
            'DF' => '53', 'ES' => '32', 'GO' => '52', 'MA' => '21', 'MG' => '31', 'MS' => '50',
            'MT' => '51', 'PA' => '15', 'PB' => '25', 'PE' => '26', 'PI' => '22', 'PR' => '41',
            'RJ' => '33', 'RN' => '24', 'RO' => '11', 'RR' => '14', 'RS' => '43', 'SC' => '42',
            'SE' => '28', 'SP' => '35', 'TO' => '17',
        ],
    ],

    'cadunico_feed' => [
        'fill_api_gaps' => filter_var(env('HORIZONTE_CADUNICO_FILL_GAPS', false), FILTER_VALIDATE_BOOL),
    ],

    'saeb_disciplines' => ['LP', 'MAT', 'lp', 'mat', 'Língua Portuguesa', 'Matemática'],

    /*
    |--------------------------------------------------------------------------
    | Abastecimento bimestral (rotina agendada + comando horizonte:fortnightly-feed)
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
            /** Dia do mês (1–28) em que inicia cada ciclo bimestral. */
            'day' => max(1, min(28, (int) env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_DAY', 1))),
            /** Meses do ano (1–12) — por defeito ímpares = a cada 2 meses. */
            'months' => array_values(array_filter(array_map(
                'intval',
                explode(',', (string) env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_MONTHS', '1,3,5,7,9,11')),
            ))),
            'time' => env('HORIZONTE_FORTNIGHTLY_FEED_TIME', '03:00'),
            'overlap_minutes' => max(60, (int) env('HORIZONTE_FORTNIGHTLY_FEED_OVERLAP_MINUTES', 10080)),
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

        /** UFs aquecidas por invocação na fase IBGE (1 = mínimo de RAM). */
        'ibge_ufs_per_step' => max(1, min(27, (int) env('HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP', 1))),

        /** Anos SAEB importados por invocação na fase planilhas (1 = mínimo de RAM). */
        'saeb_years_per_step' => max(1, min(10, (int) env('HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP', 1))),
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

    /*
    |--------------------------------------------------------------------------
    | Mapa — volume de pontos e vista inicial
    |--------------------------------------------------------------------------
    |
    | Bases nacionais (>800 municípios) aplicam recorte inicial (UF prioritária +
    | prospectos) e limitam pontos desenhados para evitar travamento no zoom.
    |
    */

    'map_display' => [
        'heavy_threshold' => max(100, (int) env('HORIZONTE_MAP_HEAVY_THRESHOLD', 800)),
        'max_render_markers' => max(80, min(800, (int) env('HORIZONTE_MAP_MAX_RENDER', 400))),
    ],

];
