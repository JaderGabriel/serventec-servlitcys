<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema (PostgreSQL / iEducar Portabilis, ex. 2.11)
    |--------------------------------------------------------------------------
    |
    | O iEducar usa vários schemas na mesma base (pmieducar, cadastro, public, …).
    | As consultas do painel qualificam tabelas via IeducarSchema; cadastro.pessoa,
    | cadastro.turno, etc. vêm de config com ponto e não são prefixados outra vez.
    |
    | Por cidade, defina o schema principal em cities.ieducar_schema (ex.: pmieducar).
    | CityDataConnection coloca esse schema primeiro no search_path PostgreSQL e junta
    | IEDUCAR_PGSQL_SEARCH_PATH sem duplicar — alinhado às instalações 2.x.
    |
    | Em MySQL sem schema, deixe vazio. Tabelas completas em IEDUCAR_TABLE_* (ex.:
    | cadastro.turno) não recebem prefixo do schema global.
    |
    */

    'schema' => env('IEDUCAR_SCHEMA', ''),

    /*
    |--------------------------------------------------------------------------
    | Schema padrão no PostgreSQL (Portabilis)
    |--------------------------------------------------------------------------
    |
    | Se a cidade usa pgsql e nem IEDUCAR_SCHEMA nem o campo ieducar_schema da
    | cidade estiverem definidos, usa-se este valor (típico: pmieducar).
    | Defina IEDUCAR_PGSQL_DEFAULT_SCHEMA vazio para desativar e usar só public.
    |
    */

    /*
     * Valor vazio no .env não deve remover o schema (Laravel devolve '' e quebra prefixos).
     * Use IEDUCAR_PGSQL_DEFAULT_SCHEMA=public se precisar só do search_path.
     */
    'pgsql_default_schema' => env('IEDUCAR_PGSQL_DEFAULT_SCHEMA') ?: 'pmieducar',

    /*
    |--------------------------------------------------------------------------
    | search_path na conexão PostgreSQL (bases Portabilis / iEducar 2.x)
    |--------------------------------------------------------------------------
    |
    | Lista de schemas adicionais após o schema principal da cidade (sempre primeiro
    | em runtime). Inclua cadastro para cadastro.* e public se necessário.
    |
    */

    'pgsql_search_path' => env('IEDUCAR_PGSQL_SEARCH_PATH', 'pmieducar,cadastro,public'),

    /*
    |--------------------------------------------------------------------------
    | Schemas PostgreSQL nomeados (placeholders em SQL customizado)
    |--------------------------------------------------------------------------
    |
    | Em IEDUCAR_SQL_* use {cadastro}, {relatorio}, {modules}, {public}, {matricula_situacao},
    | {matricula_turma}, {matricula}, {raca}, etc. (ver IeducarSqlPlaceholders), ou {schema}/{schema_main}
    | para o schema principal (pmieducar ou cities.ieducar_schema). Funções como
    | relatorio.get_nome_escola exigem o schema relatorio no search_path ou chamada qualificada.
    |
    | Situação da matrícula (Desempenho): IEDUCAR_TABLE_MATRICULA_SITUACAO, IEDUCAR_COL_MATRICULA_SITUACAO_PK,
    | IEDUCAR_COL_MATRICULA_SITUACAO_CODIGO_INEP. Inclusão raça/cor: IEDUCAR_TABLE_RACA,
    | IEDUCAR_SQL_INCLUSION_RACA, IEDUCAR_TABLE_RACA_FALLBACKS.
    |
    */

    'pgsql_schema_cadastro' => env('IEDUCAR_PGSQL_SCHEMA_CADASTRO', 'cadastro'),

    'pgsql_schema_relatorio' => env('IEDUCAR_PGSQL_SCHEMA_RELATORIO', 'relatorio'),

    'pgsql_schema_modules' => env('IEDUCAR_PGSQL_SCHEMA_MODULES', 'modules'),

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
        /** Catálogo de turnos (Portabilis 2.x: pmieducar.turma_turno com id, nome). */
        'turma_turno' => env('IEDUCAR_TABLE_TURMA_TURNO') ?: 'turma_turno',
        'matricula' => env('IEDUCAR_TABLE_MATRICULA', 'matricula'),
        'matricula_turma' => env('IEDUCAR_TABLE_MATRICULA_TURMA', 'matricula_turma'),
        'falta_aluno' => env('IEDUCAR_TABLE_FALTA_ALUNO', 'falta_aluno'),
        'nivel_ensino' => env('IEDUCAR_TABLE_NIVEL_ENSINO', 'nivel_ensino'),
        'turno' => env('IEDUCAR_TABLE_TURNO', 'cadastro.turno'),
        /** Tabelas adicionais a tentar para o filtro turno (PostgreSQL), separadas por vírgula. */
        'turno_fallbacks' => env('IEDUCAR_TABLE_TURNO_FALLBACKS', ''),
        'aluno' => env('IEDUCAR_TABLE_ALUNO', 'aluno'),
        'pessoa' => env('IEDUCAR_TABLE_PESSOA', 'cadastro.pessoa'),
        'raca' => env('IEDUCAR_TABLE_RACA', 'cadastro.raca'),
        /** Pivô pessoa física ↔ raça (Portabilis: cadastro.fisica_raca; aluno.ref_idpes → ref_idpes, ref_cod_raca → raca). */
        'fisica_raca' => env('IEDUCAR_TABLE_FISICA_RACA', 'cadastro.fisica_raca'),
        /** Tabelas adicionais para raça/cor (PostgreSQL), separadas por vírgula. */
        'raca_fallbacks' => env('IEDUCAR_TABLE_RACA_FALLBACKS', ''),
        /** Catálogo de situações (INEP); ligação matricula.ref_cod_matricula_situacao → cod_matricula_situacao. */
        'matricula_situacao' => env('IEDUCAR_TABLE_MATRICULA_SITUACAO', 'matricula_situacao'),
        /** Pivô aluno ↔ deficiência (Portabilis / iEducar 2.x). Se o BI usar outro schema, defina o nome qualificado; o painel também procura aluno_deficiencia via information_schema. */
        'aluno_deficiencia' => env('IEDUCAR_TABLE_ALUNO_DEFICIENCIA', 'aluno_deficiencia'),
        /** Catálogo de deficiências (nome legível para classificar síndromes / altas habilidades). */
        'deficiencia' => env('IEDUCAR_TABLE_DEFICIENCIA', 'cadastro.deficiencia'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabelas no MySQL (nome curto na base única)
    |--------------------------------------------------------------------------
    |
    | Quando o motor é MySQL, «cadastro.turno» etc. são mapeados para o nome local
    | (ex.: turno). Defina aqui se o nome for diferente do sufixo após o ponto.
    |
    */

    'tables_mysql' => [
        'turno' => env('IEDUCAR_MYSQL_TABLE_TURNO'),
        'pessoa' => env('IEDUCAR_MYSQL_TABLE_PESSOA'),
        'raca' => env('IEDUCAR_MYSQL_TABLE_RACA'),
        'fisica_raca' => env('IEDUCAR_MYSQL_TABLE_FISICA_RACA'),
        'matricula_situacao' => env('IEDUCAR_MYSQL_TABLE_MATRICULA_SITUACAO'),
        'aluno_deficiencia' => env('IEDUCAR_MYSQL_TABLE_ALUNO_DEFICIENCIA'),
        'deficiencia' => env('IEDUCAR_MYSQL_TABLE_DEFICIENCIA'),
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
            /** Ligação curso → nível de ensino (hierarquia Educacenso / INEP). */
            'nivel_ensino' => env('IEDUCAR_COL_CURSO_NIVEL', 'ref_cod_nivel_ensino'),
        ],
        'serie' => [
            'id' => env('IEDUCAR_COL_SERIE_ID', 'cod_serie'),
            'name' => env('IEDUCAR_COL_SERIE_NAME', 'nm_serie'),
            /** Ordenação tipo INEP / ano da etapa (inteiro na série escolar). */
            'sort' => env('IEDUCAR_COL_SERIE_SORT', 'serie'),
            /** Ordenação alternativa (educacenso) quando existir na tabela série. */
            'etapa_educacenso' => env('IEDUCAR_COL_SERIE_ETAPA_EDUCACENSO', 'etapa_educacenso'),
            /**
             * Idade limite superior para cálculo de distorção (INEP: atraso se idade > limite + 2).
             * Se vazio, o painel tenta idade_maxima, idade_final, etc.
             */
            'idade_limite_max' => env('IEDUCAR_COL_SERIE_IDADE_LIMITE_MAX', ''),
        ],
        'turma' => [
            'id' => env('IEDUCAR_COL_TURMA_ID', 'cod_turma'),
            'name' => env('IEDUCAR_COL_TURMA_NAME', 'nm_turma'),
            'year' => env('IEDUCAR_COL_TURMA_ANO', 'ano'),
            'escola' => env('IEDUCAR_COL_TURMA_ESCOLA', 'ref_cod_escola'),
            'curso' => env('IEDUCAR_COL_TURMA_CURSO', 'ref_cod_curso'),
            'serie' => env('IEDUCAR_COL_TURMA_SERIE', 'ref_cod_serie'),
            'turno' => env('IEDUCAR_COL_TURMA_TURNO', 'ref_cod_turno'),
            /** Capacidade da turma (vagas = max − matrículas activas). */
            'max_alunos' => env('IEDUCAR_COL_TURMA_MAX_ALUNO', 'max_aluno'),
        ],
        'matricula' => [
            'id' => env('IEDUCAR_COL_MATRICULA_ID', 'cod_matricula'),
            'turma' => env('IEDUCAR_COL_MATRICULA_TURMA', 'ref_cod_turma'),
            'aluno' => env('IEDUCAR_COL_MATRICULA_ALUNO', 'ref_cod_aluno'),
            'ativo' => env('IEDUCAR_COL_MATRICULA_ATIVO', 'ativo'),
            /** Ligação directa matrícula → escola (quando existir; ex.: ref_ref_cod_escola, ref_cod_escola). */
            'escola' => env('IEDUCAR_COL_MATRICULA_ESCOLA', ''),
            /** Série na matrícula (algumas bases; senão usa-se turma). */
            'serie' => env('IEDUCAR_COL_MATRICULA_SERIE', ''),
            /** Ano letivo na matrícula (ex.: ano). */
            'ano' => env('IEDUCAR_COL_MATRICULA_ANO', ''),
        ],
        /*
         * Pivô matrícula ↔ turma (PostgreSQL iEducar moderno). Usado quando matricula não tem ref_cod_turma.
         */
        'matricula_turma' => [
            'matricula' => env('IEDUCAR_COL_MATRICULA_TURMA_MATRICULA', 'ref_cod_matricula'),
            'turma' => env('IEDUCAR_COL_MATRICULA_TURMA_TURMA', 'ref_cod_turma'),
            'ativo' => env('IEDUCAR_COL_MATRICULA_TURMA_ATIVO', 'ativo'),
        ],
        'falta_aluno' => [
            'matricula' => env('IEDUCAR_COL_FALTA_MATRICULA', 'ref_cod_matricula'),
            'data' => env('IEDUCAR_COL_FALTA_DATA', 'data_falta'),
        ],
        'matricula_situacao' => [
            'aprovado' => env('IEDUCAR_COL_MATRICULA_APROVADO', 'aprovado'),
        ],
        /** Tabela matricula_situacao (catálogo): código INEP em «codigo». */
        'matricula_situacao_catalog' => [
            'id' => env('IEDUCAR_COL_MATRICULA_SITUACAO_PK', 'cod_matricula_situacao'),
            'codigo' => env('IEDUCAR_COL_MATRICULA_SITUACAO_CODIGO_INEP', 'codigo'),
        ],
        'aluno' => [
            'id' => env('IEDUCAR_COL_ALUNO_ID', 'cod_aluno'),
            'pessoa' => env('IEDUCAR_COL_ALUNO_PESSOA', 'ref_cod_pessoa'),
        ],
        'pessoa' => [
            'id' => env('IEDUCAR_COL_PESSOA_ID', 'idpes'),
            'raca' => env('IEDUCAR_COL_PESSOA_RACA', 'ref_cod_raca'),
            'sexo' => env('IEDUCAR_COL_PESSOA_SEXO', 'sexo'),
        ],
        'raca' => [
            'id' => env('IEDUCAR_COL_RACA_ID', 'cod_raca'),
            'name' => env('IEDUCAR_COL_RACA_NAME', 'nm_raca'),
        ],
        'aluno_deficiencia' => [
            'aluno' => env('IEDUCAR_COL_ALUNO_DEFICIENCIA_ALUNO', 'ref_cod_aluno'),
            'deficiencia' => env('IEDUCAR_COL_ALUNO_DEFICIENCIA_DEF', 'ref_cod_deficiencia'),
        ],
        'deficiencia' => [
            'id' => env('IEDUCAR_COL_DEFICIENCIA_ID', 'cod_deficiencia'),
            'name' => env('IEDUCAR_COL_DEFICIENCIA_NAME', 'nm_deficiencia'),
        ],
        'nivel_ensino' => [
            'id' => env('IEDUCAR_COL_NIVEL_ID', 'cod_nivel_ensino'),
            'name' => env('IEDUCAR_COL_NIVEL_NAME', 'nm_nivel'),
        ],
        'turno' => [
            'id' => env('IEDUCAR_COL_TURNO_ID', 'cod_turno'),
            /** Em PostgreSQL (Portabilis) o rótulo costuma ser nm_turno; o painel detecta automaticamente se nome/nm_turno falharem. */
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
    | Matrículas «activas» nos indicadores (KPIs / gráficos)
    |--------------------------------------------------------------------------
    |
    | Em várias bases i-Educar, «ativo» em matricula está NULL ou 0 enquanto
    | ref_cod_matricula_situacao aponta para situação INEP «em curso» (código 1).
    | Com incluir_situacao_inep activo, as contagens consideram também matrículas
    | cuja linha em matricula_situacao tem codigo INEP na lista (por defeito: 1).
    |
    */

    'matricula_indicadores' => [
        'incluir_situacao_inep' => filter_var(env('IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP', true), FILTER_VALIDATE_BOOL),
        /** Códigos INEP (matricula_situacao.codigo) tratados como matrícula activa em conjunto com ativo=1. */
        'situacao_inep_como_ativa' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_MATRICULA_SITUACAO_INEP_ATIVAS', '1'))), static fn (string $s): bool => $s !== '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultas SQL personalizadas (opcional)
    |--------------------------------------------------------------------------
    |
    | Se preenchidas, substituem o SELECT gerado automaticamente.
    | Devem devolver colunas: id e name (ou nome) para pares; ano para anos.
    |
    | Placeholders (substituídos por cidade/schema): {escola}, {curso}, {turno}, {serie},
    | {ano_letivo}, {turma}, {matricula}, {matricula_turma}, {aluno}, {pessoa}, {raca}, {falta_aluno};
    | schemas: {schema} ou {schema_main}, {cadastro}, {relatorio}, {modules}, {public}.
    |
    | Exemplo PostgreSQL (nome via função relatorio, comum na 2.11):
    | SELECT cod_escola AS id, relatorio.get_nome_escola(cod_escola) AS nome FROM {escola}
    | WHERE ativo = 1 ORDER BY nome
    |
    */

    'sql' => [
        'ano_letivo_distinct' => env('IEDUCAR_SQL_ANO_LETIVO'),
        'escola_pairs' => env('IEDUCAR_SQL_ESCOLA'),
        'curso_pairs' => env('IEDUCAR_SQL_CURSO'),
        'serie_pairs' => env('IEDUCAR_SQL_SERIE'),
        'nivel_ensino_pairs' => env('IEDUCAR_SQL_NIVEL_ENSINO'),
        'turno_pairs' => env('IEDUCAR_SQL_TURNO'),
        /** Cor/raça na aba Inclusão: mesmos placeholders que outras queries; deve filtrar matrículas activas como o painel. */
        'inclusion_raca' => env('IEDUCAR_SQL_INCLUSION_RACA'),
        'inclusion_extra' => env('IEDUCAR_SQL_INCLUSION_EXTRA'),
        /** Uma linha: coluna «pct» (0–100) ou «numerador» + «denominador» (matrículas). Placeholders como inclusion_raca. */
        'inclusion_gauge_deficiencia' => env('IEDUCAR_SQL_INCLUSION_GAUGE_DEF'),
        'inclusion_gauge_sindrome' => env('IEDUCAR_SQL_INCLUSION_GAUGE_SINDROME'),
        'inclusion_gauge_altas_habilidades' => env('IEDUCAR_SQL_INCLUSION_GAUGE_ALTAS_HABILIDADES'),
        /** Uma linha com índice ou percentual de distorção idade/série na rede (opcional). Ex.: SELECT 12.5 AS percentual */
        'distorcao_rede' => env('IEDUCAR_SQL_DISTORCAO_REDE'),
        /**
         * Várias linhas para o gráfico de distorção idade/série (opcional).
         * Colunas: label (ou name) e valor (ou value, quantidade, pct) — contagens ou percentuais por fatia.
         */
        'distorcao_rede_chart' => env('IEDUCAR_SQL_DISTORCAO_REDE_CHART'),
        /**
         * Ab Desempenho: indicadores IDEB, SAEB e metas PNE (dados externos ao registo de matrícula).
         * Várias linhas. Colunas reconhecidas: eixo|bloco|categoria (valores: ideb, saeb, pne), indicador|label|nome,
         * valor|value, referencia|ano, unidade, detalhe|observacao.
         */
        'performance_inep_indicadores' => env('IEDUCAR_SQL_PERFORMANCE_INEP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL: escolas via relatorio.get_nome_escola (iEducar Portabilis)
    |--------------------------------------------------------------------------
    |
    | Se não houver IEDUCAR_SQL_ESCOLA, em pgsql tenta-se SELECT com relatorio.get_nome_escola.
    | Desative se a função não existir na base (MySQL ignora).
    |
    */

    'pgsql_use_relatorio_escola_nome' => filter_var(
        env('IEDUCAR_PGSQL_USE_RELATORIO_ESCOLA_NOME', true),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL: turnos via SQL explícito (evita falhas com schema.tabela no builder)
    |--------------------------------------------------------------------------
    */

    'pgsql_use_raw_turno_sql' => filter_var(
        env('IEDUCAR_PGSQL_USE_RAW_TURNO_SQL', true),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | Paleta para gráficos (hex) — legenda e barras
    |--------------------------------------------------------------------------
    */

    'chart_colors' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'IEDUCAR_CHART_COLORS',
        '#6366f1,#22c55e,#f59e0b,#ec4899,#06b6d4,#a855f7,#14b8a6,#f97316,#84cc16,#e11d48'
    ))))),

    /*
    |--------------------------------------------------------------------------
    | Limites de segurança
    |--------------------------------------------------------------------------
    */

    'max_rows' => (int) env('IEDUCAR_MAX_ROWS', 2000),

];
