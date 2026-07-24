<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temp da aplicação (VPS)
    |--------------------------------------------------------------------------
    |
    | Pasta para downloads, extracções e staging — no volume da app, não em
    | /tmp do sistema operativo. Em produção pode apontar para um mount local.
    |
    */

    'path' => env('APP_TEMP_PATH', ''),

    /** Idade mínima (horas) para apagar ficheiros em tmp:purge. */
    'retention_hours' => max(1, (int) env('APP_TEMP_RETENTION_HOURS', 24)),

    'schedule' => [
        'enabled' => filter_var(env('APP_TEMP_PURGE_SCHEDULE', true), FILTER_VALIDATE_BOOL),
        'time' => (string) env('APP_TEMP_PURGE_TIME', '03:15'),
    ],

    /**
     * Pastas auxiliares (relativas a storage/app) com ficheiros processados/órfãos.
     * Não inclui caches reutilizáveis (CadÚnico CSV fresco, FUNDEB JSON, Clio artefacts).
     *
     * @var list<array{relative: string, hours: int, glob?: string}>
     */
    'extra_targets' => [
        [
            'relative' => 'temp',
            'hours' => max(1, (int) env('APP_TEMP_LEGACY_EXPORT_HOURS', 24)),
        ],
        [
            'relative' => 'saeb/microdados_cache',
            'hours' => max(1, (int) env('APP_TEMP_SAEB_EXTRACT_HOURS', 48)),
            'glob' => 'extract_*',
        ],
        [
            'relative' => 'saeb/planilhas',
            'hours' => max(1, (int) env('APP_TEMP_SAEB_PLANILHA_EXTRACT_HOURS', 48)),
            'glob' => 'extract_*',
        ],
        [
            'relative' => 'funding/tesouro-csv',
            'hours' => max(1, (int) env('APP_TEMP_TESOURO_TMP_HOURS', 24)),
            'glob' => '*.csv.tmp',
        ],
        [
            'relative' => 'horizonte/bundles',
            'hours' => max(1, (int) env('APP_TEMP_HORIZONTE_BUNDLE_HOURS', 48)),
            'glob' => '{build-*,import-*}',
        ],
        [
            'relative' => 'educacenso/uploads',
            'hours' => max(24, (int) env('EDUCACENSO_DRY_RUN_RETENTION_DAYS', 7) * 24),
        ],
        [
            'relative' => 'admin_sync/exports',
            'hours' => max(1, (int) env('APP_TEMP_ADMIN_SYNC_EXPORT_HOURS', 72)),
        ],
    ],

];
