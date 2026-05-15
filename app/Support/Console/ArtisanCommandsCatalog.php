<?php

namespace App\Support\Console;

/**
 * Catálogo curado dos comandos Artisan do projeto (documentação admin + docs).
 */
final class ArtisanCommandsCatalog
{
    /**
     * @return list<array{
     *   id: string,
     *   title: string,
     *   description: string,
     *   admin_route: ?string,
     *   commands: list<array{
     *     name: string,
     *     summary: string,
     *     signature: string,
     *     examples: list<string>,
     *     env: list<string>,
     *     doc_anchor: ?string
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
                'title' => __('FUNDEB / VAAF'),
                'description' => __('Referências VAAF, VAAT e complementação VAAR por município e ano (fundeb_municipio_references).'),
                'admin_route' => 'admin.ieducar-compatibility.index',
                'commands' => [
                    [
                        'name' => 'fundeb:import-api',
                        'summary' => __('Importa via API CKAN FNDE ou JSON e grava na base.'),
                        'signature' => 'fundeb:import-api {city} {--ano=}',
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
                        'name' => 'fundeb:import-references',
                        'summary' => __('Importa CSV (;) com ibge, ano, vaaf, vaat, complementacao_vaar.'),
                        'signature' => 'fundeb:import-references {path} {--delimiter=;}',
                        'examples' => [
                            'php artisan fundeb:import-references storage/app/fundeb.csv',
                        ],
                        'env' => [],
                        'doc_anchor' => 'fundeb',
                    ],
                ],
            ],
            [
                'id' => 'ieducar',
                'title' => __('Compatibilidade i-Educar'),
                'description' => __('Probe de schema e rotinas de discrepância. Interface: Compatibilidade da base.'),
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
