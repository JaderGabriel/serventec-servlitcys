<?php

namespace App\Support\Console;

/**
 * Catálogo curado dos comandos Artisan do projeto (documentação admin + docs).
 *
 * Campos opcionais por comando: details, schedule, confirm_slugs (referência; slugs activos em confirmSlugs()).
 */
final class ArtisanCommandsCatalog
{
    public static function documentationUrl(): string
    {
        return \App\Support\Admin\DocumentationCatalog::readerUrl('docs/COMANDOS_ARTISAN.md');
    }

    /**
     * Slugs de confirmação efectivos (production) — valores resolvidos a partir de config/.env.
     *
     * @return list<array{
     *   command: string,
     *   slug: string,
     *   slug_template: ?string,
     *   slug_examples: list<string>,
     *   env: ?string,
     *   when: string,
     *   example: string,
     *   notes: ?string
     * }>
     */
    public static function confirmSlugs(): array
    {
        $flushSlug = (string) config('ieducar.admin_sync.flush_confirm_slug', 'zerar-fila-processamento');
        $rebuildTemplate = (string) config('ieducar.finance_realtime.rebuild_confirm_slug', 'rebuild-repasses-{ano}');
        $currentYear = (int) date('Y');
        $rebuildExamples = array_map(
            static fn (int $year): string => str_replace('{ano}', (string) $year, $rebuildTemplate),
            [$currentYear - 1, $currentYear, $currentYear + 1],
        );

        return [
            [
                'command' => 'app:flush-processing-queue',
                'slug' => $flushSlug,
                'slug_template' => null,
                'slug_examples' => [],
                'env' => 'ADMIN_PROCESSING_QUEUE_FLUSH_SLUG',
                'when' => __('Obrigatório em APP_ENV=production (excepto com --dry-run).'),
                'example' => 'php artisan app:flush-processing-queue --confirm='.$flushSlug,
                'notes' => __('Esvazia filas admin-sync e exportações PDF pendentes.'),
            ],
            [
                'command' => 'funding:rebuild-finance-realtime',
                'slug' => str_replace('{ano}', (string) $currentYear, $rebuildTemplate),
                'slug_template' => str_contains($rebuildTemplate, '{ano}') ? $rebuildTemplate : null,
                'slug_examples' => $rebuildExamples,
                'env' => 'IEDUCAR_FINANCE_REALTIME_REBUILD_SLUG',
                'when' => __('Obrigatório em production por ano de repasse (--ano= ou --from/--to).'),
                'example' => 'php artisan funding:rebuild-finance-realtime --all-cities --ano='.$currentYear.' --confirm='.$rebuildExamples[1],
                'notes' => __('Slug por município na tabela de resultado: {nome}-{uf}-{ibge}-{ano} (informativo, não é --confirm).'),
            ],
            [
                'command' => 'cities:reencrypt-db-passwords',
                'slug' => 'reencrypt-db-passwords',
                'slug_template' => null,
                'slug_examples' => [],
                'env' => null,
                'when' => __('Obrigatório em production (slug fixo no código).'),
                'example' => 'php artisan cities:reencrypt-db-passwords --password=... --confirm=reencrypt-db-passwords',
                'notes' => __('Após rotação de APP_KEY — regrava db_password de todas as cidades.'),
            ],
        ];
    }

