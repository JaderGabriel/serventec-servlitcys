<?php

namespace App\Support\Admin;

/**
 * Fontes de dados públicos (fora do i-Educar) usadas na consultoria e no relatório PDF ATM.
 *
 * @see docs/IMPORTACAO_DADOS_PUBLICOS.md
 */
final class PublicDataImportCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     summary: string,
     *     data_class: string,
     *     domain: string,
     *     persistence: string,
     *     official_sources: list<string>,
     *     pdf_sections: list<string>,
     *     pdf_gaps: list<string>,
     *     admin_route: ?string,
     *     actions: list<array{
     *         key: string,
     *         label: string,
     *         task_domain: string,
     *         task_key: string,
     *         needs_city: bool,
     *         needs_year: bool,
     *         needs_years_range: bool,
     *         hint: ?string
     *     }>,
     *     cli: list<string>,
     *     cli_examples: list<array{command: string, summary?: string}>
     * }>
     */
    public static function sources(): array
    {
        return [
            self::fundebSource(),
            self::cadunicoCecadSource(),
            self::censoMatriculasSource(),
            self::repassesSource(),
            self::saebSource(),
            self::geoInepSource(),
            self::weeklyMassSource(),
        ];
    }

    public static function findSource(string $id): ?array
    {
        foreach (self::sources() as $source) {
            if ($source['id'] === $id) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return list<array{source_id: string, gap_code: string, section: string}>
     */
    public static function gapIndex(): array
    {
        $rows = [];
        foreach (self::sources() as $source) {
            foreach ($source['pdf_gaps'] as $gap) {
                $rows[] = [
                    'source_id' => $source['id'],
                    'gap_code' => $gap,
                    'section' => self::gapSection($gap),
                ];
            }
        }

        return $rows;
    }

    /**
     * Atalho para rotina de importação (hub + CLI) usado em alertas automáticos.
     *
     * @return array{hub_url: string, cli: ?string, label: ?string}
     */
    public static function routineHint(string $sourceId): array
    {
        $source = self::findSource($sourceId);
        if ($source === null) {
            return [
                'hub_url' => route('admin.public-data.index'),
                'cli' => null,
                'label' => null,
            ];
        }

        $adminRoute = $source['admin_route'] ?? null;
        $hubUrl = is_string($adminRoute) && $adminRoute !== ''
            ? route($adminRoute)
            : route('admin.public-data.index').'#source-'.$sourceId;

        $cli = $source['cli'][0] ?? null;
        $action = $source['actions'][0] ?? null;

        return [
            'hub_url' => $hubUrl,
            'cli' => is_string($cli) ? $cli : null,
            'label' => is_array($action) ? ($action['label'] ?? null) : null,
        ];
    }

    private static function gapSection(string $gapCode): string
    {
        return match ($gapCode) {
            'censo_municipio_missing', 'network_breakdown_missing', 'ei_censo_etapa', 'infra_censo_missing' => 'indicadores_educacionais',
            'fundeb_projection_missing' => 'fundeb',
            'salario_educacao_not_tracked', 'programs_empty', 'mec_programs_api' => 'programas_universais',
            'saeb_missing', 'ideb_series_missing' => 'desempenho_aprendizagem',
            'map_unavailable' => 'territorio_rede',
            'ibge_socio_missing' => 'indicadores_educacionais',
            'cadunico_previsao_missing' => 'cadunico_previsao',
            default => 'geral',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function fundebSource(): array
    {
        return [
            'id' => 'fundeb_fnde',
            'title' => __('FUNDEB — VAAF, VAAT e complementação VAAR'),
            'summary' => __('CKAN FNDE, cache JSON e CSV «Receita total do Fundeb» (portarias). Alimenta comparativos, projeção na consultoria, PDF e mapa Horizonte (pressão FUNDEB).'),
            'data_class' => 'publicado',
            'domain' => 'fundeb',
            'persistence' => 'fundeb_municipio_references',
            'official_sources' => [
                'FNDE dados abertos (CKAN)',
                'CSV receita por ente (gov.br/FUNDEB)',
            ],
            'pdf_sections' => ['fundeb', 'rede_municipal'],
            'pdf_gaps' => ['fundeb_projection_missing'],
            'admin_route' => 'admin.ieducar-compatibility.index',
            'actions' => [
                [
                    'key' => 'import_city_year',
                    'label' => __('Importar município + ano'),
                    'task_domain' => 'fundeb',
                    'task_key' => 'import_city_year',
                    'needs_city' => true,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('Grava referência municipal; use «ano mais próximo» se o CKAN não tiver o exercício.'),
                ],
                [
                    'key' => 'import_bulk_year',
                    'label' => __('Importar todos os municípios (um ano)'),
                    'task_domain' => 'fundeb',
                    'task_key' => 'import_bulk_year',
                    'needs_city' => false,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'sync_all_years',
                    'label' => __('Sincronizar intervalo de anos (config + formulário)'),
                    'task_domain' => 'fundeb',
                    'task_key' => 'sync_all_years',
                    'needs_city' => false,
                    'needs_year' => false,
                    'needs_years_range' => true,
                    'hint' => __('Equivalente ao botão na Compatibilidade i-Educar.'),
                ],
            ],
            'cli' => ['fundeb:import-api', 'fundeb:import-references'],
            'cli_examples' => [
                ['summary' => __('Um município e ano'), 'command' => 'php artisan fundeb:import-api --city=1 --ano=2025'],
                ['summary' => __('Todos os municípios (um ano)'), 'command' => 'php artisan fundeb:import-api --all-cities --ano=2025'],
                ['summary' => __('CSV de referências (VAAF/VAAT/VAAR)'), 'command' => 'php artisan fundeb:import-references storage/app/fundeb.csv'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cadunicoCecadSource(): array
    {
        return [
            'id' => 'cadunico_cecad',
            'title' => __('CadÚnico / Cecad — agregados municipais'),
            'summary' => __('Sincronização automática (API/CKAN → cache → CSV Cecad). Alimenta a aba CadÚnico e a previsão de crianças fora da rede municipal.'),
            'data_class' => 'publicado',
            'domain' => 'cadastro',
            'persistence' => 'cadunico_municipio_snapshots',
            'official_sources' => [
                'Cecad — Cadastro Único (MDS)',
                'https://www.gov.br/mds/pt-br/acoes-e-programas/cadastro-unico',
            ],
            'pdf_sections' => ['cadastro_censo', 'indicadores_educacionais'],
            'pdf_gaps' => ['cadunico_previsao_missing'],
            'admin_route' => 'admin.cadunico-sync.index',
            'actions' => [
                [
                    'key' => 'auto_sync',
                    'label' => __('Sincronização automática (download URL + nacional + lacunas)'),
                    'task_domain' => 'cadastro',
                    'task_key' => 'auto_sync',
                    'needs_city' => false,
                    'needs_year' => false,
                    'needs_years_range' => false,
                    'hint' => __('Sem upload: descarrega IEDUCAR_CADUNICO_NACIONAL_CSV_URL, importa nacional_{ano}.csv e preenche municípios em falta via API.'),
                ],
                [
                    'key' => 'import_city_year',
                    'label' => __('Sincronizar um município e ano'),
                    'task_domain' => 'cadastro',
                    'task_key' => 'import_city_year',
                    'needs_city' => true,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('API/CKAN → cache → CSV local.'),
                ],
                [
                    'key' => 'auto_sync_year',
                    'label' => __('Automático para um ano'),
                    'task_domain' => 'cadastro',
                    'task_key' => 'auto_sync',
                    'needs_city' => false,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('Download + import nacional + lacunas para o ano indicado.'),
                ],
            ],
            'cli' => [
                'cadunico:import-misocial',
                'cadunico:auto-sync',
                'cadunico:sync-city',
                'cadunico:import-cecad',
            ],
            'cli_examples' => [
                ['summary' => __('Nacional multi-ano (Misocial)'), 'command' => 'php artisan cadunico:import-misocial --from=2020'],
                ['summary' => __('Automático (download + lacunas)'), 'command' => 'php artisan cadunico:auto-sync --ano=2026'],
                ['summary' => __('Um município'), 'command' => 'php artisan cadunico:sync-city 1 --ano=2026'],
                ['summary' => __('CSV Cecad manual'), 'command' => 'php artisan cadunico:import-cecad storage/app/cecad.csv --ano=2024'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function censoMatriculasSource(): array
    {
        return [
            'id' => 'censo_inep_matriculas',
            'title' => __('Censo INEP — matrículas por município'),
            'summary' => __('Agrega microdados Educacenso (qt_mat_*) em inep_censo_municipio_matriculas. Alimenta consultoria e PDF — não substitui matrículas i-Educar. A série multi-ano do gráfico Horizonte fica no hub Horizonte (Educacenso).'),
            'data_class' => 'publicado',
            'domain' => 'funding',
            'persistence' => 'inep_censo_municipio_matriculas',
            'official_sources' => [
                'INEP microdados Censo Escolar',
                'Portarias MEC (resultados finais)',
            ],
            'pdf_sections' => ['indicadores_educacionais', 'cadastro_censo', 'educacao_infantil'],
            'pdf_gaps' => ['censo_municipio_missing', 'network_breakdown_missing', 'ei_censo_etapa', 'infra_censo_missing'],
            'admin_route' => 'admin.geo-sync.index',
            'actions' => [
                [
                    'key' => 'index_censo_matriculas',
                    'label' => __('Indexar matrículas a partir do CSV de microdados'),
                    'task_domain' => 'funding',
                    'task_key' => 'index_censo_matriculas',
                    'needs_city' => false,
                    'needs_year' => false,
                    'needs_years_range' => false,
                    'hint' => __('Requer arquivo em storage (pipeline geo passo 3 ou download manual).'),
                ],
            ],
            'horizonte_link' => [
                'route' => 'admin.horizonte-import.index',
                'fragment' => 'horizonte-educacenso-sync',
                'label' => __('Educacenso nacional (gráfico Horizonte)'),
                'hint' => __('Reimportação ano × UF — hub Horizonte, não consultoria municipal.'),
            ],
            'cli' => ['inep:index-censo-geo-agg', 'inep:import-microdados-cadastro-escolas-geo'],
            'cli_examples' => [
                ['summary' => __('Indexar matrículas a partir do CSV de microdados'), 'command' => 'php artisan inep:index-censo-geo-agg'],
                ['summary' => __('Importar microdados + coordenadas escolas'), 'command' => 'php artisan inep:import-microdados-cadastro-escolas-geo storage/app/microdados.csv'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function repassesSource(): array
    {
        return [
            'id' => 'repasses_tesouro',
            'title' => __('Repasses observados — FUNDEB (Tempo Real)'),
            'summary' => __('Importação municipal com granularidade dia/mês (CKAN, SISWEB, BB). Não grava total da UF na importação normal; use Rebuild para purgar e reimportar snapshots.'),
            'data_class' => 'publicado',
            'domain' => 'funding',
            'persistence' => 'municipal_transfer_snapshots',
            'official_sources' => [
                'Tesouro Transparente — publicação FUNDEB (planilha)',
                'SISWEB — Transferências Constitucionais (REPASSES)',
                'Banco do Brasil — demonstrativos.apps.bb.com.br/extrato',
                'CKAN Tesouro Transparente (municipal)',
                'Portal da Transparência (opcional)',
            ],
            'pdf_sections' => ['programas_universais', 'salario_educacao'],
            'pdf_gaps' => ['programs_empty', 'mec_programs_api', 'salario_educacao_not_tracked'],
            'admin_route' => 'admin.public-data.index',
            'consultoria_tab' => 'finance_realtime',
            'theme_accent' => 'emerald',
            'theme_icon' => 'banknotes',
            'actions' => [
                [
                    'key' => 'import_transfers_city_year',
                    'label' => __('Importar repasses municipais (município + ano)'),
                    'task_domain' => 'funding',
                    'task_key' => 'import_transfers_city_year',
                    'needs_city' => true,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('CKAN/SISWEB/BB com meta.mensal — sem publicação STN por UF.'),
                ],
                [
                    'key' => 'import_transfers_all_cities',
                    'label' => __('Importar repasses — todos os municípios (um ano)'),
                    'task_domain' => 'funding',
                    'task_key' => 'import_transfers_city_year',
                    'needs_city' => false,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('Enfileira uma tarefa por município com IBGE.'),
                ],
                [
                    'key' => 'rebuild_finance_realtime_city_year',
                    'label' => __('Rebuild Tempo Real (município + ano)'),
                    'task_domain' => 'funding',
                    'task_key' => 'rebuild_finance_realtime',
                    'needs_city' => true,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('Apaga snapshots do ano/município e reimporta só fontes municipais com granularidade.'),
                ],
                [
                    'key' => 'rebuild_finance_realtime_all_cities',
                    'label' => __('Rebuild Tempo Real — todos os municípios (um ano)'),
                    'task_domain' => 'funding',
                    'task_key' => 'rebuild_finance_realtime',
                    'needs_city' => false,
                    'needs_year' => true,
                    'needs_years_range' => false,
                    'hint' => __('Uma tarefa: purga + reimporta todos os municípios analytics (equivalente ao Artisan rebuild).'),
                ],
            ],
            'cli' => [
                'funding:rebuild-finance-realtime',
            ],
            'cli_examples' => [
                ['summary' => __('Rebuild um município'), 'command' => 'php artisan funding:rebuild-finance-realtime --city=1 --ano=2025'],
                ['summary' => __('Simular rebuild nacional'), 'command' => 'php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --dry-run'],
                ['summary' => __('Rebuild todos os municípios'), 'command' => 'php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --confirm=rebuild-repasses-2025'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function saebSource(): array
    {
        return [
            'id' => 'saeb_inep',
            'title' => __('SAEB / desempenho (INEP)'),
            'summary' => __('Microdados, CSV, planilhas INEP ou JSON por IBGE. Alimenta Desempenho, PDF e mapa Horizonte (déficit pedagógico SAEB).'),
            'data_class' => 'publicado',
            'domain' => 'pedagogical',
            'persistence' => 'saeb_indicator_points',
            'official_sources' => [
                'INEP microdados SAEB',
                'Portal IDEB (referência)',
            ],
            'pdf_sections' => ['desempenho_aprendizagem', 'rede_municipal'],
            'pdf_gaps' => ['saeb_missing', 'ideb_series_missing'],
            'admin_route' => 'admin.pedagogical-sync.index',
            'actions' => [],
            'cli' => ['saeb:sync-microdados', 'saeb:import-official', 'saeb:import-csv'],
            'cli_examples' => [
                ['summary' => __('Microdados SAEB'), 'command' => 'php artisan saeb:sync-microdados'],
                ['summary' => __('Planilhas oficiais INEP'), 'command' => 'php artisan saeb:import-official'],
                ['summary' => __('CSV local'), 'command' => 'php artisan saeb:import-csv storage/app/saeb.csv'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function geoInepSource(): array
    {
        return [
            'id' => 'geo_inep',
            'title' => __('Georreferenciação INEP'),
            'summary' => __('Coordenadas INEP, microdados e catálogo IBGE municipal (centroides) — mapa de unidades, divergências geo e posicionamento no Horizonte.'),
            'data_class' => 'publicado',
            'domain' => 'geo',
            'persistence' => 'school_unit_geos / inep_school_geos',
            'official_sources' => [
                'Catálogo INEP / ArcGIS',
                'Microdados Censo (cadastro escolas)',
            ],
            'pdf_sections' => ['territorio_rede'],
            'pdf_gaps' => ['map_unavailable'],
            'admin_route' => 'admin.geo-sync.index',
            'actions' => [],
            'cli' => ['geo:sync-inep', 'inep:probe-geo-fallbacks', 'inep:import-microdados-cadastro-escolas-geo'],
            'cli_examples' => [
                ['summary' => __('Sincronizar coordenadas INEP'), 'command' => 'php artisan geo:sync-inep'],
                ['summary' => __('Diagnosticar fallbacks geo'), 'command' => 'php artisan inep:probe-geo-fallbacks'],
                ['summary' => __('Microdados cadastro escolas + geo'), 'command' => 'php artisan inep:import-microdados-cadastro-escolas-geo storage/app/microdados.csv'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function weeklyMassSource(): array
    {
        return [
            'id' => 'weekly_mass_sync',
            'title' => __('Rotina semanal completa'),
            'summary' => __('Executa em sequência: geo, FUNDEB, repasses, indexação Censo e SAEB (checkpoint retomável).'),
            'data_class' => 'publicado',
            'domain' => 'system',
            'persistence' => __('várias tabelas'),
            'official_sources' => [__('Orquestração das fontes acima')],
            'pdf_sections' => ['indicadores_educacionais', 'fundeb', 'desempenho_aprendizagem', 'territorio_rede'],
            'pdf_gaps' => [],
            'admin_route' => 'admin.sync-queue.index',
            'actions' => [
                [
                    'key' => 'weekly_mass_sync',
                    'label' => __('Enfileirar sincronização massiva semanal'),
                    'task_domain' => 'system',
                    'task_key' => 'weekly_mass_sync',
                    'needs_city' => false,
                    'needs_year' => false,
                    'needs_years_range' => false,
                    'hint' => __('Pode demorar horas; acompanhe na fila de processamento.'),
                ],
            ],
            'cli' => ['weekly-mass-sync:run', 'public-data:check-official'],
            'cli_examples' => [
                ['summary' => __('Orquestração semanal (geo, FUNDEB, repasses, Censo, SAEB)'), 'command' => 'php artisan weekly-mass-sync:run'],
                ['summary' => __('Retomar checkpoint'), 'command' => 'php artisan weekly-mass-sync:run --resume=42'],
                ['summary' => __('Verificar fontes oficiais (read-only)'), 'command' => 'php artisan public-data:check-official'],
                ['summary' => __('Verificação sem notificação'), 'command' => 'php artisan public-data:check-official --no-notify'],
            ],
        ];
    }
}
