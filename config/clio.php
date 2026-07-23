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

    'drive' => [
        /** API key Google Cloud (Drive API v3) — pastas/ficheiros «qualquer pessoa com o link». */
        'api_key' => env('CLIO_DRIVE_API_KEY', env('GOOGLE_API_KEY')),
        'max_files' => max(1, (int) env('CLIO_DRIVE_MAX_FILES', 500)),
        'max_file_mb' => max(1, (int) env('CLIO_DRIVE_MAX_FILE_MB', env('CLIO_UPLOAD_MAX_MB', 64))),
        'max_depth' => max(1, (int) env('CLIO_DRIVE_MAX_DEPTH', 4)),
        'request_timeout' => max(30, (int) env('CLIO_DRIVE_TIMEOUT', 120)),
        /** Acima deste nº de ficheiros relevantes, a importação corre em lotes com retomada. */
        'batch_threshold' => max(1, (int) env('CLIO_DRIVE_BATCH_THRESHOLD', 100)),
        /** Ficheiros por lote de download+ingest (evita timeout HTTP). */
        'batch_size' => max(1, (int) env('CLIO_DRIVE_BATCH_SIZE', 40)),
    ],

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
