<?php

return [

    'enabled' => filter_var(env('CLIO_ENABLED', true), FILTER_VALIDATE_BOOL),

    'upload_max_mb' => max(1, (int) env('CLIO_UPLOAD_MAX_MB', 64)),

    'max_files_per_upload' => max(1, (int) env('CLIO_MAX_FILES_PER_UPLOAD', 200)),

    'retention_days' => max(1, (int) env('CLIO_RETENTION_DAYS', 90)),

    'queue' => env('CLIO_QUEUE', 'clio'),

    'layout_year_default' => (int) env('CLIO_LAYOUT_YEAR_DEFAULT', 2026),

    'disk' => env('CLIO_DISK', 'local'),

    'storage_root' => 'clio',

    'feature_promote' => filter_var(env('CLIO_PROMOTE_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Tipos de artefato reconhecidos no classificador de nomes. */
    'kinds' => [
        'acomp_coleta_1etapa',
        'relacao_aluno_escola',
        'relacao_turma_escola',
        'relacao_profissional_escola',
        'pacote_zip',
        'migracao_txt',
        'unknown',
    ],

];
