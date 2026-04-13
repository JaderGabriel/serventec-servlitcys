<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nomes de tabelas (MySQL / iEducar — Portabilis)
    |--------------------------------------------------------------------------
    |
    | Ajuste via .env se a sua instalação usar prefixos ou nomes diferentes.
    | As consultas usam estes valores com Query Builder (identificadores escapados).
    |
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
    | Limites de segurança
    |--------------------------------------------------------------------------
    */

    'max_rows' => (int) env('IEDUCAR_MAX_ROWS', 2000),

];
