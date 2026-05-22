<?php

/**
 * Glossário admin / sync / conexões (pt-BR).
 */
return [
    'sync' => [
        'mass_weekly' => 'Sincronização massiva semanal',
        'admin_sync' => 'Fila de sincronização admin',
        'flush_queue' => 'Esvaziar fila de processamento',
    ],
    'connections' => [
        'test_by_city' => 'Testar conexão por município',
        'matrix_fundeb' => 'Matriz FUNDEB municipal',
    ],
    'censo' => [
        'index_matriculas' => 'Indexar matrículas Censo (microdados INEP)',
        'reencrypt_passwords' => 'Regravar senha padrão em todas as cidades (APP_KEY)',
    ],
    'glossary' => [
        'ieducar_schema' => 'Schema principal PostgreSQL (ex.: pmieducar)',
        'pgsql_search_path' => 'Ordem de schemas na conexão dinâmica',
        'app_key' => 'Chave Laravel para criptografar senhas das cidades',
    ],
];
