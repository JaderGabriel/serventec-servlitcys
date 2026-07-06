<?php

use App\Support\Horizonte\HorizonteReferenceYear;

$horizonteReferenceYearRaw = (int) env('HORIZONTE_REFERENCE_YEAR', 0);

return [

    /*
    |--------------------------------------------------------------------------
    | Horizonte — mapa de oportunidade municipal
    |--------------------------------------------------------------------------
    |
    | Score indicativo (0–100) com base em dados públicos importados no hub
    | Dados públicos. Não substitui diagnóstico i-Educar / Consultoria.
    |
    */

    'enabled' => filter_var(env('HORIZONTE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'cache_seconds' => max(60, (int) env('HORIZONTE_CACHE_SECONDS', 3600)),

    'reference_year_raw' => $horizonteReferenceYearRaw,

    'reference_year' => HorizonteReferenceYear::resolve($horizonteReferenceYearRaw),

    'enrollment_series' => [
        'years' => max(2, min(10, (int) env('HORIZONTE_ENROLLMENT_SERIES_YEARS', 5))),
    ],

    'high_opportunity_threshold' => max(1, min(99, (int) env('HORIZONTE_HIGH_THRESHOLD', 70))),

    'medium_opportunity_threshold' => max(1, min(98, (int) env('HORIZONTE_MEDIUM_THRESHOLD', 40))),

    'weights' => [
        'financial_pressure' => 0.18,
        'pedagogical_gap' => 0.16,
        'scale' => 0.10,
        'social_demand' => 0.16,
        'transfer_dependency' => 0.08,
        'fiscal_capacity' => 0.10,
        'enrollment_momentum' => 0.06,
        'data_readiness' => 0.08,
        'benefit_scale' => 0.08,
    ],

    'siconfi' => [
        'enabled' => filter_var(env('HORIZONTE_SICONFI_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => env('HORIZONTE_SICONFI_BASE_URL', 'https://apidatalake.tesouro.gov.br/ords/siconfi/tt'),
        'http_timeout' => max(15, (int) env('HORIZONTE_SICONFI_HTTP_TIMEOUT', 45)),
        'page_limit' => max(500, min(5000, (int) env('HORIZONTE_SICONFI_PAGE_LIMIT', 5000))),
        'period' => max(1, min(6, (int) env('HORIZONTE_SICONFI_PERIOD', 6))),
        'municipios_per_step' => max(1, min(50, (int) env('HORIZONTE_SICONFI_MUNICIPIOS_PER_STEP', 8))),
    ],

    /** Sincronização dedicada SICONFI (`horizonte:sync-siconfi`) — semestral recomendada. */
    'siconfi_sync' => [
        'progress_ttl' => max(3600, (int) env('HORIZONTE_SICONFI_PROGRESS_TTL', 7776000)),
        /** Nacional: 1 UF inteira por execução (recomendado). false = lote por N municípios. */
        'by_uf' => filter_var(env('HORIZONTE_SICONFI_BY_UF', true), FILTER_VALIDATE_BOOL),
        /** Ordem das UFs: municipalities_asc (DF→MG), catalog, municipalities_desc. */
        'uf_order' => env('HORIZONTE_SICONFI_UF_ORDER', 'municipalities_asc'),
        /** UFs processadas por invocação na fase `siconfi_sync` (predefinição: 1 UF inteira). */
        'ufs_per_step' => max(1, min(3, (int) env('HORIZONTE_SICONFI_UFS_PER_STEP', 1))),
        /** Tempo máximo por UF no feed (SP ~645 municípios). */
        'time_limit' => max(300, (int) env('HORIZONTE_SICONFI_TIME_LIMIT', 3600)),
        'schedule' => [
            'enabled' => filter_var(env('HORIZONTE_SICONFI_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            /** Dia do mês (1–28) em que inicia cada ciclo semestral. */
            'day' => max(1, min(28, (int) env('HORIZONTE_SICONFI_SCHEDULE_DAY', 15))),
            /** Meses do ano (1–12) — por defeito jan e jul (2×/ano). */
            'months' => array_values(array_filter(array_map(
                'intval',
                explode(',', (string) env('HORIZONTE_SICONFI_SCHEDULE_MONTHS', '1,7')),
            ))),
            'time' => env('HORIZONTE_SICONFI_SCHEDULE_TIME', '04:00'),
            'overlap_minutes' => max(3600, (int) env('HORIZONTE_SICONFI_SCHEDULE_OVERLAP_MINUTES', 43200)),
            'step_interval_minutes' => max(5, (int) env('HORIZONTE_SICONFI_SCHEDULE_STEP_INTERVAL', 30)),
            /** Uma invocação por lote (recomendado em produção). */
            'staged' => filter_var(env('HORIZONTE_SICONFI_SCHEDULE_STAGED', true), FILTER_VALIDATE_BOOL),
        ],
    ],

    'transparency' => [
        'municipios_per_step' => max(1, min(30, (int) env('HORIZONTE_TRANSPARENCY_MUNICIPIOS_PER_STEP', 5))),
        'http_timeout' => max(10, (int) env('HORIZONTE_TRANSPARENCY_HTTP_TIMEOUT', 25)),
    ],

    'sidra' => [
        'enabled' => filter_var(env('HORIZONTE_SIDRA_ENABLED', true), FILTER_VALIDATE_BOOL),
        'agregado' => env('HORIZONTE_SIDRA_AGREGADO', '9514'),
        'variavel' => env('HORIZONTE_SIDRA_VARIAVEL', '93'),
        'periodo' => max(2010, (int) env('HORIZONTE_SIDRA_PERIODO', 2022)),
        'ufs_per_step' => max(1, min(3, (int) env('HORIZONTE_SIDRA_UFS_PER_STEP', 1))),
        'base_url' => env('HORIZONTE_SIDRA_BASE_URL', 'https://servicodados.ibge.gov.br/api/v3/agregados'),
        'http_timeout' => max(15, (int) env('HORIZONTE_SIDRA_HTTP_TIMEOUT', 90)),
        'uf_n3_codes' => [
            'AC' => '12', 'AL' => '27', 'AM' => '13', 'AP' => '16', 'BA' => '29', 'CE' => '23',
            'DF' => '53', 'ES' => '32', 'GO' => '52', 'MA' => '21', 'MG' => '31', 'MS' => '50',
            'MT' => '51', 'PA' => '15', 'PB' => '25', 'PE' => '26', 'PI' => '22', 'PR' => '41',
            'RJ' => '33', 'RN' => '24', 'RO' => '11', 'RR' => '14', 'RS' => '43', 'SC' => '42',
            'SE' => '28', 'SP' => '35', 'TO' => '17',
        ],
    ],

    'cadunico_feed' => [
        'fill_api_gaps' => filter_var(env('HORIZONTE_CADUNICO_FILL_GAPS', false), FILTER_VALIDATE_BOOL),
    ],

    'saeb_disciplines' => ['LP', 'MAT', 'lp', 'mat', 'Língua Portuguesa', 'Matemática'],

    /*
    |--------------------------------------------------------------------------
    | Abastecimento bimestral (rotina agendada + comando horizonte:fortnightly-feed)
    |--------------------------------------------------------------------------
    |
    | Sincroniza dados públicos nacionais para alimentar o mapa Horizonte:
    | FUNDEB (CSV receita FNDE), Censo matrículas, SAEB planilhas INEP, catálogo IBGE.
    |
    */

    'fortnightly_feed' => [
        'enabled' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_ENABLED', true), FILTER_VALIDATE_BOOL),

        'schedule' => [
            'enabled' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            /** Dia do mês (1–28) em que inicia cada ciclo bimestral. */
            'day' => max(1, min(28, (int) env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_DAY', 1))),
            /** Meses do ano (1–12) — por defeito ímpares = a cada 2 meses. */
            'months' => array_values(array_filter(array_map(
                'intval',
                explode(',', (string) env('HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_MONTHS', '1,3,5,7,9,11')),
            ))),
            'time' => env('HORIZONTE_FORTNIGHTLY_FEED_TIME', '03:00'),
            'overlap_minutes' => max(60, (int) env('HORIZONTE_FORTNIGHTLY_FEED_OVERLAP_MINUTES', 10080)),
            'step_interval_minutes' => max(5, (int) env('HORIZONTE_FORTNIGHTLY_FEED_STEP_INTERVAL', 20)),
        ],

        'fundeb_years' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('HORIZONTE_FORTNIGHTLY_FUNDEB_YEARS', '')),
        ))),

        'fundeb_allow_empty' => filter_var(env('HORIZONTE_FORTNIGHTLY_FUNDEB_ALLOW_EMPTY', true), FILTER_VALIDATE_BOOL),

        'saeb_years' => null,

        'censo_skip_if_missing' => filter_var(env('HORIZONTE_FORTNIGHTLY_CENSO_SKIP_IF_MISSING', true), FILTER_VALIDATE_BOOL),
        'censo_allow_empty' => filter_var(env('HORIZONTE_FORTNIGHTLY_CENSO_ALLOW_EMPTY', false), FILTER_VALIDATE_BOOL),
        'educacenso_enabled' => filter_var(env('HORIZONTE_EDUCACENSO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'educacenso_fetch_if_missing' => filter_var(env('HORIZONTE_EDUCACENSO_FETCH_IF_MISSING', true), FILTER_VALIDATE_BOOL),
        'educacenso_skip_if_missing' => filter_var(env('HORIZONTE_EDUCACENSO_SKIP_IF_MISSING', true), FILTER_VALIDATE_BOOL),
        'educacenso_allow_empty' => filter_var(env('HORIZONTE_EDUCACENSO_ALLOW_EMPTY', false), FILTER_VALIDATE_BOOL),
        'educacenso_years_per_step' => max(1, min(5, (int) env('HORIZONTE_EDUCACENSO_YEARS_PER_STEP', 1))),
        'educacenso_steps_per_step' => max(1, min(27, (int) env('HORIZONTE_EDUCACENSO_STEPS_PER_STEP', 1))),
        'educacenso_memory_limit' => env('HORIZONTE_EDUCACENSO_MEMORY_LIMIT', '1024M'),
        /** Checkpoint ano×UF — persiste em storage/app/… (sobrevive a cache:clear no deploy). */
        'educacenso_progress_snapshot_path' => env(
            'HORIZONTE_EDUCACENSO_PROGRESS_SNAPSHOT_PATH',
            'horizonte/educacenso_import_progress.json',
        ),
        /** Reconstruir passos concluídos a partir de inep_censo_municipio_matriculas se snapshot ausente. */
        'educacenso_infer_progress_from_db' => filter_var(env('HORIZONTE_EDUCACENSO_INFER_PROGRESS', true), FILTER_VALIDATE_BOOL),
        'educacenso_infer_min_coverage_ratio' => max(0.5, min(1.0, (float) env('HORIZONTE_EDUCACENSO_INFER_MIN_RATIO', 0.7))),
        'snapshot_cache_ttl' => max(3600, (int) env('HORIZONTE_FORTNIGHTLY_SNAPSHOT_CACHE_TTL', 604800)),
        'pipeline_cache_ttl' => max(3600, (int) env('HORIZONTE_FORTNIGHTLY_PIPELINE_CACHE_TTL', 604800)),

        /** Executar uma fase por invocação Artisan (recomendado em produção). */
        'staged' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_STAGED', true), FILTER_VALIDATE_BOOL),
        'notify_phases' => filter_var(env('HORIZONTE_FORTNIGHTLY_FEED_NOTIFY_PHASES', true), FILTER_VALIDATE_BOOL),
        'memory_limit' => env('HORIZONTE_FORTNIGHTLY_FEED_MEMORY_LIMIT', '512M'),
        /** Planilhas SAEB INEP (RAR/XLSX) exigem mais RAM que o resto do feed. */
        'saeb_memory_limit' => env('HORIZONTE_SAEB_MEMORY_LIMIT', '2048M'),
        'time_limit' => max(60, (int) env('HORIZONTE_FORTNIGHTLY_FEED_TIME_LIMIT', 900)),

        /** UFs aquecidas por invocação na fase IBGE (1 = mínimo de RAM). */
        'ibge_ufs_per_step' => max(1, min(27, (int) env('HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP', 1))),

        /** Anos SAEB importados por invocação na fase planilhas (1 = mínimo de RAM). */
        'saeb_years_per_step' => max(1, min(10, (int) env('HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP', 1))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sistemas de gestão educacional (SGE) — registo opcional + catálogo local
    |--------------------------------------------------------------------------
    */

    'sge' => [
        'enabled' => filter_var(env('HORIZONTE_SGE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'registry_path' => env('HORIZONTE_SGE_REGISTRY_PATH', 'horizonte/sge_registry.json'),
        'registry_url' => env('HORIZONTE_SGE_REGISTRY_URL'),
        'registry_http_timeout' => max(5, min(60, (int) env('HORIZONTE_SGE_REGISTRY_HTTP_TIMEOUT', 15))),
        'registry_cache_ttl' => max(3600, (int) env('HORIZONTE_SGE_REGISTRY_CACHE_TTL', 604800)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertas oficiais MEC/FNDE — bloqueios, inabilitações VAAT, avisos
    |--------------------------------------------------------------------------
    |
    | Fontes públicas consultáveis: CSV oficial FNDE VAAT inabilitados (primário),
    | PDF portaria (fallback), registo JSON local/remoto (Simec, Tesouro, alertas manuais).
    | Não existe API REST única — importação periódica via horizonte:sync-municipal-alerts.
    |
    */

    'municipal_alerts' => [
        'enabled' => filter_var(env('HORIZONTE_MUNICIPAL_ALERTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'registry_path' => env('HORIZONTE_MUNICIPAL_ALERTS_PATH', 'horizonte/municipal_alerts_registry.json'),
        'registry_url' => env('HORIZONTE_MUNICIPAL_ALERTS_URL'),
        'snapshot_path' => env('HORIZONTE_MUNICIPAL_ALERTS_SNAPSHOT_PATH', 'horizonte/municipal_alerts_snapshot.json'),
        'http_timeout' => max(5, min(120, (int) env('HORIZONTE_MUNICIPAL_ALERTS_HTTP_TIMEOUT', 45))),
        'http_user_agent' => env(
            'HORIZONTE_MUNICIPAL_ALERTS_USER_AGENT',
            'Mozilla/5.0 (compatible; Servlitcys-Horizonte/1.0; +https://serventecassessoria.com.br)',
        ),
        'cache_ttl' => max(3600, (int) env('HORIZONTE_MUNICIPAL_ALERTS_CACHE_TTL', 604800)),
        'detail_urls' => [
            'fnde_consultas' => env(
                'HORIZONTE_MUNICIPAL_ALERTS_FNDE_URL',
                'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas',
            ),
            'simec' => env('HORIZONTE_MUNICIPAL_ALERTS_SIMEC_URL', 'https://simec.mec.gov.br/'),
            'siconfi_vaat' => env(
                'HORIZONTE_MUNICIPAL_ALERTS_SICONFI_VAAT_URL',
                'https://siconfi.tesouro.gov.br/siconfi/pages/public/conteudo/conteudo.jsf?id=51903',
            ),
            'tesouro_bloqueados' => env(
                'HORIZONTE_MUNICIPAL_ALERTS_TESOURO_BLOQUEADOS_URL',
                'https://www.tesourotransparente.gov.br/consultas/consulta-aos-entes-bloqueados',
            ),
            'fnde_vaar' => env(
                'HORIZONTE_MUNICIPAL_ALERTS_FNDE_VAAR_URL',
                'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2026',
            ),
            'pnae_suspensas' => env(
                'HORIZONTE_MUNICIPAL_ALERTS_PNAE_URL',
                'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/pnae/consultas/entidades-suspensas-1/entidades-suspensas',
            ),
        ],
        'sources' => [
            'fnde_vaat_inabilitados' => [
                'enabled' => filter_var(env('HORIZONTE_FNDE_VAAT_INABILITADOS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'exercise_year' => max(2007, (int) env('HORIZONTE_FNDE_VAAT_INABILITADOS_YEAR', (int) date('Y'))),
                'csv_url' => env(
                    'HORIZONTE_FNDE_VAAT_INABILITADOS_CSV_URL',
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/vaat/lista-dos-entes-habilitados-e-inabilitados-ao-vaat-2026-posicao-final-com-ajuste-de-decisao-judicial-edit-csv.csv/@@download/file',
                ),
                'csv_storage_path' => env(
                    'HORIZONTE_FNDE_VAAT_INABILITADOS_CSV_STORAGE_PATH',
                    'horizonte/alerts/fnde_vaat_inabilitados.csv',
                ),
                'pdf_url' => env(
                    'HORIZONTE_FNDE_VAAT_INABILITADOS_PDF_URL',
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025-1/ListapreliminarinabilitadosVAAT202623Jun2025.pdf',
                ),
                'detail_page_url' => env(
                    'HORIZONTE_FNDE_VAAT_INABILITADOS_DETAIL_URL',
                    'https://siconfi.tesouro.gov.br/siconfi/pages/public/conteudo/conteudo.jsf?id=51903',
                ),
                'storage_path' => env(
                    'HORIZONTE_FNDE_VAAT_INABILITADOS_STORAGE_PATH',
                    'horizonte/alerts/fnde_vaat_inabilitados.pdf',
                ),
            ],

            /** FNDE — lista de entes não habilitados/não beneficiários à complementação VAAR (CSV). */
            'fnde_vaar_nao_habilitados' => [
                'enabled' => filter_var(env('HORIZONTE_FNDE_VAAR_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'exercise_year' => max(2007, (int) env('HORIZONTE_FNDE_VAAR_YEAR', (int) date('Y'))),
                'csv_url' => env(
                    'HORIZONTE_FNDE_VAAR_CSV_URL',
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2026-1/ListaentesbeneficiariosenaobeneficiariosacomplementacaoVAARdoFundeb2026.csv',
                ),
                'csv_storage_path' => env(
                    'HORIZONTE_FNDE_VAAR_CSV_STORAGE_PATH',
                    'horizonte/alerts/fnde_vaar_nao_habilitados.csv',
                ),
                'detail_page_url' => env('HORIZONTE_FNDE_VAAR_DETAIL_URL', ''),
            ],

            /** FNDE/PNAE — relação de Entidades Executoras com repasse suspenso (XLSX). */
            'pnae_entidades_suspensas' => [
                'enabled' => filter_var(env('HORIZONTE_PNAE_SUSPENSAS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'exercise_year' => max(2007, (int) env('HORIZONTE_PNAE_SUSPENSAS_YEAR', (int) date('Y'))),
                'xlsx_url' => env(
                    'HORIZONTE_PNAE_SUSPENSAS_XLSX_URL',
                    'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/programas/pnae/consultas/entidades-suspensas-1/ENTIDADESSUSPENSAS_29_12_2025.xlsx',
                ),
                'storage_path' => env(
                    'HORIZONTE_PNAE_SUSPENSAS_STORAGE_PATH',
                    'horizonte/alerts/pnae_entidades_suspensas.xlsx',
                ),
                'detail_page_url' => env('HORIZONTE_PNAE_SUSPENSAS_DETAIL_URL', ''),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa — volume de pontos e vista inicial
    |--------------------------------------------------------------------------
    |
    | Bases nacionais (>800 municípios) aplicam recorte inicial (UF prioritária +
    | prospectos) e limitam pontos desenhados para evitar travamento no zoom.
    |
    */

    'map_display' => [
        'heavy_threshold' => max(100, (int) env('HORIZONTE_MAP_HEAVY_THRESHOLD', 800)),
        'max_render_markers' => max(80, min(800, (int) env('HORIZONTE_MAP_MAX_RENDER', 400))),
        /** UF com muitos municípios — limites adaptativos de renderização no mapa. */
        'regional_medium_threshold' => max(80, (int) env('HORIZONTE_MAP_REGIONAL_MEDIUM', 150)),
        'regional_heavy_threshold' => max(150, (int) env('HORIZONTE_MAP_REGIONAL_HEAVY', 300)),
        'regional_max_render_medium' => max(80, min(500, (int) env('HORIZONTE_MAP_REGIONAL_MAX_MEDIUM', 180))),
        'regional_max_render_heavy' => max(60, min(400, (int) env('HORIZONTE_MAP_REGIONAL_MAX_HEAVY', 120))),
        'regional_heat_max' => max(100, (int) env('HORIZONTE_MAP_REGIONAL_HEAT_MAX', 150)),
        /** Máximo de coords aproximadas para resolver overlaps (O(n²) — acima disto confia em clusters). */
        'overlap_max_markers' => max(10, (int) env('HORIZONTE_MAP_OVERLAP_MAX', 80)),
        /** Busca centroide IBGE individual (lento em UF extensa); preferir cache do feed. */
        'fetch_remote_centroids' => filter_var(env('HORIZONTE_MAP_FETCH_REMOTE_CENTROIDS', false), FILTER_VALIDATE_BOOL),
        /** Tempo máximo PHP ao montar recorte regional (UF extensa, ex. MG). */
        'regional_time_limit' => max(60, (int) env('HORIZONTE_MAP_REGIONAL_TIME_LIMIT', 120)),
        /** Tempo máximo PHP ao montar o painel nacional (primeira carga sem cache). */
        'overview_time_limit' => max(60, (int) env('HORIZONTE_MAP_OVERVIEW_TIME_LIMIT', 180)),
        /** Espera máxima (s) por lock de cache quando outro pedido monta o mesmo recorte. */
        'cache_lock_wait_seconds' => max(15, (int) env('HORIZONTE_MAP_CACHE_LOCK_WAIT', 90)),
        /** Todas as UFs com malha mesorregional IBGE abrem vista intermédia antes do detalhe municipal. */
        'meso_overview_threshold' => 0,
        /** Vista inicial GIS/BI — municípios com pressão FUNDEB elevada ou alta propensão. */
        'default_view' => env('HORIZONTE_MAP_DEFAULT_VIEW', 'high_pressure'),
        'financial_pressure_min' => max(0, min(100, (int) env('HORIZONTE_MAP_FINANCIAL_PRESSURE_MIN', 60))),
        'hide_approximate_on_map' => filter_var(env('HORIZONTE_MAP_HIDE_APPROXIMATE', true), FILTER_VALIDATE_BOOL),
    ],

    /** Aquecimento do cache JSON do mapa (`horizonte:warm-map-cache`). */
    'map_cache_warm' => [
        'enabled' => filter_var(env('HORIZONTE_MAP_CACHE_WARM_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
        /** 0 = domingo … 6 = sábado */
        'day_of_week' => max(0, min(6, (int) env('HORIZONTE_MAP_CACHE_WARM_DAY', 0))),
        'time' => env('HORIZONTE_MAP_CACHE_WARM_TIME', '05:30'),
        'overlap_minutes' => max(60, (int) env('HORIZONTE_MAP_CACHE_WARM_OVERLAP_MINUTES', 720)),
    ],

    /** Sincronização dedicada de centroides IBGE (`horizonte:sync-ibge-centroids`). */
    'ibge_centroid_sync' => [
        'delay_ms' => max(0, (int) env('HORIZONTE_IBGE_CENTROID_DELAY_MS', 120)),
        'ufs_per_step' => max(1, (int) env('HORIZONTE_IBGE_CENTROID_UFS_PER_STEP', 1)),
    ],

    /** Importação dedicada de repasses Tesouro (`horizonte:sync-repasses-tesouro`). */
    'tesouro_repasses_sync' => [
        'ufs_per_step' => max(1, (int) env('HORIZONTE_TESOURO_REPASSES_UFS_PER_STEP', 1)),
        'progress_ttl' => max(3600, (int) env('HORIZONTE_TESOURO_REPASSES_PROGRESS_TTL', 604800)),
    ],

    /** Malhas IBGE (UF / mesorregião) para mapa coroplético Horizonte. */
    'geo_malha' => [
        'cache_dir' => env('HORIZONTE_GEO_CACHE_DIR', 'horizonte/geo'),
        'cache_seconds' => max(86400, (int) env('HORIZONTE_GEO_CACHE_SECONDS', 604800)),
        'http_timeout' => max(15, (int) env('HORIZONTE_GEO_HTTP_TIMEOUT', 60)),
        'brazil_uf_url' => env(
            'HORIZONTE_GEO_BRAZIL_UF_URL',
            'https://servicodados.ibge.gov.br/api/v3/malhas/paises/BR?formato=application/vnd.geo+json&qualidade=intermediaria&intrarregiao=UF',
        ),
        'state_meso_url_template' => env(
            'HORIZONTE_GEO_STATE_MESO_URL',
            'https://servicodados.ibge.gov.br/api/v3/malhas/estados/{id}?formato=application/vnd.geo+json&qualidade=intermediaria&intrarregiao=mesorregiao',
        ),
        'state_micro_url_template' => env(
            'HORIZONTE_GEO_STATE_MICRO_URL',
            'https://servicodados.ibge.gov.br/api/v3/malhas/estados/{id}?formato=application/vnd.geo+json&qualidade=intermediaria&intrarregiao=microrregiao',
        ),
        'state_municipal_url_template' => env(
            'HORIZONTE_GEO_STATE_MUNICIPAL_URL',
            'https://servicodados.ibge.gov.br/api/v3/malhas/estados/{id}?formato=application/vnd.geo+json&qualidade={qualidade}&intrarregiao=municipio',
        ),
        'municipal_qualidade' => env('HORIZONTE_GEO_MUNICIPAL_QUALIDADE', 'intermediaria'),
    ],

    /** Malha municipal + área territorial (km²) para mapa Horizonte. */
    'municipal_geo' => [
        'enabled' => filter_var(env('HORIZONTE_MUNICIPAL_GEO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ufs_per_step' => max(1, (int) env('HORIZONTE_MUNICIPAL_GEO_UFS_PER_STEP', 1)),
        'reference_year' => max(2000, (int) env('HORIZONTE_MUNICIPAL_GEO_REFERENCE_YEAR', (int) date('Y'))),
        'metadados_fallback' => filter_var(env('HORIZONTE_MUNICIPAL_GEO_METADADOS_FALLBACK', true), FILTER_VALIDATE_BOOL),
        'metadados_delay_ms' => max(0, (int) env('HORIZONTE_MUNICIPAL_GEO_METADADOS_DELAY_MS', 40)),
        /** Após aquecer catálogo IBGE, importar malha+área da mesma UF. */
        'with_ibge_catalog' => filter_var(env('HORIZONTE_MUNICIPAL_GEO_WITH_IBGE_CATALOG', true), FILTER_VALIDATE_BOOL),
    ],

];