    /**
     * @return list<array{
     *   id: string,
     *   title: string,
     *   description: string,
     *   admin_route: ?string,
     *   admin_route_query?: array<string, mixed>,
     *   admin_route_fragment?: ?string,
     *   commands: list<array{
     *     name: string,
     *     summary: string,
     *     signature: string,
     *     examples: list<string>,
     *     env: list<string>,
     *     doc_anchor: ?string,
     *     details?: ?string,
     *     schedule?: ?string,
     *     confirm_slugs?: list<string>
     *   }>
     * }>
     */
    public static function categories(): array
    {
        return [
            [
                'id' => 'geo',
                'title' => __('Geográficas (mapa / unidades escolares)'),
                'description' => __('Coordenadas locais (i-Educar), oficiais INEP, microdados e pipeline. Interface: Sincronização geográfica.'),
                'admin_route' => 'admin.geo-sync.index',
                'commands' => [
                    [
                        'name' => 'app:sync-school-unit-geos',
                        'summary' => __('Sincroniza lat/lng locais do i-Educar para school_unit_geos.'),
                        'signature' => 'app:sync-school-unit-geos {--city=} {--only-missing=0}',
                        'examples' => [
                            'php artisan app:sync-school-unit-geos',
                            'php artisan app:sync-school-unit-geos --city=1 --only-missing=1',
                        ],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:sync-school-unit-geos-official',
                        'summary' => __('Coordenadas oficiais INEP (ArcGIS/fallbacks) e divergência vs i-Educar.'),
                        'signature' => 'app:sync-school-unit-geos-official {--city=} {--only-missing=0} {--threshold=}',
                        'examples' => [
                            'php artisan app:sync-school-unit-geos-official --city=1',
                        ],
                        'env' => ['IEDUCAR_INEP_GEO_* (ver config/ieducar.php)'],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:import-inep-microdados-cadastro-escolas-geo',
                        'summary' => __('Microdados INEP (cadastro de escolas) para INEPs ainda sem coordenadas.'),
                        'signature' => 'app:import-inep-microdados-cadastro-escolas-geo {--city=} {--fetch=1} ...',
                        'examples' => [
                            'php artisan app:import-inep-microdados-cadastro-escolas-geo --city=1',
                        ],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:sync-school-unit-geos-pipeline',
                        'summary' => __('Pipeline completo: i-Educar → oficial → microdados.'),
                        'signature' => 'app:sync-school-unit-geos-pipeline {--city=} ...',
                        'examples' => [
                            'php artisan app:sync-school-unit-geos-pipeline --city=1',
                        ],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:probe-inep-geo-fallbacks',
                        'summary' => __('Diagnóstico da cadeia de geocodificação INEP (sem gravar).'),
                        'signature' => 'app:probe-inep-geo-fallbacks {--city=}',
                        'examples' => [
                            'php artisan app:probe-inep-geo-fallbacks --city=1',
                        ],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:export-inep-geo-fallback-csv',
                        'summary' => __('Exporta CSV de escolas para preenchimento manual de coordenadas.'),
                        'signature' => 'app:export-inep-geo-fallback-csv {--city=}',
                        'examples' => ['php artisan app:export-inep-geo-fallback-csv'],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:import-inep-geo-fallback-csv',
                        'summary' => __('Importa CSV de coordenadas para school_unit_geos existentes.'),
                        'signature' => 'app:import-inep-geo-fallback-csv {path}',
                        'examples' => ['php artisan app:import-inep-geo-fallback-csv storage/app/geo_fallback.csv'],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                    [
                        'name' => 'app:index-inep-censo-geo-agg',
                        'summary' => __('Indexa agregados geográficos do Censo na tabela inep_censo_escola_geo_agg.'),
                        'signature' => 'app:index-inep-censo-geo-agg',
                        'examples' => ['php artisan app:index-inep-censo-geo-agg'],
                        'env' => [],
                        'doc_anchor' => 'geograficas',
                    ],
                ],
            ],
            [
                'id' => 'pedagogical',
                'title' => __('Pedagógicas (SAEB)'),
                'description' => __('Importação de indicadores SAEB por município. Interface: Sincronização pedagógica.'),
                'admin_route' => 'admin.pedagogical-sync.index',
                'commands' => [
                    [
                        'name' => 'saeb:import-planilhas-inep',
                        'summary' => __('Planilhas oficiais INEP (aba Municípios) — download RAR/XLSX, conversão e import SAEB.'),
                        'signature' => 'saeb:import-planilhas-inep {--years=} {--url=} {--year=} {--no-download} {--no-merge} ...',
                        'examples' => [
                            'php artisan saeb:import-planilhas-inep --years=2021,2023',
                            'php artisan saeb:import-planilhas-inep --years=2023 --no-download',
                        ],
                        'env' => ['IEDUCAR_SAEB_*'],
                        'doc_anchor' => 'pedagogicas',
                        'details' => __('Requer unrar/p7zip para RAR INEP. Usado pelo Horizonte e Analytics pedagógico.'),
                    ],
                    [
                        'name' => 'saeb:sync-microdados',
                        'summary' => __('Descarrega microdados SAEB (ZIP INEP ou URL CSV) e grava historico.json.'),
                        'signature' => 'saeb:sync-microdados {--year=} {--url=} {--no-merge} ...',
                        'examples' => [
                            'php artisan saeb:sync-microdados --year=2023',
                            'php artisan saeb:sync-microdados --url=https://exemplo/dados.csv',
                        ],
                        'env' => ['IEDUCAR_SAEB_*'],
                        'doc_anchor' => 'pedagogicas',
                    ],
                    [
                        'name' => 'saeb:refresh-ca-bundle',
                        'summary' => __('Actualiza bundle PEM para SSL do download.inep.gov.br (erro cURL 60).'),
                        'signature' => 'saeb:refresh-ca-bundle',
                        'examples' => ['php artisan saeb:refresh-ca-bundle'],
                        'env' => [],
                        'doc_anchor' => 'pedagogicas',
                    ],
                    [
                        'name' => 'saeb:import-official',
                        'summary' => __('Séries SAEB oficiais por IBGE → JSON configurado.'),
                        'signature' => 'saeb:import-official {--city=} {--year=}',
                        'examples' => ['php artisan saeb:import-official --city=1 --year=2023'],
                        'env' => ['IEDUCAR_SAEB_JSON_PATH'],
                        'doc_anchor' => 'pedagogicas',
                    ],
                    [
                        'name' => 'saeb:import-csv',
                        'summary' => __('Importa CSV SAEB (IBGE, ano, disciplina, valor).'),
                        'signature' => 'saeb:import-csv {path} {--city=}',
                        'examples' => ['php artisan saeb:import-csv storage/app/saeb.csv --city=1'],
                        'env' => [],
                        'doc_anchor' => 'pedagogicas',
                    ],
                ],
            ],
            [
                'id' => 'fundeb',
                'title' => __('admin_ieducar_compatibility.hub.tab_label'),
                'description' => __('Referências VAAF, VAAT e complementação VAAR por município e ano (fundeb_municipio_references).'),
                'admin_route' => 'admin.ieducar-compatibility.index',
                'commands' => [
                    [
                        'name' => 'fundeb:import-api',
                        'summary' => __('Importa via API CKAN FNDE ou JSON e grava na base.'),
                        'signature' => 'fundeb:import-api {city} {--ano=} {--all} {--replace} {--nearest}',
                        'examples' => [
                            'php artisan fundeb:import-api 1 --ano=2024',
                        ],
                        'env' => [
                            'IEDUCAR_FUNDEB_CKAN_URL',
                            'IEDUCAR_FUNDEB_CKAN_RESOURCE_ID',
                            'IEDUCAR_FUNDEB_JSON_URL',
                        ],
                        'doc_anchor' => 'fundeb',
                    ],
                    [
                        'name' => 'fundeb:diagnose-matriculas',
                        'summary' => __('Diagnóstico matrículas i-Educar e Censo por município/ano (base VAAF).'),
                        'signature' => 'fundeb:diagnose-matriculas {city?} {--anos=}',
                        'examples' => [
                            'php artisan fundeb:diagnose-matriculas',
                            'php artisan fundeb:diagnose-matriculas 1 --anos=2024,2025,2026',
                        ],
                        'doc_anchor' => 'fundeb',
                    ],
                    [
                        'name' => 'fundeb:import-references',
                        'summary' => __('Importa CSV (;) com ibge, ano, vaaf, vaat, complementacao_vaar.'),
                        'signature' => 'fundeb:import-references {path} {--delimiter=;}',
                        'examples' => [
                            'php artisan fundeb:import-references storage/app/fundeb.csv',
                        ],
                        'env' => [],
                        'doc_anchor' => 'fundeb',
                    ],
                    [
                        'name' => 'fundeb:benchmark',
                        'summary' => __('Benchmark: fluxo completo FUNDEB vs gravação directa na base (diagnóstico de performance).'),
                        'signature' => 'fundeb:benchmark {--cities=} {--years=} {--iterations=3} {--warm-cache}',
                        'examples' => ['php artisan fundeb:benchmark --cities=1,2 --years=2024,2025'],
                        'env' => [],
                        'doc_anchor' => 'fundeb',
                    ],
                ],
            ],
            [
                'id' => 'funding_repasses',
                'title' => __('Repasses / Tempo Real'),
                'description' => __('Repasses municipais observados (CKAN, SISWEB, BB) — municipal_transfer_snapshots e aba Finanças → Tempo Real.'),
                'admin_route' => 'admin.public-data.index',
                'admin_route_query' => ['hub' => 'repasses'],
                'admin_route_fragment' => 'source-repasses_tesouro',
                'commands' => [
                    [
                        'name' => 'funding:rebuild-finance-realtime',
                        'summary' => __('Apaga e reimporta repasses FUNDEB (municipal_transfer_snapshots) para Finanças → Tempo Real.'),
                        'signature' => 'funding:rebuild-finance-realtime {--ano=} {--from=} {--to=} {--city=} {--cities=} {--all-cities} {--purge-only} {--no-purge} {--dry-run} {--confirm=}',
                        'examples' => [
                            'php artisan funding:rebuild-finance-realtime --city=1 --ano=2025',
                            'php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --dry-run',
                            'php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --confirm=rebuild-repasses-2025',
                        ],
                        'env' => [
                            'IEDUCAR_FUNDING_TRANSFERS_ENABLED',
                            'IEDUCAR_FINANCE_REALTIME_REBUILD_SLUG',
                            'IEDUCAR_TESOURO_CSV_ENABLED',
                        ],
                        'doc_anchor' => 'fundeb-repasses',
                        'details' => __('Não confundir com fundeb:import-api (VAAF em fundeb_municipio_references). Totais Tempo Real não somam tesouro_publicacao (total UF).'),
                        'confirm_slugs' => ['rebuild-repasses-{ano}'],
                    ],
                    [
                        'name' => 'weekly-mass-sync:run',
                        'summary' => __('Sincronização massiva semanal (geo, FUNDEB, repasses, SAEB) — enfileira ou retoma com checkpoint.'),
                        'signature' => 'weekly-mass-sync:run {--resume=} {--sync} {--force}',
                        'examples' => [
                            'php artisan weekly-mass-sync:run',
                            'php artisan weekly-mass-sync:run --resume=42',
                        ],
                        'env' => ['ADMIN_SYNC_SCHEDULE_ENABLED'],
                        'doc_anchor' => 'fundeb-repasses',
                        'schedule' => __('Semanal (config admin_sync) — complementa fila admin-sync.'),
                    ],
                ],
            ],
            [
                'id' => 'cadunico',
                'title' => __('CadÚnico / Misocial (MDS)'),
                'description' => __('Agregados municipais CadÚnico — fonte principal SAGI/Misocial; Cecad/CSV como complemento.'),
                'admin_route' => 'admin.cadunico-sync.index',
                'commands' => [
                    [
                        'name' => 'cadunico:import-misocial',
                        'summary' => __('Importação nacional multi-ano via Misocial (sem gap-fill).'),
                        'signature' => 'cadunico:import-misocial {--from=2020} {--to=} {--years=}',
                        'examples' => [
                            'php artisan cadunico:import-misocial --from=2020',
                            'php artisan cadunico:import-misocial --years=2024,2025,2026',
                        ],
                        'env' => [
                            'IEDUCAR_CADUNICO_MISOGIAL_ENABLED',
                            'IEDUCAR_CADUNICO_MISOGIAL_FROM_YEAR',
                        ],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:auto-sync',
                        'summary' => __('Pipeline nacional + lacunas (CKAN/API/CSV).'),
                        'signature' => 'cadunico:auto-sync {--ano=} {--queue} {--no-gap-fill}',
                        'examples' => [
                            'php artisan cadunico:auto-sync --ano=2026',
                            'php artisan cadunico:auto-sync --queue',
                        ],
                        'env' => ['IEDUCAR_CADUNICO_AUTO_SYNC_ENABLED', 'IEDUCAR_CADUNICO_NACIONAL_CSV_URL'],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:sync-city',
                        'summary' => __('Um município ou --all (analytics).'),
                        'signature' => 'cadunico:sync-city {city?} {--ano=} {--all}',
                        'examples' => ['php artisan cadunico:sync-city 1 --ano=2026'],
                        'env' => ['IEDUCAR_CADUNICO_MISOGIAL_ENABLED'],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:import-cecad',
                        'summary' => __('CSV Cecad manual (;) — agregados municipais CadÚnico.'),
                        'signature' => 'cadunico:import-cecad {path} {--ano=}',
                        'examples' => [
                            'php artisan cadunico:import-cecad storage/app/cadunico/cecad/nacional_2024.csv --ano=2024',
                        ],
                        'env' => ['IEDUCAR_CADUNICO_NACIONAL_CSV_URL'],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:import-territorio',
                        'summary' => __('CSV agregado bairro/setor → cadunico_territorio_snapshots.'),
                        'signature' => 'cadunico:import-territorio {path} {--city=} {--ano=}',
                        'examples' => [
                            'php artisan cadunico:import-territorio storage/app/cadunico/territorio/territorio_2910800_2024.csv --city=1 --ano=2024',
                        ],
                        'env' => [],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:sync-territorio',
                        'summary' => __('Mapa IBGE (FTP+WFS) + rateio municipal.'),
                        'signature' => 'cadunico:sync-territorio {city?} {--ano=} {--all} {--queue}',
                        'examples' => [
                            'php artisan cadunico:sync-territorio --all --queue --ano=2025',
                            'php artisan cadunico:sync-city --all --ano=2025',
                        ],
                        'env' => [
                            'IEDUCAR_CADUNICO_TERRITORIO_IBGE_BAIRRO_ZIP',
                            'IEDUCAR_CADUNICO_TERRITORIO_SCHEDULE_ENABLED',
                        ],
                        'doc_anchor' => 'cadunico',
                    ],
                    [
                        'name' => 'cadunico:pull-territorio',
                        'summary' => __('Download URL + import CSV territorial (produção).'),
                        'signature' => 'cadunico:pull-territorio {city?} {--ano=} {--all} {--url=} {--force} {--download-only}',
                        'examples' => [
                            'php artisan cadunico:pull-territorio 1 --ano=2025',
                            'php artisan cadunico:pull-territorio --all --ano=2025',
                        ],
                        'env' => [
                            'IEDUCAR_CADUNICO_TERRITORIO_CSV_URL',
                            'IEDUCAR_CADUNICO_TERRITORIO_CSV_CACHE_DAYS',
                        ],
                        'doc_anchor' => 'cadunico',
                    ],
                ],
            ],
            [
                'id' => 'ieducar',
                'title' => __('admin_ieducar_compatibility.page.title'),
                'description' => __('admin_ieducar_compatibility.page.subtitle'),
                'admin_route' => 'admin.ieducar-compatibility.index',
                'commands' => [
                    [
                        'name' => 'ieducar:schema-probe',
                        'summary' => __('Gera schema_probe.json (rotinas + recurso de prova INEP).'),
                        'signature' => 'ieducar:schema-probe {city} {--output=} {--ano=}',
                        'examples' => [
                            'php artisan ieducar:schema-probe 1 --ano=2024',
                            'php artisan ieducar:schema-probe 1 --output=storage/app/schema_probe.json',
                        ],
                        'env' => [],
                        'doc_anchor' => 'ieducar',
                    ],
                    [
                        'name' => 'ieducar:probe-falta',
                        'summary' => __('Diagnóstico de faltas / presença no i-Educar (município específico).'),
                        'signature' => 'ieducar:probe-falta {city}',
                        'examples' => ['php artisan ieducar:probe-falta 1'],
                        'env' => [],
                        'doc_anchor' => 'ieducar',
                    ],
                ],
            ],
            [
                'id' => 'educacenso',
                'title' => __('Educacenso — conferência 1ª etapa'),
                'description' => __('Cruzamento arquivo INEP .txt com i-Educar read-only. Interface: Analytics → aba Censo.'),
                'admin_route' => null,
                'commands' => [
                    [
                        'name' => 'censo:analyze-educacenso-file',
                        'summary' => __('Analisa arquivo Educacenso do portal INEP vs matrículas i-Educar.'),
                        'signature' => 'censo:analyze-educacenso-file {file} {--city=} {--ano=} {--output=json|table}',
                        'examples' => [
                            'php artisan censo:analyze-educacenso-file tests/fixtures/educacenso/stage1_2026_minimal.txt --city=1 --ano=2026',
                        ],
                        'env' => ['EDUCACENSO_*'],
                        'doc_anchor' => 'educacenso',
                    ],
                ],
            ],
            [
                'id' => 'horizonte',
                'title' => __('Horizonte — inteligência comercial'),
                'description' => __('Abastecimento nacional do mapa de oportunidade municipal (FUNDEB, Censo, SAEB, CadÚnico, IBGE, repasses).'),
                'admin_route' => 'admin.public-data.index',
                'admin_route_query' => ['hub' => 'horizonte'],
                'commands' => [
                    [
                        'name' => 'horizonte:fortnightly-feed',
                        'summary' => __('Pipeline bimestral Horizonte — fases nacionais (FUNDEB, Censo, Educacenso, SAEB, CadÚnico, SIDRA, repasses, IBGE, SGE, alertas, verify).'),
                        'signature' => 'horizonte:fortnightly-feed {--dry-run} {--all} {--staged} {--continue} {--reset} {--phase=} {--skip-*} {--uf=}',
                        'examples' => [
                            'php artisan horizonte:fortnightly-feed --staged --reset',
                            'php artisan horizonte:fortnightly-feed --staged --continue',
                            'php artisan horizonte:fortnightly-feed --phase=educacenso',
                            'php artisan horizonte:fortnightly-feed --phase=saeb_planilhas',
                            'php artisan horizonte:fortnightly-feed --phase=ibge_catalog',
                            'php artisan horizonte:fortnightly-feed --dry-run',
                            'php artisan horizonte:fortnightly-feed --uf=SP --phase=ibge_catalog',
                        ],
                        'env' => [
                            'HORIZONTE_FORTNIGHTLY_FEED_ENABLED',
                            'HORIZONTE_FORTNIGHTLY_FEED_SCHEDULE_ENABLED',
                            'HORIZONTE_FORTNIGHTLY_FEED_STAGED',
                            'HORIZONTE_FORTNIGHTLY_FEED_STEP_INTERVAL',
                            'HORIZONTE_REFERENCE_YEAR',
                            'HORIZONTE_EDUCACENSO_ENABLED',
                            'HORIZONTE_EDUCACENSO_YEARS_PER_STEP',
                        ],
                        'doc_anchor' => 'horizonte',
                        'details' => __('Modo --staged recomendado em produção (1 fase por invocação). Fases: fundeb_receita, censo_matriculas, educacenso, cadunico_sync, sidra_demography, repasses_tesouro, saeb_planilhas, ibge_catalog, sge_registry, municipal_alerts, official_check. Educacenso, SAEB, IBGE e SIDRA aceitam --phase isolado (repetir até concluir; --reset recomeça). Repasses importam referência + ano vigente.'),
                        'schedule' => __('Bimestral — dia 1 nos meses 1, 3, 5, 7, 9, 11 + passos --continue a cada HORIZONTE_FORTNIGHTLY_FEED_STEP_INTERVAL min.'),
                    ],
                    [
                        'name' => 'horizonte:verify-educacenso-coverage',
                        'summary' => __('Audita cobertura da janela Educacenso em municípios aleatórios (gráfico Horizonte §6.9).'),
                        'signature' => 'horizonte:verify-educacenso-coverage {--sample=50} {--seed=} {--json}',
                        'examples' => [
                            'php artisan horizonte:verify-educacenso-coverage --sample=50',
                            'php artisan horizonte:verify-educacenso-coverage --sample=50 --seed=20260701',
                            'php artisan horizonte:verify-educacenso-coverage --json',
                        ],
                        'env' => [
                            'HORIZONTE_ENROLLMENT_SERIES_YEARS',
                            'HORIZONTE_REFERENCE_YEAR',
                        ],
                        'doc_anchor' => 'horizonte',
                    ],
                    [
                        'name' => 'horizonte:sync-repasses-tesouro',
                        'summary' => __('Repasses Tesouro CKAN — ano vigente (YTD) e opcionalmente referência Horizonte, por UF retomável.'),
                        'signature' => 'horizonte:sync-repasses-tesouro {--year=} {--with-ref} {--ref-only} {--uf=} {--continue} {--reset} {--ufs-per-step=} {--dry-run}',
                        'examples' => [
                            'php artisan horizonte:sync-repasses-tesouro',
                            'php artisan horizonte:sync-repasses-tesouro --with-ref',
                            'php artisan horizonte:sync-repasses-tesouro --uf=BA',
                            'php artisan horizonte:sync-repasses-tesouro --continue',
                            'php artisan horizonte:sync-repasses-tesouro --dry-run',
                        ],
                        'env' => [
                            'HORIZONTE_TESOURO_REPASSES_UFS_PER_STEP',
                            'HORIZONTE_REFERENCE_YEAR',
                        ],
                        'doc_anchor' => 'horizonte',
                    ],
                    [
                        'name' => 'horizonte:sync-ibge-centroids',
                        'summary' => __('Sincroniza centroides IBGE de todos os municípios (UFs menores primeiro, retomável).'),
                        'signature' => 'horizonte:sync-ibge-centroids {--reset} {--ufs-per-step=} {--uf=} {--force} {--dry-run} {--delay=}',
                        'examples' => [
                            'php artisan horizonte:sync-ibge-centroids --reset',
                            'php artisan horizonte:sync-ibge-centroids',
                            'php artisan horizonte:sync-ibge-centroids --uf=RR -v',
                            'php artisan horizonte:sync-ibge-centroids --dry-run',
                        ],
                        'env' => [
                            'HORIZONTE_IBGE_CENTROID_DELAY_MS',
                            'HORIZONTE_IBGE_CENTROID_UFS_PER_STEP',
                        ],
                        'doc_anchor' => 'horizonte',
                        'details' => __('Uma UF por invocação por defeito. Grava cache ibge_municipality_centroid:{ibge} e invalida o mapa Horizonte ao concluir cada passo.'),
                    ],
                    [
                        'name' => 'horizonte:sync-municipal-alerts',
                        'summary' => __('Importa alertas oficiais MEC/FNDE (VAAT inabilitados, registo manual) para o modal municipal.'),
                        'signature' => 'horizonte:sync-municipal-alerts {--uf=} {--skip-fnde} {--dry-run} {--reset}',
                        'examples' => [
                            'php artisan horizonte:sync-municipal-alerts',
                            'php artisan horizonte:sync-municipal-alerts --dry-run',
                            'php artisan horizonte:sync-municipal-alerts --uf=BA',
                        ],
                        'env' => [
                            'HORIZONTE_MUNICIPAL_ALERTS_ENABLED',
                            'HORIZONTE_FNDE_VAAT_INABILITADOS_CSV_URL',
                            'HORIZONTE_FNDE_VAAT_INABILITADOS_PDF_URL',
                            'HORIZONTE_MUNICIPAL_ALERTS_PATH',
                        ],
                        'doc_anchor' => 'horizonte',
                        'details' => __('Fonte principal: CSV oficial FNDE VAAT inabilitados (HORIZONTE_FNDE_VAAT_INABILITADOS_CSV_URL). Fallback PDF se o CSV falhar. Registo JSON opcional. Três estados no modal: pendência encontrada, sem pendências, não verificado.'),
                    ],
                    [
                        'name' => 'horizonte:export-data-bundle',
                        'summary' => __('Exporta pacote ZIP Horizonte (FUNDEB, Censo, SAEB, CadÚnico, SIDRA, repasses, IBGE, SGE).'),
                        'signature' => 'horizonte:export-data-bundle {--output=} {--skip-*}',
                        'examples' => ['php artisan horizonte:export-data-bundle'],
                        'env' => ['HORIZONTE_ENABLED'],
                        'doc_anchor' => 'horizonte',
                    ],
                    [
                        'name' => 'horizonte:import-data-bundle',
                        'summary' => __('Importa pacote ZIP exportado localmente.'),
                        'signature' => 'horizonte:import-data-bundle {path} {--dry-run} {--only=}',
                        'examples' => [
                            'php artisan horizonte:import-data-bundle storage/app/horizonte/bundles/latest.zip',
                            'php artisan horizonte:import-data-bundle storage/app/horizonte/bundles/latest.zip --only=fundeb,censo,cadunico,demography,transfers',
                        ],
                        'env' => ['HORIZONTE_ENABLED'],
                        'doc_anchor' => 'horizonte',
                    ],
                ],
            ],
            [
                'id' => 'monitor',
                'title' => __('Monitor de módulos'),
                'description' => __('Sondas estruturais de saúde por módulo (sync, conexões, PDF, fontes públicas). Interface: Operação → Monitor de módulos.'),
                'admin_route' => 'admin.module-monitor.index',
                'commands' => [
                    [
                        'name' => 'module-monitor:collect',
                        'summary' => __('Recolhe sinais de saúde por módulo e grava cache usado na UI.'),
                        'signature' => 'module-monitor:collect {--dry-run}',
                        'examples' => [
                            'php artisan module-monitor:collect',
                            'php artisan module-monitor:collect --dry-run',
                        ],
                        'env' => [
                            'MODULE_MONITOR_ENABLED',
                            'MODULE_MONITOR_COLLECT_SCHEDULE_ENABLED',
                            'MODULE_MONITOR_COLLECT_INTERVAL_MINUTES',
                        ],
                        'doc_anchor' => 'operacao',
                        'schedule' => __('A cada MODULE_MONITOR_COLLECT_INTERVAL_MINUTES (default 10 min) via schedule:run.'),
                    ],
                    [
                        'name' => 'public-data:check-official',
                        'summary' => __('Verifica fontes oficiais de dados públicos e notifica admins (não importa dados).'),
                        'signature' => 'public-data:check-official {--no-notify}',
                        'examples' => [
                            'php artisan public-data:check-official',
                            'php artisan public-data:check-official --no-notify',
                        ],
                        'env' => [
                            'PUBLIC_DATA_DAILY_CHECK_ENABLED',
                            'PUBLIC_DATA_DAILY_CHECK_TIME',
                        ],
                        'doc_anchor' => 'operacao',
                        'schedule' => __('Diário (PUBLIC_DATA_DAILY_CHECK_TIME, default 07:00).'),
                    ],
                ],
            ],
            [
                'id' => 'ops',
                'title' => __('Operação Laravel (referência)'),
                'description' => __('Comandos úteis em deploy e desenvolvimento.'),
                'admin_route' => null,
                'commands' => [
                    [
                        'name' => 'migrate',
                        'summary' => __('Aplica migrações da base da aplicação.'),
                        'signature' => 'migrate {--force}',
                        'examples' => [
                            'php artisan migrate',
                            'php artisan migrate --force',
                        ],
                        'env' => ['DB_*'],
                        'doc_anchor' => 'operacao',
                    ],
                    [
                        'name' => 'config:cache',
                        'summary' => __('Cache de configuração (produção).'),
                        'signature' => 'config:cache',
                        'examples' => ['php artisan config:cache'],
                        'env' => [],
                        'doc_anchor' => 'operacao',
                    ],
                    [
                        'name' => 'view:cache',
                        'summary' => __('Compila views Blade.'),
                        'signature' => 'view:cache',
                        'examples' => ['php artisan view:cache'],
                        'env' => [],
                        'doc_anchor' => 'operacao',
                    ],
                    [
                        'name' => 'test',
                        'summary' => __('Executa testes PHPUnit.'),
                        'signature' => 'test {--filter=}',
                        'examples' => [
                            'php artisan test',
                            'composer test',
                        ],
                        'env' => [],
                        'doc_anchor' => 'operacao',
                    ],
                    [
                        'name' => 'cities:reencrypt-db-passwords',
                        'summary' => __('Regrava db_password de todas as cidades com APP_KEY actual (pós key:generate).'),
                        'signature' => 'cities:reencrypt-db-passwords {--password=} {--probe} {--dry-run} {--confirm=}',
                        'examples' => [
                            'php artisan cities:reencrypt-db-passwords --password=... --dry-run',
                            'php artisan cities:reencrypt-db-passwords --password=... --confirm=reencrypt-db-passwords',
                        ],
                        'env' => ['APP_KEY'],
                        'doc_anchor' => 'operacao',
                        'confirm_slugs' => ['reencrypt-db-passwords'],
                        'details' => __('Use após «The MAC is invalid» na conexão i-Educar. Mesma senha aplicada a todas as cidades.'),
                    ],
                    [
                        'name' => 'app:flush-processing-queue',
                        'summary' => __('Esvazia filas admin (sync + PDF) e jobs pendentes; em production exige --confirm=slug.'),
                        'signature' => 'app:flush-processing-queue {--confirm=} {--only-sync} {--only-pdf} {--include-failed} {--include-completed} {--dry-run}',
                        'examples' => [
                            'php artisan app:flush-processing-queue --dry-run',
                            'php artisan app:flush-processing-queue --confirm=zerar-fila-processamento',
                        ],
                        'env' => ['ADMIN_PROCESSING_QUEUE_FLUSH_SLUG'],
                        'doc_anchor' => 'operacao',
                        'confirm_slugs' => ['zerar-fila-processamento'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function commandNames(): array
    {
        $names = [];
        foreach (self::categories() as $cat) {
            foreach ($cat['commands'] as $cmd) {
                $names[] = $cmd['name'];
            }
        }

        return $names;
    }
}
