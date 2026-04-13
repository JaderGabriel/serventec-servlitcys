<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema (PostgreSQL / iEducar Portabilis)
    |--------------------------------------------------------------------------
    |
    | Ex.: pmieducar — as consultas passam a usar "pmieducar"."escola", etc.
    | Em MySQL sem schema, deixe vazio. Também pode definir tabela completa em
    | IEDUCAR_TABLE_* (ex.: cadastro.turno) e o prefixo não será aplicado.
    |
    */

    'schema' => env('IEDUCAR_SCHEMA', ''),

    /*
    |--------------------------------------------------------------------------
    | Nomes de tabelas
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'escola' => env('IEDUCAR_TABLE_ESCOLA', 'escola'),
        'ano_letivo' => env('IEDUCAR_TABLE_ANO_LETIVO', 'ano_letivo'),
        'curso' => env('IEDUCAR_TABLE_CURSO', 'curso'),
        'serie' => env('IEDUCAR_TABLE_SERIE', 'serie'),
        'turma' => env('IEDUCAR_TABLE_TURMA', 'turma'),
        'matricula' => env('IEDUCAR_TABLE_MATRICULA', 'matricula'),
        'nivel_ensino' => env('IEDUCAR_TABLE_NIVEL_ENSINO', 'nivel_ensino'),
        'turno' => env('IEDUCAR_TABLE_TURNO', 'turno'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Colunas por tabela
    |--------------------------------------------------------------------------
    */

    'columns' => [
        'escola' => [
            'id' => env('IEDUCAR_COL_ESCOLA_ID', 'cod_escola'),
            'name' => env('IEDUCAR_COL_ESCOLA_NAME', 'nome'),
            'active' => env('IEDUCAR_COL_ESCOLA_ACTIVE', 'ativo'),
        ],
        'ano_letivo' => [
            'year' => env('IEDUCAR_COL_ANO_LETIVO_ANO', 'ano'),
        ],
        'curso' => [
            'id' => env('IEDUCAR_COL_CURSO_ID', 'cod_curso'),
            'name' => env('IEDUCAR_COL_CURSO_NAME', 'nm_curso'),
        ],
        'serie' => [
            'id' => env('IEDUCAR_COL_SERIE_ID', 'cod_serie'),
            'name' => env('IEDUCAR_COL_SERIE_NAME', 'nm_serie'),
        ],
        'turma' => [
            'id' => env('IEDUCAR_COL_TURMA_ID', 'cod_turma'),
            'name' => env('IEDUCAR_COL_TURMA_NAME', 'nm_turma'),
            'year' => env('IEDUCAR_COL_TURMA_ANO', 'ano'),
        ],
        'matricula' => [
            'id' => env('IEDUCAR_COL_MATRICULA_ID', 'cod_matricula'),
            'turma' => env('IEDUCAR_COL_MATRICULA_TURMA', 'ref_cod_turma'),
            'ativo' => env('IEDUCAR_COL_MATRICULA_ATIVO', 'ativo'),
        ],
        'nivel_ensino' => [
            'id' => env('IEDUCAR_COL_NIVEL_ID', 'cod_nivel_ensino'),
            'name' => env('IEDUCAR_COL_NIVEL_NAME', 'nm_nivel'),
        ],
        'turno' => [
            'id' => env('IEDUCAR_COL_TURNO_ID', 'id'),
            'name' => env('IEDUCAR_COL_TURNO_NAME', 'nome'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtros nas listagens (SELECT para os combos)
    |--------------------------------------------------------------------------
    */

    'filters' => [
        'escola_only_active' => filter_var(env('IEDUCAR_FILTER_ESCOLA_ONLY_ACTIVE', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallbacks quando a tabela ano_letivo não existir ou falhar
    |--------------------------------------------------------------------------
    |
    | Muitas instalações iEducar guardam o ano na tabela turma (coluna ano).
    |
    */

    'fallbacks' => [
        'ano_letivo_from_turma' => filter_var(env('IEDUCAR_ANO_FALLBACK_FROM_TURMA', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultas SQL personalizadas (opcional)
    |--------------------------------------------------------------------------
    |
    | Se preenchidas, substituem o SELECT gerado automaticamente.
    | Devem devolver colunas: id, name (para escolas, cursos, etc.) ou ano (para anos).
    | Use identificadores conforme o motor da cidade (aspas em PostgreSQL).
    |
    */

    'sql' => [
        'ano_letivo_distinct' => env('IEDUCAR_SQL_ANO_LETIVO'),
        'escola_pairs' => env('IEDUCAR_SQL_ESCOLA'),
        'curso_pairs' => env('IEDUCAR_SQL_CURSO'),
        'serie_pairs' => env('IEDUCAR_SQL_SERIE'),
        'nivel_ensino_pairs' => env('IEDUCAR_SQL_NIVEL_ENSINO'),
        'turno_pairs' => env('IEDUCAR_SQL_TURNO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de segurança
    |--------------------------------------------------------------------------
    */

    'max_rows' => (int) env('IEDUCAR_MAX_ROWS', 2000),

];
