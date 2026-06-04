<?php

use App\Support\InepGeoFallbackCsvPath;
use App\Support\InepMicrodadosCadastroEscolasPath;

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
    | URL pública do i-Educar por município
    |--------------------------------------------------------------------------
    |
    | Prioridade: cities.ieducar_app_url → IEDUCAR_APP_URLS (JSON city_id => URL)
    | → IEDUCAR_APP_URL_TEMPLATE com {city_id}, {slug}, {ibge}, {uf}.
    |
    */

    'app_urls' => array_filter(
        is_array($decoded = json_decode((string) env('IEDUCAR_APP_URLS', '{}'), true))
            ? $decoded
            : []
    ),

    'app_url_template' => trim((string) env('IEDUCAR_APP_URL_TEMPLATE', '')),

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
        /** Pessoa ↔ deficiência (Portabilis / BIS); prioridade na aba Inclusão em relação a aluno_deficiência. */
        'fisica_deficiencia' => env('IEDUCAR_TABLE_FISICA_DEFICIENCIA', ''),
        /** Pivô aluno/pessoa ↔ recurso de prova INEP (se vazio, detecção automática). */
        'aluno_recurso_prova' => env('IEDUCAR_TABLE_ALUNO_RECURSO_PROVA', ''),
        /** Catálogo de tipos de recurso de prova INEP. */
        'recurso_prova_catalogo' => env('IEDUCAR_TABLE_RECURSO_PROVA_CATALOGO', ''),
        /**
         * Educacenso (i-Educar 2.x PostgreSQL): ligação escola interna ↔ código INEP.
         * Ver migration legacy `create_modules_educacenso_cod_escola_table` no repositório portabilis/i-educar.
         * Colunas típicas: cod_escola, cod_escola_inep (PK composta).
         */
        'educacenso_cod_escola' => env('IEDUCAR_TABLE_EDUCACENSO_COD_ESCOLA', 'modules.educacenso_cod_escola'),
        /**
         * Catálogo opcional: situação de funcionamento / substatus da escola (Educacenso).
         * Se vazio, o painel tenta descobrir o nome da tabela via information_schema.
         */
        'escola_situacao_funcionamento' => env('IEDUCAR_TABLE_ESCOLA_SITUACAO_FUNCIONAMENTO', ''),
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
            /** Código INEP da escola (nem sempre existe na mesma tabela; deixe vazio se não houver). */
            'inep' => env('IEDUCAR_COL_ESCOLA_INEP', ''),
            /**
             * FK opcional para o catálogo de situação de funcionamento (substatus).
             * Se vazio, tentam-se nomes típicos (ref_cod_situacao_funcionamento, …).
             */
            'substatus_fk' => env('IEDUCAR_COL_ESCOLA_SUBSTATUS_FK', ''),
        ],
        /** Catálogo ligado por escola.substatus_fk (ou coluna descoberta). */
        'escola_situacao_funcionamento' => [
            'id' => env('IEDUCAR_COL_ESCOLA_SITFUNC_PK', ''),
            'name' => env('IEDUCAR_COL_ESCOLA_SITFUNC_NAME', ''),
        ],
        /** Tabela modules.educacenso_cod_escola (i-Educar 2.11): INEP não fica em pmieducar.escola. */
        'educacenso_cod_escola' => [
            'cod_escola' => env('IEDUCAR_COL_EDUCACENSO_COD_ESCOLA', 'cod_escola'),
            'cod_escola_inep' => env('IEDUCAR_COL_EDUCACENSO_COD_ESCOLA_INEP', 'cod_escola_inep'),
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
            /** Capacidade da turma (vagas = max − matrículas ativas). */
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
        'recurso_prova' => [
            'id' => env('IEDUCAR_COL_RECURSO_PROVA_ID', 'cod_recurso'),
            'name' => env('IEDUCAR_COL_RECURSO_PROVA_NAME', 'nm_recurso'),
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
    | Matrículas «ativas» nos indicadores (KPIs / gráficos)
    |--------------------------------------------------------------------------
    |
    | Em várias bases i-Educar, «ativo» em matricula está NULL ou 0 enquanto
    | ref_cod_matricula_situacao aponta para situação INEP «em curso» (código 1).
    | Com incluir_situacao_inep ativo, as contagens consideram também matrículas
    | cuja linha em matricula_situacao tem codigo INEP na lista (por defeito: 1).
    |
    */

    'matricula_indicadores' => [
        'incluir_situacao_inep' => filter_var(env('IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP', true), FILTER_VALIDATE_BOOL),
        /** Códigos INEP (matricula_situacao.codigo) tratados como matrícula ativa em conjunto com ativo=1. */
        'situacao_inep_como_ativa' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_MATRICULA_SITUACAO_INEP_ATIVAS', '1'))), static fn (string $s): bool => $s !== '')),
    ],

    /*
    | Distorção idade/série — motor com vários mecanismos (pessoa, fisica, matricula.ano, etc.)
    */
    'distorcao' => [
        /** Margem em anos sobre a idade máxima da série (critério INEP habitual: +2). */
        'margem_anos_inep' => (int) env('IEDUCAR_DISTORCAO_MARGEM_ANOS', 2),
        /**
         * Idade máxima etária por etapa Educacenso (serie.etapa_educacenso) quando faltar idade na série.
         * Chave = código da etapa; valor = idade limite em anos (31/03).
         */
        'etapa_educacenso_idade_maxima' => [
            '1' => 5,
            '2' => 6,
            '3' => 10,
            '4' => 11,
            '5' => 12,
            '6' => 14,
            '7' => 15,
            '8' => 16,
            '9' => 17,
            '10' => 17,
            '11' => 17,
            '12' => 17,
            '13' => 17,
            '14' => 17,
            '15' => 17,
            '16' => 17,
            '17' => 17,
            '18' => 17,
            '19' => 17,
            '20' => 17,
            '21' => 17,
            '22' => 22,
            '23' => 22,
            '24' => 22,
            '25' => 22,
            '26' => 22,
            '27' => 22,
            '28' => 22,
            '29' => 22,
            '30' => 22,
            '31' => 22,
            '32' => 22,
            '33' => 22,
            '34' => 22,
            '35' => 22,
            '36' => 22,
            '37' => 22,
            '38' => 22,
            '39' => 22,
            '40' => 22,
            '41' => 17,
            '42' => 17,
            '43' => 22,
            '44' => 22,
            '45' => 22,
            '46' => 22,
            '47' => 22,
            '48' => 22,
            '51' => 5,
            '52' => 6,
            '53' => 10,
            '54' => 11,
            '55' => 12,
            '56' => 14,
            '61' => 15,
            '62' => 16,
            '63' => 17,
            '64' => 17,
            '65' => 17,
            '66' => 17,
            '67' => 17,
            '68' => 17,
            '69' => 17,
            '70' => 17,
            '71' => 17,
            '72' => 17,
            '73' => 17,
            '74' => 17,
            '81' => 22,
            '82' => 22,
            '83' => 22,
            '84' => 22,
        ],
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
        /** Cor/raça na aba Inclusão: mesmos placeholders que outras queries; deve filtrar matrículas ativas como o painel. */
        'inclusion_raca' => env('IEDUCAR_SQL_INCLUSION_RACA'),
        'inclusion_extra' => env('IEDUCAR_SQL_INCLUSION_EXTRA'),
        /** Uma linha: coluna «pct» (0–100) ou «numerador» + «denominador» (matrículas). Placeholders como inclusion_raca. */
        'inclusion_gauge_deficiencia' => env('IEDUCAR_SQL_INCLUSION_GAUGE_DEF'),
        'inclusion_gauge_sindrome' => env('IEDUCAR_SQL_INCLUSION_GAUGE_SINDROME'),
        'inclusion_gauge_altas_habilidades' => env('IEDUCAR_SQL_INCLUSION_GAUGE_ALTAS_HABILIDADES'),
        /** Subquery: devolve coluna cod_aluno (ou ref_cod_aluno). Placeholders como inclusion_raca. */
        'inclusion_recurso_prova_alunos' => env('IEDUCAR_SQL_INCLUSION_RECURSO_PROVA_ALUNOS'),
        /** Várias linhas: nome, total — catálogo de recursos por matrículas no filtro. */
        'inclusion_recurso_prova_catalogo' => env('IEDUCAR_SQL_INCLUSION_RECURSO_PROVA_CATALOGO'),
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
    | Desempenho — SAEB (séries finais e preliminares)
    |--------------------------------------------------------------------------
    |
    | Os gráficos leem apenas o JSON em storage/app/public (Admin → Sincronizações → Pedagógicas).
    | Primeira carga: IEDUCAR_SAEB_IMPORT_URLS, ficheiros em storage ou template externo — o endpoint interno
    | GET /api/saeb/municipio/{ibge} só devolve dados depois de existir JSON em disco (não use como única fonte).
    | Importação oficial: IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com {ibge}, {uf}, {city_id} — uma URL por município
    | cadastrado (código IBGE em cities.ibge_municipio). Cada ponto deve ter city_ids (automático na importação oficial).
    | IEDUCAR_SAEB_IMPORT_URLS: URLs opcionais (JSON completo com «pontos»); não há fallback para ficheiros de exemplo.
    | Importação CSV: comando artisan saeb:import-csv (dados reais por município/escola a partir de CSV tabular).
    |
    */

    'saeb' => [
        'enabled' => filter_var(env('IEDUCAR_SAEB_SERIES_ENABLED', true), FILTER_VALIDATE_BOOL),
        /** Legado: os gráficos e a API usam a base (saeb_indicator_points); mantido só para referência em ferramentas externas. */
        'json_path' => env('IEDUCAR_SAEB_JSON_PATH', 'saeb/historico.json'),
        /** Lista separada por vírgulas: tenta em ordem até obter JSON com chave «pontos». Vazio = não há importação por URL. */
        'import_urls' => trim((string) env('IEDUCAR_SAEB_IMPORT_URLS', '')),
        /** Timeout global (teto); por tentativa usa-se o menor entre este e import_attempt_timeout_seconds. */
        'import_timeout_seconds' => (int) env('IEDUCAR_SAEB_IMPORT_TIMEOUT', 45),
        /** Timeout por pedido HTTP ao percorrer várias URLs (evita minutos de espera em páginas HTML). */
        'import_attempt_timeout_seconds' => (int) env('IEDUCAR_SAEB_IMPORT_ATTEMPT_TIMEOUT', 12),
        /**
         * URLs extra quando import_urls está vazio. Por defeito vazio.
         *
         * @var list<string>
         */
        'import_url_defaults' => [],
        /** URL com placeholders {ibge} (7 dígitos), {uf}, {city_id}. Vazio = APP_URL + /api/saeb/municipio/{ibge}.json (só útil se já houver JSON importado). */
        'official_url_template' => trim((string) env('IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE', '')),
        /** Antes do HTTP: ler storage (saeb/municipio/{ibge}.json ou corte do historico.json). */
        'official_use_internal_storage_first' => filter_var(env('IEDUCAR_SAEB_OFFICIAL_USE_INTERNAL', true), FILTER_VALIDATE_BOOL),
        /** Timeout por pedido HTTP na importação oficial por município. */
        'official_timeout_seconds' => (int) env('IEDUCAR_SAEB_OFFICIAL_TIMEOUT', 60),
        /**
         * Quando o template aponta para a API interna (APP_URL) e não há pontos na base,
         * descarrega microdados INEP (ZIP) e importa com INEP→cod_escola antes de falhar.
         */
        'official_auto_microdados_fallback' => filter_var(env('IEDUCAR_SAEB_OFFICIAL_AUTO_MICRODADOS', true), FILTER_VALIDATE_BOOL),
        /** Ano preferido do ZIP INEP no fallback (vazio = ano civil anterior). */
        'official_prefer_year' => env('IEDUCAR_SAEB_OFFICIAL_YEAR') !== null && env('IEDUCAR_SAEB_OFFICIAL_YEAR') !== ''
            ? max(2000, min(2100, (int) env('IEDUCAR_SAEB_OFFICIAL_YEAR')))
            : null,
        /** No fallback e na agregação oficial: mapear INEP da escola para cod_escola no i-Educar. */
        'official_resolve_inep' => filter_var(env('IEDUCAR_SAEB_OFFICIAL_RESOLVE_INEP', true), FILTER_VALIDATE_BOOL),
        /** Gravar storage/app/public/saeb/municipio/{ibge}.json após cada importação bem-sucedida (para GET /api/saeb/municipio/...). */
        'municipio_json_files_enabled' => filter_var(env('IEDUCAR_SAEB_MUNICIPIO_JSON_FILES', true), FILTER_VALIDATE_BOOL),
        /** Expor GET /api/saeb/municipio/{ibge}(.json) com dados agregados. */
        'public_api_enabled' => filter_var(env('IEDUCAR_SAEB_PUBLIC_API', true), FILTER_VALIDATE_BOOL),

        /**
         * Microdados SAEB (INEP ZIP) e CSV público (dados.gov / URL directa): download, filtro por cidades cadastradas, normalização.
         */
        'microdados_enabled' => filter_var(env('IEDUCAR_SAEB_MICRODADOS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'microdados_inep_zip_url_template' => (string) env(
            'IEDUCAR_SAEB_MICRODADOS_ZIP_URL',
            'https://download.inep.gov.br/microdados/microdados_saeb_{year}.zip'
        ),
        /**
         * URLs INEP por ano quando o template genérico devolve 404 (ex.: 2021 EF/Médio).
         *
         * @var array<int, string>
         */
        'microdados_inep_zip_url_overrides' => [
            2021 => 'https://download.inep.gov.br/microdados/microdados_saeb_2021_ensino_fundamental_e_medio.zip',
        ],
        /**
         * Planilhas oficiais (CO_MUNICIPIO IBGE) — `php artisan saeb:import-planilhas-inep`.
         *
         * @var array<int, string>
         */
        'planilha_resultados_urls' => [
            2023 => 'https://download.inep.gov.br/saeb/resultados/planilha_de_resultados_2023.rar',
            2021 => 'https://download.inep.gov.br/saeb/resultados/saeb_2021_brasil_estados_municipios.xlsx',
        ],
        /** Cache de planilhas INEP descarregadas (RAR/XLSX/XLSB). */
        'planilha_cache_path' => trim((string) env('IEDUCAR_SAEB_PLANILHA_CACHE_PATH', 'saeb/planilhas')) ?: 'saeb/planilhas',
        /**
         * Dependência administrativa preferida na aba Municípios (ex.: Municipal, Total - Federal, Estadual, Municipal e Privada).
         */
        'planilha_prefer_dependencia' => trim((string) env('IEDUCAR_SAEB_PLANILHA_DEPENDENCIA', 'Municipal')) ?: 'Municipal',
        'microdados_cache_path' => trim((string) env('IEDUCAR_SAEB_MICRODADOS_CACHE_PATH', 'saeb/microdados_cache')) ?: 'saeb/microdados_cache',
        'microdados_download_timeout_seconds' => (int) env('IEDUCAR_SAEB_MICRODADOS_TIMEOUT', 900),
        /** cURL/Guzzle: verificar certificado SSL (false só em dev com CA em falta). */
        'microdados_http_verify' => filter_var(env('IEDUCAR_SAEB_HTTP_VERIFY', true), FILTER_VALIDATE_BOOL),
        /** Caminho opcional para PEM (vazio = resources/certs/inep-download-chain.pem + CA do SO). */
        'microdados_http_ca_bundle' => trim((string) env('IEDUCAR_SAEB_HTTP_CA_BUNDLE', '')),
        /**
         * Último recurso: descarregar sem verificar SSL (apenas dev/rede fechada).
         * Ainda assim o ficheiro vem do INEP; o risco é MITM na transferência.
         */
        'microdados_http_insecure_fallback' => filter_var(env('IEDUCAR_SAEB_HTTP_INSECURE_FALLBACK', false), FILTER_VALIDATE_BOOL),
        /** Pontuação mínima (cabeçalho) para escolher o melhor .csv dentro do ZIP. */
        'microdados_csv_min_score' => (int) env('IEDUCAR_SAEB_MICRODADOS_CSV_MIN_SCORE', 6),
        /** Limite de linhas de saída canónica (protecção). */
        'microdados_max_rows' => (int) env('IEDUCAR_SAEB_MICRODADOS_MAX_ROWS', 5_000_000),
        /**
         * Mapeamento opcional de colunas INEP → chaves internas (ibge, year, year_alt, uf, disciplina, valor, lp_wide, mat_wide, etapa, inep_escola, preliminar_flag, tipo_resultado).
         *
         * @var array<string, list<string>>
         */
        'microdados_column_map' => [],
        /** URL opcional de CSV (dados.gov.br, CKAN) quando não se usa o ZIP INEP. */
        'microdados_opendata_csv_url' => trim((string) env('IEDUCAR_SAEB_OPENDATA_CSV_URL', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inclusão — palavras-chave (heurística) para AEE e segmentos
    |--------------------------------------------------------------------------
    |
    | Usado no cruzamento «aluno NEE em turma AEE vs outras turmas»: comparação
    | case-insensitive no nome da turma e do curso. Liste termos separados por
    | vírgula ou use as variáveis de ambiente com a mesma lista.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Discrepâncias e Erros — impacto financeiro indicativo (FUNDEB / VAAR / Censo)
    |--------------------------------------------------------------------------
    |
    | vaa_referencia_anual: ordem de grandeza do VAAF municipal (R$/aluno/ano) para estimar
    | perda por matrícula não contabilizada ou com cadastro inválido no Censo.
    | peso_por_check: multiplicador por tipo de discrepância (NEE e Censo costumam pesar mais).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Fontes públicas — extração e relatórios (consultoria)
    |--------------------------------------------------------------------------
    |
    | Catálogo de links oficiais (FNDE, Tesouro, INEP, Simec). extra_categories
    | permite acrescentar categorias no formato do PublicDataSourcesCatalog.
    |
    */

    'public_data_sources' => [
        'enabled' => filter_var(env('IEDUCAR_PUBLIC_SOURCES_ENABLED', true), FILTER_VALIDATE_BOOL),
        'extra_categories' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultoria — sinais operacionais e limiares
    |--------------------------------------------------------------------------
    */

    'consultoria' => [
        'rede_ociosidade_alerta_pct' => (float) env('IEDUCAR_CONSULTORIA_REDE_OCIOSIDADE_PCT', 15),
        'raca_nao_declarado_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_CONSULTORIA_RACA_NAO_DECLARADO',
            'não declarado,nao declarado,sem informação,sem informacao,ignorado,não informado,nao informado'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | FUNDEB — previsão de recursos e distribuição legal (indicativo)
    |--------------------------------------------------------------------------
    |
    | Usa matrículas do filtro × vaa_referencia_anual (mesma referência das discrepâncias).
    | complementacao_vaar_pct_base: ordem de grandeza opcional de complementação VAAR sobre a base.
    |
    */

    'fundeb' => [
        /*
         * VAAF por IBGE (7 dígitos). Valor numérico ou mapa por ano:
         * '2910800' => 5120.50
         * '2910800' => ['2024' => ['vaaf' => 5120.50, 'vaat' => 4800, 'complementacao_vaar' => 1200000]]
         */
        'vaaf_por_ibge' => [],

        /*
         * Importação via API (CKAN FNDE ou JSON público). Grava em fundeb_municipio_references.
         * IEDUCAR_FUNDEB_CKAN_RESOURCE_ID: ID do recurso CKAN (recomendado em produção).
         * IEDUCAR_FUNDEB_JSON_URL: URL HTTP(S) com {ibge}/{ano} OU padrão storage:// (cache em disco).
         * IEDUCAR_FUNDEB_CACHE_PATH: opcional; se vazio, usa storage:// de JSON_URL ou default abaixo.
         * Fluxo: lê cache → se falhar, CKAN/HTTP → grava JSON no cache → upsert na BD.
         */
        'open_data' => [
            'ckan_base_url' => (string) env('IEDUCAR_FUNDEB_CKAN_URL', 'https://www.fnde.gov.br/dadosabertos'),
            'resource_id' => (string) env('IEDUCAR_FUNDEB_CKAN_RESOURCE_ID', ''),
            'json_url' => (string) env('IEDUCAR_FUNDEB_JSON_URL', ''),
            'cache_path' => (string) env('IEDUCAR_FUNDEB_CACHE_PATH', ''),
            'search_query' => (string) env('IEDUCAR_FUNDEB_CKAN_SEARCH', 'fundeb vaaf municipio'),
            'timeout' => (int) env('IEDUCAR_FUNDEB_API_TIMEOUT', 30),
            'fields' => [
                'ibge' => [
                    'co_municipio', 'codigo_ibge', 'ibge_municipio', 'ibge', 'cod_municipio', 'codigo_municipio',
                    'cod_ibge', 'cd_municipio', 'id_municipio', 'codigoibge', 'cod_mun_ibge',
                ],
                'ano' => ['nu_ano', 'ano', 'ano_referencia', 'ano_letivo', 'exercicio'],
                'vaaf' => ['vaaf', 'vaa', 'vl_vaaf', 'valor_vaaf', 'valor_aluno_ano_fundeb'],
                'vaat' => ['vaat', 'vl_vaat', 'valor_vaat'],
                'complementacao_vaar' => ['complementacao_vaar', 'vaar', 'vl_complementacao_vaar', 'complementacao'],
            ],
            /*
             * Anos sincronizados: lista explícita OU intervalo (from/to).
             * IEDUCAR_FUNDEB_SYNC_YEARS=2020,2021,2022 — tem prioridade sobre o intervalo.
             * Se a lista estiver vazia: de SYNC_FROM_YEAR (default 2020) até SYNC_TO_YEAR (0 = ano anterior).
             * Importação completa (botão admin) pode somar anos já em cache/BD (sync_include_*).
             */
            'sync_years' => array_values(array_filter(array_map(
                static fn (string $y): int => (int) trim($y),
                explode(',', (string) env('IEDUCAR_FUNDEB_SYNC_YEARS', ''))
            ), static fn (int $y): bool => $y >= 2000)),
            'sync_from_year' => (int) env('IEDUCAR_FUNDEB_SYNC_FROM_YEAR', 2020),
            'sync_to_year' => (int) env('IEDUCAR_FUNDEB_SYNC_TO_YEAR', 0),
            'sync_max_years' => max(1, (int) env('IEDUCAR_FUNDEB_SYNC_MAX_YEARS', 30)),
            'sync_include_cached_years' => filter_var(env('IEDUCAR_FUNDEB_SYNC_INCLUDE_CACHED', true), FILTER_VALIDATE_BOOL),
            'sync_include_database_years' => filter_var(env('IEDUCAR_FUNDEB_SYNC_INCLUDE_DATABASE', true), FILTER_VALIDATE_BOOL),
            'sync_on_city_save' => filter_var(env('IEDUCAR_FUNDEB_SYNC_ON_CITY_SAVE', true), FILTER_VALIDATE_BOOL),
            /*
             * Quando cache/CKAN/JSON remoto não retornam VAAF municipal, grava piso nacional
             * (referência para planejamento — substitua quando houver dado oficial por IBGE).
             */
            /*
             * CSV «Receita total do Fundeb por ente federado» (Portaria FNDE em gov.br).
             * Descoberta automática em fundeb/{ano}; override por ano, ex.:
             * 'fnde_receita_csv_urls' => [2025 => 'https://www.gov.br/fnde/.../1.Receitatotal....csv']
             */
            'fnde_receita_csv_urls' => [
                2026 => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2026-1/publicacoes-2026/2-publicacao/1-receita-total-do-fundeb-por-ente-federado.csv',
            ],

            /** PDF «Valor aluno/ano e receita anual prevista» por UF/DF (Consultas FNDE). */
            'fnde_estado_vaaf_enabled' => filter_var(env('IEDUCAR_FUNDEB_ESTADO_VAAF_ENABLED', true), FILTER_VALIDATE_BOOL),
            'fnde_estado_vaaf_on_import' => filter_var(env('IEDUCAR_FUNDEB_ESTADO_VAAF_ON_IMPORT', true), FILTER_VALIDATE_BOOL),
            'fnde_estado_vaaf_pdf_urls' => [],

            /** VAAF estimado = receita total FNDE ÷ matrículas i-Educar (limites de sanidade). */
            'vaaf_estimate_min' => (float) env('IEDUCAR_FUNDEB_VAAF_ESTIMATE_MIN', 2500),
            'vaaf_estimate_max' => (float) env('IEDUCAR_FUNDEB_VAAF_ESTIMATE_MAX', 18000),
            /** Usar matrículas do Censo INEP quando i-Educar = 0 (estimativa VAAF portaria). */
            'vaaf_use_censo_matriculas_fallback' => filter_var(env('IEDUCAR_FUNDEB_VAAF_CENSO_FALLBACK', true), FILTER_VALIDATE_BOOL),
            /** Anos à frente no perfil de planejamento (ex.: 1 = ano civil + próximo). */
            'planning_years_ahead' => max(0, (int) env('IEDUCAR_FUNDEB_PLANNING_YEARS_AHEAD', 1)),
            'planning_include_suggested_import_year' => filter_var(env('IEDUCAR_FUNDEB_PLANNING_INCLUDE_IMPORT_YEAR', true), FILTER_VALIDATE_BOOL),

            'national_floor' => [
                'enabled' => filter_var(env('IEDUCAR_FUNDEB_NATIONAL_FLOOR', true), FILTER_VALIDATE_BOOL),
                /** Se false, importação falha em vez de gravar piso nacional (recomendado). */
                'write_on_import' => filter_var(env('IEDUCAR_FUNDEB_NATIONAL_FLOOR_ON_IMPORT', false), FILTER_VALIDATE_BOOL),
                'vaaf_by_year' => [
                    2024 => (float) env('IEDUCAR_FUNDEB_NATIONAL_VAAF_2024', 0) ?: null,
                    2025 => (float) env('IEDUCAR_FUNDEB_NATIONAL_VAAF_2025', 0) ?: null,
                ],
            ],
        ],

        'aviso_previsao' => (string) env(
            'IEDUCAR_FUNDEB_AVISO_PREVISAO',
            'Previsão = matrículas ativas no filtro × valor-aluno/ano (VAAF municipal quando importado; senão prévia federal ou piso em IEDUCAR_DISC_VAA_REFERENCIA, padrão R$ 4.500/aluno/ano). Não inclui receitas próprias, ICMS/ISS repassados nem complementação VAAR oficial — consulte FNDE, Simec e Tesouro Transparente.'
        ),
        'complementacao_vaar_pct_base' => (float) env('IEDUCAR_FUNDEB_VAAR_PCT_BASE', 0),
        /** Quando true e complementacao_vaar importada existir, substitui o % fixo na previsão FUNDEB. */
        'use_imported_vaar' => filter_var(env('IEDUCAR_FUNDEB_USE_IMPORTED_VAAR', true), FILTER_VALIDATE_BOOL),
        'distribuicao_legal' => [
            'referencia' => 'Lei nº 14.113/2020, art. 31 — aplicação mínima anual dos recursos do FUNDEB.',
            'nota' => 'Pisos para planejamento e controle social; a execução orçamentária deve respeitar normas do fundo e prestação de contas.',
            'pisos' => [
                [
                    'id' => 'remuneracao',
                    'titulo' => 'Remuneração dos profissionais da educação básica',
                    'descricao' => 'Folha de pagamento e encargos dos profissionais da educação básica da rede.',
                    'percentual_minimo' => 70,
                ],
                [
                    'id' => 'docentes_efetivos',
                    'titulo' => 'Docentes em efetivo exercício',
                    'descricao' => 'Parcela mínima paga a docentes em efetivo exercício na educação básica.',
                    'percentual_minimo' => 49,
                    'nota' => 'Corresponde a no mínimo 70% do montante de remuneração (49% do total anual do FUNDEB).',
                ],
                [
                    'id' => 'qualidade',
                    'titulo' => 'Demais despesas de manutenção e desenvolvimento',
                    'descricao' => 'Infraestrutura, materiais didáticos, formação e demais despesas permitidas.',
                    'percentual_maximo' => 30,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Finanças — Tempo Real (repasses × expectativa FUNDEB)
    |--------------------------------------------------------------------------
    */

    'finance_realtime' => [
        'enabled' => filter_var(env('IEDUCAR_FINANCE_REALTIME_ENABLED', true), FILTER_VALIDATE_BOOL),
        'alert_threshold_pct' => max(1.0, (float) env('IEDUCAR_FINANCE_REALTIME_ALERT_PCT', 15)),
        'program_keywords' => ['fundeb', 'fnde', 'educacao basica', 'educação básica', 'manutencao', 'manutenção', 'salario educacao'],
        'sources_note' => (string) env('IEDUCAR_FINANCE_REALTIME_SOURCES_NOTE', ''),
        'aviso' => (string) env(
            'IEDUCAR_FINANCE_REALTIME_AVISO',
            'Comparação indicativa entre repasses públicos importados e matrículas × VAAF. Não substitui extrato bancário nem prestação de contas no FNDE/Simec.'
        ),
        'bb_enabled' => filter_var(env('IEDUCAR_BB_OPEN_FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'bb_client_id' => (string) env('IEDUCAR_BB_OPEN_FINANCE_CLIENT_ID', ''),
        'bb_base_url' => (string) env('IEDUCAR_BB_OPEN_FINANCE_BASE_URL', 'https://api.bb.com.br'),
    ],

    'discrepancies' => [
        'vaa_referencia_anual' => (float) env('IEDUCAR_DISC_VAA_REFERENCIA', 4500),
        'nee_benchmark_pct_min' => (float) env('IEDUCAR_DISC_NEE_BENCHMARK_PCT', 1.5),
        'min_matriculas_nee_benchmark' => (int) env('IEDUCAR_DISC_NEE_MIN_MAT', 80),
        'aviso_financeiro' => (string) env(
            'IEDUCAR_DISC_AVISO_FINANCEIRO',
            'Estimativa indicativa: ocorrências × (VAAF do cálculo × peso por tipo). Sem VAAF municipal importado, usa-se prévia federal ou IEDUCAR_DISC_VAA_REFERENCIA (padrão R$ 4.500/aluno/ano). Não substitui cálculo FNDE/Simec nem complementação VAAR oficial.'
        ),
        'peso_por_check' => [
            'sem_raca' => 0.35,
            'sem_sexo' => 0.35,
            'sem_data_nascimento' => 0.45,
            'nee_sem_aee' => 1.2,
            'aee_sem_nee' => 1.0,
            'nee_subnotificacao' => 1.5,
            'escola_sem_inep' => 1.8,
            'escola_inativa_matricula' => 1.6,
            'escola_sem_geo' => 0.5,
            'recurso_prova_sem_nee' => 1.1,
            'nee_sem_recurso_prova' => 0.9,
            'recurso_prova_incompativel' => 1.0,
            'matricula_duplicada' => 1.4,
            'matricula_situacao_invalida' => 1.3,
            'distorcao_idade_serie' => 0.4,
            'rede_vagas_ociosas' => 0.25,
            'matricula_censo_vs_ieducar' => 1.6,
            /** Abandono + remanejamento (aba Desempenho) — eixo VAAR-indicadores. */
            'fluxo_abandono_remanejamento' => (float) env('IEDUCAR_DISC_PESO_FLUXO_ABANDONO', 0.45),
            /** Registos agregados em falta_aluno (aba Frequência), escala por lote de faltas. */
            'faltas_registro_mensal' => (float) env('IEDUCAR_DISC_PESO_FALTAS', 0.08),
            /** Tabela/colunas de falta_aluno inacessíveis — risco PNAE/transporte sem trilha. */
            'frequencia_sem_base_faltas' => (float) env('IEDUCAR_DISC_PESO_FREQ_SEM_BASE', 0.35),
            /** Base OK mas sem lançamentos no filtro — matrículas sem frequência registada. */
            'frequencia_nao_lancada' => (float) env('IEDUCAR_DISC_PESO_FREQ_NAO_LANCADA', 0.22),
        ],
        'censo_matricula_tolerance_pct' => (float) env('IEDUCAR_DISC_CENSO_MAT_TOLERANCE_PCT', 5),
        'censo_matricula_min_diff' => (int) env('IEDUCAR_DISC_CENSO_MAT_MIN_DIFF', 10),
        'funding_pillars' => [
            [
                'id' => 'fundeb-base',
                'titulo' => 'FUNDEB — matrícula no Censo',
                'descricao' => 'Financiamento básico da educação vinculado a matrículas válidas declaradas no Censo Escolar (Educacenso). Cadastro incompleto ou inconsistente pode reduzir repasse ou gerar glosa.',
            ],
            [
                'id' => 'vaar-inclusao',
                'titulo' => 'VAAR — Inclusão e equidade',
                'descricao' => 'Condicionalidades ligadas a educação especial, equidade (raça/cor, gênero) e políticas de inclusão. Subnotificação de NEE ou ausência de AEE impacta comprovação.',
            ],
            [
                'id' => 'vaar-indicadores',
                'titulo' => 'VAAR — Indicadores (INEP)',
                'descricao' => 'Metas de aprendizagem e indicadores educacionais (IDEB, SAEB). Escola sem INEP ou unidade inativa distorce a rede.',
            ],
            [
                'id' => 'pnae-transporte',
                'titulo' => 'Programas complementares',
                'descricao' => 'PNAE, transporte e outros repasses usam cadastro escolar e aluno; erros de situação da matrícula ou duplicidade afetam planejamento e custos.',
            ],
        ],
    ],

    'inclusion' => [
        /**
         * Contar também matrículas activas em turma/curso identificados como AEE (palavras-chave),
         * além do cadastro em fisica_deficiencia / aluno_deficiencia — evita omitir educação especial só na oferta AEE.
         */
        'nee_incluir_turma_aee' => filter_var(env('IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE', true), FILTER_VALIDATE_BOOL),

        'recurso_prova_exigir_com_nee' => filter_var(env('IEDUCAR_INCLUSION_RECURSO_EXIGIR_COM_NEE', false), FILTER_VALIDATE_BOOL),
        'recurso_prova_column_patterns' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INCLUSION_RECURSO_PROVA_COLUMN_PATTERNS',
            '%recurso%,%prova%,%oculos%,%lupa%,%ledor%,%interprete%,%braille%'
        ))))),
        'recurso_alto_impacto_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INCLUSION_RECURSO_ALTO_IMPACTO',
            'ledor,intérprete,interprete,braille,surdo,cegueira,prova em braille,libras'
        ))))),
        'recurso_deficiencia_incompatibilidades' => [
            [
                'recurso' => ['ledor', 'braille', 'prova ampliada', 'fonte ampliada'],
                'deficiencia' => ['visual', 'cegueira', 'baixa vis', 'visão'],
            ],
            [
                'recurso' => ['intérprete', 'interprete', 'libras', 'surdo'],
                'deficiencia' => ['audit', 'surdez', 'surdo'],
            ],
        ],
        'aee_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INCLUSION_AEE_KEYWORDS',
            'aee,atendimento educacional especializado,atendimento especializado,sala de recurso,sala multifuncional,multifuncional'
        ))))),
        'eja_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INCLUSION_EJA_KEYWORDS',
            'eja,educação de jovens e adultos,educacao de jovens e adultos'
        ))))),
        'infantil_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INCLUSION_INFANTIL_KEYWORDS',
            'educação infantil,educacao infantil,creche,berçário,bercario,pré-escola,pre-escola,infantil'
        ))))),

        /**
         * Ponderação «educação especial» no FUNDEB (Lei nº 14.113/2020, Anexo, alínea n): 1,20.
         * O impacto na aba Inclusão usa só o incremento (peso − 1) × VAAF × matrículas NEE.
         */
        'fundeb_peso_educacao_especial' => max(1.0, (float) env('IEDUCAR_INCLUSION_FUNDEB_PESO_EDUCACAO_ESPECIAL', 1.2)),

        /**
         * Cor/raça Educacenso (INEP) — ordem de exibição nos gráficos; unido ao catálogo cadastro.raca da base.
         */
        'raca_mec_catalog' => [
            'Não declarada',
            'Branca',
            'Preta',
            'Parda',
            'Amarela',
            'Indígena',
            'Quilombola',
        ],

        /**
         * Tipos de deficiência/NEE Educacenso (referência MEC) — unidos a cadastro.deficiencia (i-Educar).
         */
        'deficiencia_mec_catalog' => [
            'Cegueira',
            'Baixa visão',
            'Surdez',
            'Deficiência auditiva',
            'Surdocegueira',
            'Deficiência física',
            'Deficiência intelectual',
            'Deficiência múltipla',
            'Transtorno do espectro autista',
            'Altas habilidades/Superdotação',
            'Síndrome de Down',
            'Discalculia',
            'Disgrafia',
            'Dislalia',
            'Dislexia',
            'TDAH',
            'TPAC',
        ],

        /**
         * Alias de designação local → rótulo MEC/INEP (chave e valor = texto como em cadastro.deficiencia).
         * Usado no catálogo unificado NEE (gráfico agrupado + detalhado).
         */
        'deficiencia_label_aliases' => [
            // 'TEA' => 'Transtorno do espectro autista',
            // 'Autismo clássico' => 'Transtorno do espectro autista',
            // 'AH' => 'Altas habilidades/Superdotação',
        ],

        /**
         * Tipos frequentes no i-Educar que não são campo próprio do Censo/INEP — exibir como «complementar»
         * (podem ser mapeados para deficiência múltipla, intelectual ou outro código oficial na exportação).
         */
        'deficiencia_complementar_catalog' => [
            'Discalculia',
            'Disgrafia',
            'Dislalia',
            'Dislexia',
            'TDAH',
            'TPAC',
        ],
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

    /** Telefone da escola via relatorio.get_telefone_escola(cod_escola) quando ainda vazio (Portabilis). */
    'pgsql_use_relatorio_get_telefone_escola' => filter_var(
        env('IEDUCAR_PGSQL_USE_RELATORIO_GET_TELEFONE_ESCOLA', true),
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
    | Georreferenciação INEP (Catálogo de Escolas — dados abertos)
    |--------------------------------------------------------------------------
    |
    | Quando a tabela escola não tem latitude/longitude, o painel pode consultar
    | um serviço ArcGIS público (query por Código_INEP) usando o código INEP da
    | escola (coluna detectada automaticamente: codigo_inep, inep, …).
    |
    | Atenção: o URL padrão abaixo aponta para uma camada com poucos milhares de
    | feições (subconjunto / recorte — não cobre todas as escolas do país). Se o
    | INEP publicar outra Feature Layer nacional, defina IEDUCAR_INEP_ARCGIS_QUERY_URL.
    |
    */

    'inep_geocoding' => [
        /** INEP para coordenadas no mapa (ArcGIS e fallbacks). Com false, ainda é possível enriquecer o cartão se `enrich_markers_with_inep_catalog` estiver ativo. */
        'enabled' => filter_var(env('IEDUCAR_INEP_GEOCODING_ENABLED', true), FILTER_VALIDATE_BOOL),
        /**
         * Uma (1) URL ou uma lista (em ordem de tentativa) de URLs de query ArcGIS.
         *
         * - `IEDUCAR_INEP_ARCGIS_QUERY_URLS`: separado por vírgula (primeira URL tem prioridade)
         * - fallback: `IEDUCAR_INEP_ARCGIS_QUERY_URL` (legado)
         *
         * Nota: a URL padrão do repositório é um recorte pequeno; configure uma camada nacional para cobrir todas as escolas.
         */
        'arcgis_layer_query_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_INEP_ARCGIS_QUERY_URLS',
            (string) env(
                'IEDUCAR_INEP_ARCGIS_QUERY_URL',
                'https://services3.arcgis.com/ba17q0p2zHwzRK3B/arcgis/rest/services/inep_escolas_fmt_250609_geocode/FeatureServer/1/query'
            )
        ))))),
        'cache_ttl_seconds' => max(3600, (int) env('IEDUCAR_INEP_GEO_CACHE_TTL', 2592000)),
        'batch_size' => max(5, min(100, (int) env('IEDUCAR_INEP_GEO_BATCH_SIZE', 40))),
        /** Quando true, consulta o ArcGIS por INEP para preencher o catálogo no popup (mesmo com coordenadas só na base). */
        'enrich_markers_with_inep_catalog' => filter_var(env('IEDUCAR_INEP_ENRICH_MAP_MARKERS', true), FILTER_VALIDATE_BOOL),
        /** @deprecated Legado; o modal usa inep_portal_escola_url_template (Portal IDEB / INEP). */
        'qedu_escola_base_url' => rtrim((string) env(
            'IEDUCAR_QEDU_ESCOLA_BASE_URL',
            'https://www.qedu.org.br/escola'
        ), '/'),
        /**
         * URL público INEP para consulta por escola (painel pedagógico / IDEB por escola).
         * Use o placeholder {inep} para o código INEP (ex.: …/resultado/escola/{inep}).
         * Se não contiver {inep}, o mesmo URL é usado para todas (página geral do INEP).
         */
        'inep_portal_escola_url_template' => (string) env(
            'IEDUCAR_INEP_PORTAL_ESCOLA_URL_TEMPLATE',
            'https://www.qedu.org.br/escola/{inep}'
        ),
        /**
         * Fallback offline: CSV com coordenadas apenas para INEPs já presentes em school_unit_geos
         * das cidades forAnalytics (exportado via app:export-inep-geo-fallback-csv).
         * O lookup ignora linhas cujo INEP não esteja na whitelist local.
         *
         * Caminho por defeito no disco public do Laravel (`storage/app/public`), relativo a esse root
         * (ex.: `inep_geo_fallback.csv`). Caminho absoluto permitido. Legado: `app/...` relativo a `storage/`.
         *
         * @see InepGeoFallbackCsvPath
         */
        'fallback_csv_enabled' => filter_var(env('IEDUCAR_INEP_GEO_FALLBACK_CSV_ENABLED', true), FILTER_VALIDATE_BOOL),
        'fallback_csv_path' => env('IEDUCAR_INEP_GEO_FALLBACK_CSV', 'inep_geo_fallback.csv'),
        /**
         * CSV de escolas extraído do ZIP oficial do Censo (`microdados_ed_basica_*.csv`) ou ficheiro
         * legado `MICRODADOS_CADASTRO_ESCOLAS_*.csv`. Glob escolhe o ano mais recente no nome.
         *
         * @see InepMicrodadosCadastroEscolasPath
         */
        'microdados_cadastro_escolas_path' => env(
            'IEDUCAR_INEP_MICRODADOS_CADASTRO_ESCOLAS',
            'inep/microdados_ed_basica_*.csv'
        ),
        /** Descarregar automaticamente o ZIP do INEP quando o CSV não existir (import/pipeline). */
        'microdados_fetch_enabled' => filter_var(env('IEDUCAR_INEP_MICRODADOS_FETCH', true), FILTER_VALIDATE_BOOL),
        /** URL do ZIP (placeholder {year}). HTTP costuma ser mais fiável que HTTPS no mirror INEP. */
        'microdados_download_url_template' => (string) env(
            'IEDUCAR_INEP_MICRODADOS_DOWNLOAD_URL',
            'http://download.inep.gov.br/dados_abertos/microdados_censo_escolar_{year}.zip'
        ),
        /** Ano fixo do Censo (vazio = detetar o ZIP mais recente disponível). */
        'microdados_download_year' => env('IEDUCAR_INEP_MICRODADOS_YEAR', ''),
        /** No lookup do mapa/catálogo, ler coordenadas do CSV local (se existir colunas lat/lng). */
        'microdados_runtime_lookup_enabled' => filter_var(env('IEDUCAR_INEP_MICRODADOS_RUNTIME_LOOKUP', true), FILTER_VALIDATE_BOOL),
        /** Modal do mapa: mostrar município/UF/região (Censo) quando não há endereço no cadastro i-Educar. */
        'censo_geo_agg_modal_enabled' => filter_var(env('IEDUCAR_INEP_CENSO_GEO_AGG_MODAL', true), FILTER_VALIDATE_BOOL),
        /** Após import do microdados, reindexar tabela `inep_censo_escola_geo_agg` (pode demorar em ficheiros nacionais). */
        'censo_geo_agg_index_on_import' => filter_var(env('IEDUCAR_INEP_CENSO_GEO_AGG_INDEX_ON_IMPORT', true), FILTER_VALIDATE_BOOL),
        'censo_matriculas_index_on_import' => filter_var(env('IEDUCAR_INEP_CENSO_MATRICULAS_INDEX_ON_IMPORT', true), FILTER_VALIDATE_BOOL),
        /** Distância mínima (Haversine) para marcar divergência entre i-Educar e coordenada oficial (INEP). */
        'divergence_threshold_meters' => max(1.0, (float) env('IEDUCAR_GEO_DIVERGENCE_THRESHOLD_M', 100)),
        /**
         * Quando o ArcGIS não devolve o INEP, reutiliza coordenadas já guardadas em school_unit_geos (outra cidade/mesmo INEP).
         */
        'school_unit_geo_inep_fallback_enabled' => filter_var(env('IEDUCAR_INEP_GEO_SCHOOL_UNIT_GEO_FALLBACK', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de segurança
    |--------------------------------------------------------------------------
    */

    'max_rows' => (int) env('IEDUCAR_MAX_ROWS', 2000),

    /*
    |--------------------------------------------------------------------------
    | Demais financiamentos (PNAE, PNATE, PDDE, etc.)
    |--------------------------------------------------------------------------
    |
    | Programas exibidos na aba «Demais financiamentos». Cada item pode declarar
    | colunas candidatas em matricula, aluno ou escola para leitura automática.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Repasses observados (Tesouro / Transparência) — snapshots locais
    |--------------------------------------------------------------------------
    */

    'funding' => [
        'transfers' => [
            'enabled' => filter_var(env('IEDUCAR_FUNDING_TRANSFERS_ENABLED', true), FILTER_VALIDATE_BOOL),
            'timeout' => max(5, (int) env('IEDUCAR_FUNDING_TRANSFERS_TIMEOUT', 20)),
            'historical_years' => max(1, (int) env('IEDUCAR_FUNDING_TRANSFERS_HISTORICAL_YEARS', 5)),
            'job_timeout' => max(60, (int) env('IEDUCAR_FUNDING_TRANSFERS_JOB_TIMEOUT', 600)),
            'program_keywords' => [
                'fundeb' => ['fundeb', 'fnde', 'salario-educacao', 'salário-educação'],
                'pnae' => ['pnae', 'alimentacao', 'alimentação', 'merenda'],
                'pnate' => ['pnate', 'transporte escolar', 'transporte'],
                'pdde' => ['pdde', 'dinheiro direto'],
                'geral_educacao' => ['educacao', 'educação', 'escolar'],
            ],
        ],
    ],

    'other_funding' => [
        /*
         * Consultas automáticas a bases públicas na aba Financiamentos (por IBGE/ano).
         * PORTAL_TRANSPARENCIA_API_KEY: cadastro em portaldatransparencia.gov.br/pagina-api
         */
        'public_queries' => [
            'enabled' => filter_var(env('IEDUCAR_OTHER_FUNDING_PUBLIC_QUERIES', true), FILTER_VALIDATE_BOOL),
            'cache_ttl_seconds' => max(60, (int) env('IEDUCAR_OTHER_FUNDING_PUBLIC_CACHE_TTL', 3600)),
            'timeout' => max(5, (int) env('IEDUCAR_OTHER_FUNDING_PUBLIC_TIMEOUT', 12)),
            'live_fnde_fetch' => filter_var(env('IEDUCAR_OTHER_FUNDING_LIVE_FNDE', false), FILTER_VALIDATE_BOOL),
            'portal_transparencia' => [
                'enabled' => filter_var(env('IEDUCAR_PORTAL_TRANSPARENCIA_ENABLED', true), FILTER_VALIDATE_BOOL),
                'api_key' => (string) env('PORTAL_TRANSPARENCIA_API_KEY', ''),
                'base_url' => (string) env('IEDUCAR_PORTAL_TRANSPARENCIA_URL', 'https://api.portaldatransparencia.gov.br'),
                'max_rows' => max(3, (int) env('IEDUCAR_PORTAL_TRANSPARENCIA_MAX_ROWS', 8)),
                'education_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
                    'IEDUCAR_PORTAL_TRANSPARENCIA_KEYWORDS',
                    'educacao,educação,fnde,pnae,pnate,pdde,fundeb,escolar,merenda,transporte escolar'
                ))))),
            ],
            'tesouro_ckan' => [
                'enabled' => filter_var(env('IEDUCAR_TESOURO_CKAN_ENABLED', true), FILTER_VALIDATE_BOOL),
                'base_url' => (string) env('IEDUCAR_TESOURO_CKAN_URL', 'https://www.tesourotransparente.gov.br/ckan'),
                'resource_id' => (string) env('IEDUCAR_TESOURO_TRANSFERENCIAS_RESOURCE_ID', ''),
                'package_id' => (string) env('IEDUCAR_TESOURO_TRANSFERENCIAS_PACKAGE', 'transferencias-obrigatorias-da-uniao-por-municipio'),
                /** CSV municipal (COD_MUN + nome/UF); fallback quando datastore CKAN está inactivo. */
                'csv_enabled' => filter_var(env('IEDUCAR_TESOURO_CSV_ENABLED', true), FILTER_VALIDATE_BOOL),
                'csv_cache_ttl_seconds' => max(300, (int) env('IEDUCAR_TESOURO_CSV_CACHE_TTL', 86400)),
                'csv_resources' => [
                    'fundeb' => [
                        'resource_id' => '18d5b0ae-8037-461e-8685-3f0d7752a287',
                        'programa_id' => 'fundeb',
                        'name' => 'FUNDEB por município',
                        'url' => 'https://www.tesourotransparente.gov.br/ckan/dataset/transferencias-obrigatorias-da-uniao-por-municipio/resource/18d5b0ae-8037-461e-8685-3f0d7752a287/download/fundeb-por-municipio.csv',
                    ],
                ],
            ],
        ],

        'programs' => [
            [
                'id' => 'pnate',
                'titulo' => 'PNATE — Programa Nacional de Apoio ao Transporte do Escolar',
                'descricao' => 'Repasses e prestação de contas ligados ao transporte escolar; dependem do cadastro de utilização de transporte nas matrículas e da oferta declarada no Censo.',
                'matricula_columns' => [
                    'transporte_escolar',
                    'uso_transporte_escolar',
                    'veiculo_transporte_escolar',
                    'ref_cod_transporte_escolar',
                ],
                'fnde_url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/programa-nacional-de-apoio-ao-transporte-do-escolar-pnate',
            ],
            [
                'id' => 'pnae',
                'titulo' => 'PNAE — Programa Nacional de Alimentação Escolar',
                'descricao' => 'Merenda escolar e alimentação escolar; o i-Educar raramente centraliza cardápios — o cadastro de matrículas e escolas alimenta planejamento e Censo.',
                'matricula_columns' => [
                    'alimentacao_escolar',
                    'recebe_escolarizacao_em_outro_espaco',
                    'tipo_atendimento',
                ],
                'fnde_url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/programa-nacional-de-alimentacao-escolar',
            ],
            [
                'id' => 'pdde',
                'titulo' => 'PDDE — Programa Dinheiro Direto na Escola',
                'descricao' => 'Recursos de custeio e capital repassados às escolas; exige escolas com INEP válido e matrículas consistentes no Censo.',
                'matricula_columns' => [],
                'fnde_url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/programa-dinheiro-direto-na-escola',
            ],
            [
                'id' => 'pdde-qualidade',
                'titulo' => 'PDDE — Qualidade (ações prioritárias)',
                'descricao' => 'Complemento do PDDE para ações de qualidade; comprovação no Simec/FNDE, com reflexo em indicadores e cadastro escolar.',
                'matricula_columns' => [],
                'fnde_url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/pdde-qualidade',
            ],
            [
                'id' => 'salario-educacao',
                'titulo' => 'Salário-educação e programas correlatos',
                'descricao' => 'Financiamento da educação básica via contribuição social; não depende de campos específicos no i-Educar, mas de matrículas válidas no Censo.',
                'matricula_columns' => [],
                'fnde_url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/salario-educacao',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Censo / Educacenso por escola (aba «Censo»)
    |--------------------------------------------------------------------------
    |
    | Detecção automática de tabelas educacenso_* ou colunas exportado/fechado.
    | IEDUCAR_CENSO_STATUS_TABLE: tabela qualificada (ex.: modules.educacenso_exportacao).
    |
    */

    'censo_tracking' => [
        'status_table' => (string) env('IEDUCAR_CENSO_STATUS_TABLE', ''),
        'table_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_CENSO_TABLE_CANDIDATES',
            'educacenso_exportacao,educacenso_exportacoes,exportacao_educacenso,educacenso_registro,educacenso_ano_letivo,escola_educacenso'
        ))))),
        'export_columns' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_CENSO_EXPORT_COLUMNS', ''))))),
        'closed_columns' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_CENSO_CLOSED_COLUMNS', ''))))),
        'status_columns' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_CENSO_STATUS_COLUMNS', ''))))),
        'exported_text_values' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_CENSO_EXPORTED_TEXT',
            'exportado,exportada,concluido,concluída,finalizado,enviado'
        ))))),
        'closed_text_values' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_CENSO_CLOSED_TEXT',
            'fechado,fechada,encerrado,encerrada,fecho,concluido'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trabalho realizado (cadastro recente no i-Educar)
    |--------------------------------------------------------------------------
    |
    | Exclusão de usuários administrativos na contagem (login, IDs, níveis).
    | minutos_por_registro: fallback quando não há timestamps suficientes na base.
    |
    */

    'work_tracking' => [
        'exclude_login_patterns' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_WORK_EXCLUDE_LOGINS',
            'admin,administrador,suporte,portabilis'
        ))))),
        'exclude_usuario_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env(
            'IEDUCAR_WORK_EXCLUDE_USER_IDS',
            '1'
        ))))),
        'exclude_nivel_usuario' => array_values(array_filter(array_map('intval', explode(',', (string) env(
            'IEDUCAR_WORK_EXCLUDE_NIVEL',
            '1'
        ))))),
        'default_minutes_per_record' => max(0.5, (float) env('IEDUCAR_WORK_MINUTES_PER_RECORD', 3.5)),
        'minutes_per_turma' => max(1.0, (float) env('IEDUCAR_WORK_MINUTES_PER_TURMA', 8)),
        'minutes_per_matricula' => max(0.5, (float) env('IEDUCAR_WORK_MINUTES_PER_MATRICULA', 3.5)),
        'minutes_per_enturmacao' => max(0.5, (float) env('IEDUCAR_WORK_MINUTES_PER_ENTURMACAO', 2.5)),
        'working_hours_per_day' => max(1, (float) env('IEDUCAR_WORK_HOURS_PER_DAY', 6)),
        'periods_days' => [
            'day' => 1,
            'week' => 7,
            'fortnight' => 15,
        ],
        'matricula_date_columns' => [
            'data_cad',
            'data_cadastro',
            'data_matricula',
            'data_matricula_inicial',
            'data_registro',
        ],
        'matricula_user_columns' => [
            'ref_usuario_cad',
            'ref_usuario',
            'id_usuario_cad',
            'usuario_cad',
        ],
        'ano_letivo_table_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'IEDUCAR_WORK_ANO_LETIVO_TABLES',
            'escola_ano_letivo,ano_letivo,educacenso_ano_letivo'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila de sincronização administrativa (geo, pedagógico, FUNDEB, i-Educar)
    |--------------------------------------------------------------------------
    |
    | Use QUEUE_CONNECTION=database (ou redis) em produção e execute:
    | php artisan admin-sync:work
    |
    */

    'admin_sync' => [
        'queue' => (string) env('ADMIN_SYNC_QUEUE', 'admin-sync'),
        'connection' => env('ADMIN_SYNC_QUEUE_CONNECTION'),
        'job_timeout' => max(60, (int) env('ADMIN_SYNC_JOB_TIMEOUT', 3600)),
        'tries' => max(1, (int) env('ADMIN_SYNC_TRIES', 1)),
        /** Slug exigido por `app:flush-processing-queue --confirm=` em APP_ENV=production. */
        'flush_confirm_slug' => (string) env('ADMIN_PROCESSING_QUEUE_FLUSH_SLUG', 'zerar-fila-processamento'),
        /*
         * Processamento via `php artisan schedule:run` (cron a cada SCHEDULE_RUN_INTERVAL_MINUTES).
         * Por defeito: admin-sync:work 2×/dia (ADMIN_SYNC_SCHEDULE_TIMES) e, entre execuções,
         * schedule:run dispara o worker quando há jobs/tarefas pendentes (on_demand).
         * Desactive se usar `admin-sync:work` contínuo em Supervisor.
         */
        'schedule' => [
            'enabled' => filter_var(env('ADMIN_SYNC_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'times' => array_values(array_filter(array_map(
                static fn (string $part): string => trim($part),
                explode(',', (string) env('ADMIN_SYNC_SCHEDULE_TIMES', '06:00,18:00')),
            ))),
            'on_demand' => filter_var(env('ADMIN_SYNC_SCHEDULE_ON_DEMAND', true), FILTER_VALIDATE_BOOL),
            'on_demand_max_seconds' => max(60, (int) env('ADMIN_SYNC_SCHEDULE_ON_DEMAND_MAX_SECONDS', 900)),
            /** @deprecated Use ADMIN_SYNC_SCHEDULE_TIMES. Mantido só se `times` estiver vazio. */
            'interval_minutes' => max(1, (int) env('ADMIN_SYNC_SCHEDULE_INTERVAL_MINUTES', 60)),
            'max_seconds' => max(10, (int) env('ADMIN_SYNC_SCHEDULE_MAX_SECONDS', 3300)),
            'overlap_minutes' => max(1, (int) env('ADMIN_SYNC_SCHEDULE_OVERLAP_MINUTES', 720)),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronização massiva semanal (system::weekly_mass_sync)
    |--------------------------------------------------------------------------
    |
    | Comando: php artisan weekly-mass-sync:run
    | Retomar: php artisan weekly-mass-sync:run --resume={task_id}
    | Agenda: domingo (configurável) via schedule:run
    |
    */

    /*
    |--------------------------------------------------------------------------
    | CadÚnico / Cecad — agregados municipais (previsão fora da rede)
    |--------------------------------------------------------------------------
    |
    | Importe CSV exportado do Cecad (Consulta, Seleção e Extração) ou painéis MDs
    | agregados por município. Não armazena CPF/NIS — apenas totais por faixa etária.
    | Comando: php artisan cadunico:import-cecad {caminho} --ano=2024
    |
    */

    'cadunico' => [
        'enabled' => filter_var(env('IEDUCAR_CADUNICO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'idade_escolar_min' => max(0, (int) env('IEDUCAR_CADUNICO_IDADE_MIN', 4)),
        'idade_escolar_max' => max(4, (int) env('IEDUCAR_CADUNICO_IDADE_MAX', 17)),
        'cobertura_alerta_pct' => max(50.0, (float) env('IEDUCAR_CADUNICO_COBERTURA_ALERTA_PCT', 92.0)),
        'misocial' => [
            'enabled' => filter_var(env('IEDUCAR_CADUNICO_MISOGIAL_ENABLED', true), FILTER_VALIDATE_BOOL),
            'base_url' => (string) env('IEDUCAR_CADUNICO_MISOGIAL_BASE_URL', 'https://aplicacoes.mds.gov.br/sagi/servicos/misocial'),
            'page_size' => max(500, min(15000, (int) env('IEDUCAR_CADUNICO_MISOGIAL_PAGE_SIZE', 6000))),
            'field_list' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_CADUNICO_MISOGIAL_FIELDS', ''))))),
            /** Máximo de campos em `fl` (Solr ignora listas longas). */
            'field_list_max' => max(10, (int) env('IEDUCAR_CADUNICO_MISOGIAL_FIELDS_MAX', 24)),
            /** Ano inicial do `cadunico:import-misocial` sem --from/--years. */
            'historical_from_year' => max(2000, (int) env('IEDUCAR_CADUNICO_MISOGIAL_FROM_YEAR', 2020)),
        ],
        'open_data' => [
            'cache_enabled' => filter_var(env('IEDUCAR_CADUNICO_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'http_timeout' => max(5, (int) env('IEDUCAR_CADUNICO_HTTP_TIMEOUT', 45)),
            'api_url_template' => (string) env('IEDUCAR_CADUNICO_API_URL_TEMPLATE', ''),
            'ckan_base_url' => (string) env('IEDUCAR_CADUNICO_CKAN_URL', 'https://dados.gov.br'),
            'ckan_bases' => array_values(array_filter(array_map('trim', explode(',', (string) env('IEDUCAR_CADUNICO_CKAN_BASES', 'https://dados.gov.br,https://catalogo.dados.gov.br'))))),
            'resource_id' => (string) env('IEDUCAR_CADUNICO_CKAN_RESOURCE_ID', ''),
            'search_query' => (string) env('IEDUCAR_CADUNICO_CKAN_SEARCH_QUERY', 'cadastro unico municipio ibge'),
        ],
        'auto_sync' => [
            'enabled' => filter_var(env('IEDUCAR_CADUNICO_AUTO_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL),
            'sync_on_city_save' => filter_var(env('IEDUCAR_CADUNICO_SYNC_ON_CITY_SAVE', true), FILTER_VALIDATE_BOOL),
            'fill_api_gaps' => filter_var(env('IEDUCAR_CADUNICO_FILL_API_GAPS', true), FILTER_VALIDATE_BOOL),
            /** Descarrega nacional_{ano}.csv de URL (sem upload). Placeholder {ano}. */
            'nacional_csv_url_template' => (string) env('IEDUCAR_CADUNICO_NACIONAL_CSV_URL', ''),
            'municipal_csv_url_template' => (string) env('IEDUCAR_CADUNICO_MUNICIPAL_CSV_URL', ''),
            'refresh_csv_days' => max(1, (int) env('IEDUCAR_CADUNICO_REFRESH_CSV_DAYS', 30)),
            'dados_gov_search' => filter_var(env('IEDUCAR_CADUNICO_DADOS_GOV_SEARCH', true), FILTER_VALIDATE_BOOL),
            'dados_gov_query' => (string) env('IEDUCAR_CADUNICO_DADOS_GOV_QUERY', 'cadastro unico municipio'),
            'years' => array_values(array_filter(array_map('intval', explode(',', (string) env('IEDUCAR_CADUNICO_SYNC_YEARS', ''))))),
            'schedule' => [
                'enabled' => filter_var(env('IEDUCAR_CADUNICO_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'day_of_week' => max(0, min(6, (int) env('IEDUCAR_CADUNICO_SCHEDULE_DAY', 1))),
                'time' => trim((string) env('IEDUCAR_CADUNICO_SCHEDULE_TIME', '03:30')) ?: '03:30',
            ],
        ],
        'cecad' => [
            'delimiter' => env('IEDUCAR_CADUNICO_CSV_DELIMITER', ';'),
            'storage_path' => (string) env('IEDUCAR_CADUNICO_CSV_PATH', 'cadunico/cecad'),
            'column_map' => [
                'ibge' => [
                    'codigo_ibge', 'ibge', 'ibge_municipio', 'co_ibge', 'cd_ibge', 'cod_ibge',
                ],
                'ano' => ['ano', 'ano_referencia', 'nu_ano', 'exercicio'],
                'pessoas' => ['pessoas_cadastradas', 'total_pessoas', 'pop_cadastrada'],
                'familias' => ['familias_cadastradas', 'total_familias', 'familias'],
                'criancas_0_3' => ['criancas_0_3', 'pop_0_3', 'faixa_0_3'],
                'criancas_4_5' => ['criancas_4_5', 'pop_4_5', 'pre_escola', 'faixa_4_5'],
                'criancas_6_10' => ['criancas_6_10', 'pop_6_10', 'fund_inicial', 'faixa_6_10'],
                'criancas_11_14' => ['criancas_11_14', 'pop_11_14', 'fund_final', 'faixa_11_14'],
                'criancas_15_17' => ['criancas_15_17', 'pop_15_17', 'ensino_medio', 'faixa_15_17'],
                'pop_escolar' => [
                    'populacao_escolar', 'pop_escolar_4_17', 'criancas_4_17', 'escolaridade_obrigatoria',
                ],
            ],
        ],
        'faixas_etarias' => [
            ['key' => 'criancas_4_5', 'label' => 'Pré-escola (4-5 anos)', 'etapa_keywords' => ['infantil', 'pré', 'pre', 'creche']],
            ['key' => 'criancas_6_10', 'label' => 'Fundamental — anos iniciais', 'etapa_keywords' => ['iniciais', 'fundamental i']],
            ['key' => 'criancas_11_14', 'label' => 'Fundamental — anos finais', 'etapa_keywords' => ['finais', 'fundamental ii', 'fundamental 2']],
            ['key' => 'criancas_15_17', 'label' => 'Ensino médio', 'etapa_keywords' => ['médio', 'medio', 'eja']],
        ],
        'territorio' => [
            'delimiter' => env('IEDUCAR_CADUNICO_TERRITORIO_DELIMITER', ';'),
            'storage_path' => (string) env('IEDUCAR_CADUNICO_TERRITORIO_PATH', 'cadunico/territorio'),
            'load_school_markers' => filter_var(env('IEDUCAR_CADUNICO_TERRITORIO_SCHOOL_MARKERS', true), FILTER_VALIDATE_BOOL),
            'ibge_censo' => [
                'cache_days' => max(7, (int) env('IEDUCAR_CADUNICO_TERRITORIO_IBGE_CACHE_DAYS', 90)),
                'http_timeout' => max(30, (int) env('IEDUCAR_CADUNICO_TERRITORIO_IBGE_TIMEOUT', 180)),
                'zip_urls' => [
                    'bairro_basico' => (string) env(
                        'IEDUCAR_CADUNICO_TERRITORIO_IBGE_BAIRRO_ZIP',
                        'https://ftp.ibge.gov.br/Censos/Censo_Demografico_2022/Agregados_por_Setores_Censitarios/Agregados_por_Bairro_csv/Agregados_por_bairros_basico_BR_20260520.zip',
                    ),
                    'setor_basico' => (string) env(
                        'IEDUCAR_CADUNICO_TERRITORIO_IBGE_SETOR_ZIP',
                        'https://ftp.ibge.gov.br/Censos/Censo_Demografico_2022/Agregados_por_Setores_Censitarios/Agregados_por_Setor_csv/Agregados_por_setores_basico_BR_20260520.zip',
                    ),
                ],
            ],
            'ibge_wfs' => [
                'base_url' => (string) env(
                    'IEDUCAR_CADUNICO_TERRITORIO_IBGE_WFS',
                    'https://geoservicos.ibge.gov.br/geoserver/CGMAT/wfs',
                ),
                'layer_bairro' => 'CGMAT:qg_2022_650_bairro_agreg',
                'layer_setor' => 'CGMAT:qg_2022_600_setcensitario__v02',
                'timeout' => max(15, (int) env('IEDUCAR_CADUNICO_TERRITORIO_WFS_TIMEOUT', 60)),
                'max_features' => 1500,
            ],
            'column_map' => [
                'ibge' => ['codigo_ibge', 'ibge', 'ibge_municipio'],
                'ano' => ['ano', 'ano_referencia'],
                'codigo' => ['territorio_codigo', 'codigo', 'cod_bairro', 'cod_setor'],
                'nome' => ['territorio_nome', 'bairro', 'nome', 'regiao', 'nm_bairro'],
                'tipo' => ['territorio_tipo', 'tipo'],
                'criancas_4_17' => ['criancas_4_17', 'pop_4_17', 'populacao_escolar'],
                'criancas_4_5' => ['criancas_4_5'],
                'criancas_6_10' => ['criancas_6_10'],
                'criancas_11_14' => ['criancas_11_14'],
                'criancas_15_17' => ['criancas_15_17'],
                'familias_beneficio' => ['familias_pbf', 'familias_beneficio'],
                'vulnerabilidade' => ['indice_vulnerabilidade', 'vulnerabilidade', 'ivs'],
                'lat' => ['latitude', 'lat'],
                'lng' => ['longitude', 'lng', 'lon'],
            ],
        ],
        'weekly_allow_partial' => filter_var(env('IEDUCAR_CADUNICO_WEEKLY_PARTIAL_OK', true), FILTER_VALIDATE_BOOL),
        'fontes_oficiais' => [
            'SAGI/Misocial — Matriz de Informação Social (MDS)',
            'https://aplicacoes.mds.gov.br/sagi/servicos/misocial/',
            'Cecad — CadÚnico (MDS)',
            'https://www.gov.br/mds/pt-br/acoes-e-programas/cadastro-unico',
            'dados.gov.br — CKAN (descoberta automática)',
            'https://dados.gov.br',
        ],
    ],

    'weekly_mass_sync' => [
        'enabled' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL),
        /** Timeout do job na fila admin-sync (segundos). */
        'job_timeout' => max(3600, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_JOB_TIMEOUT', 14400)),
        /** Tempo máximo do `admin-sync:work` quando esta tarefa está pendente (cron on_demand). */
        'worker_max_seconds' => max(3600, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_WORKER_MAX_SECONDS', 14400)),
        /** set_time_limit dentro do worker. */
        'php_time_limit' => max(3600, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_PHP_TIME_LIMIT', 14400)),
        'schedule' => [
            'enabled' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            /** 0=domingo … 6=sábado (Laravel weeklyOn). */
            'day_of_week' => max(0, min(6, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_DAY', 0))),
            'time' => trim((string) env('IEDUCAR_WEEKLY_MASS_SYNC_TIME', '02:00')) ?: '02:00',
            /** Mutex: não sobrepor execução anterior (minutos). */
            'overlap_minutes' => max(60, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_OVERLAP_MINUTES', 10080)),
        ],
        /** Anos de repasse Tesouro/Transparência (últimos N anos civis). */
        'transfer_year_span' => max(1, min(10, (int) env('IEDUCAR_WEEKLY_MASS_SYNC_TRANSFER_YEARS', 3))),
        'geo_ieducar_only_missing' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_GEO_IEDUCAR_ONLY_MISSING', true), FILTER_VALIDATE_BOOL),
        'geo_official_only_missing' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_GEO_OFFICIAL_ONLY_MISSING', true), FILTER_VALIDATE_BOOL),
        'geo_microdados_fetch' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_GEO_FETCH', true), FILTER_VALIDATE_BOOL),
        'transfers_allow_partial_failures' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_TRANSFERS_PARTIAL_OK', true), FILTER_VALIDATE_BOOL),
        'censo_matriculas_skip_if_missing' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_CENSO_SKIP_MISSING', true), FILTER_VALIDATE_BOOL),
        'censo_matriculas_allow_empty' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_CENSO_ALLOW_EMPTY', false), FILTER_VALIDATE_BOOL),
        /** Fases activas (chave => true/false). Vazio = todas. */
        'phases' => [
            'geo_pipeline' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_GEO', true), FILTER_VALIDATE_BOOL),
            'fundeb_sync' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_FUNDEB', true), FILTER_VALIDATE_BOOL),
            'funding_transfers' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_TRANSFERS', true), FILTER_VALIDATE_BOOL),
            'funding_censo_matriculas' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_CENSO', true), FILTER_VALIDATE_BOOL),
            'pedagogical_saeb' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_SAEB', true), FILTER_VALIDATE_BOOL),
            'censo_geo_agg' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_CENSO_AGG', true), FILTER_VALIDATE_BOOL),
            'cadunico_snapshots' => filter_var(env('IEDUCAR_WEEKLY_MASS_SYNC_PHASE_CADUNICO', true), FILTER_VALIDATE_BOOL),
        ],
    ],

];
