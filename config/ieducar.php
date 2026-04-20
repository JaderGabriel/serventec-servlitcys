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
        'microdados_cache_path' => trim((string) env('IEDUCAR_SAEB_MICRODADOS_CACHE_PATH', 'saeb/microdados_cache')) ?: 'saeb/microdados_cache',
        'microdados_download_timeout_seconds' => (int) env('IEDUCAR_SAEB_MICRODADOS_TIMEOUT', 900),
        /** cURL/Guzzle: verificar certificado SSL (false só em dev com CA em falta). */
        'microdados_http_verify' => filter_var(env('IEDUCAR_SAEB_HTTP_VERIFY', true), FILTER_VALIDATE_BOOL),
        /** Caminho opcional para cacert.pem (ex.: /etc/ssl/certs/ca-certificates.crt). */
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

    'inclusion' => [
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
            'https://www.portalideb.org.br/resultado/escola/{inep}'
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

];
