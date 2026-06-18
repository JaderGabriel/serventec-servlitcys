<?php

return [

    'enabled' => filter_var(env('EDUCACENSO_DRY_RUN_ENABLED', true), FILTER_VALIDATE_BOOL),

    'layout_year_default' => (int) env('EDUCACENSO_LAYOUT_YEAR_DEFAULT', 2026),

    'upload_max_mb' => max(1, (int) env('EDUCACENSO_DRY_RUN_MAX_MB', 64)),

    'retention_days' => max(1, (int) env('EDUCACENSO_DRY_RUN_RETENTION_DAYS', 7)),

    'cache_ttl_hours' => max(1, (int) env('EDUCACENSO_ANALYSIS_CACHE_HOURS', 24)),

    /** Tipos de registro conhecidos na 1ª etapa (Matrícula inicial). */
    'record_types_stage1' => ['00', '10', '20', '30', '40', '50', '51', '60'],

    /**
     * Índice zero-based do código INEP da escola por tipo de registro (layout INEP típico).
     * Ajustável por exercício em LayoutRegistry futuro.
     */
    'school_inep_field_index' => [
        '00' => 1,
        '10' => 1,
        '20' => 1,
        '30' => 1,
        '40' => 1,
        '50' => 1,
        '51' => 1,
        '60' => 1,
    ],

    'tolerance_matricula_pct' => (float) env('IEDUCAR_DISC_CENSO_MAT_TOLERANCE_PCT', 5),

    'tolerance_matricula_min_diff' => (int) env('IEDUCAR_DISC_CENSO_MAT_MIN_DIFF', 10),

    /** Máximo de linhas detalhadas em memória; estatísticas agregadas mantêm-se completas. */
    'store_records_max' => max(0, (int) env('EDUCACENSO_STORE_RECORDS_MAX', 50_000)),

];
