<?php

namespace App\Services\Admin;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalAreaSnapshot;
use App\Models\MunicipalDemographySnapshot;
use App\Models\MunicipalFiscalSnapshot;
use App\Models\MunicipalTransparencySnapshot;
use App\Models\SaebIndicatorPoint;
use App\Support\Admin\PublicDataImportCatalog;
use App\Services\Admin\PublicDataOfficialCheckCache;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use App\Support\Horizonte\HorizonteFortnightlyFeedCache;
use App\Support\Horizonte\HorizonteFortnightlyFeedScheduleCadence;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use App\Support\Horizonte\HorizonteIbgeMunicipalGeoImportProgress;
use App\Support\Horizonte\HorizonteIbgeWarmProgress;
use App\Support\Horizonte\HorizonteMunicipalAlertsCache;
use App\Support\Horizonte\HorizonteSidraImportProgress;
use App\Support\Horizonte\HorizonteSiconfiSyncProgress;
use App\Support\Horizonte\HorizonteSaebImportProgress;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Support\Facades\Storage;

/**
 * Cobertura nacional e metadados da rotina Horizonte para o hub Dados públicos.
 */
final class HorizonteImportHubStatusService
{
    /** @var list<string> */
    private const BRAZIL_UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $fundebIbges = FundebMunicipioReference::query()
            ->whereNotNull('ibge_municipio')
            ->distinct()
            ->pluck('ibge_municipio')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $censoIbges = InepCensoMunicipioMatricula::query()
            ->whereNotNull('ibge_municipio')
            ->distinct()
            ->pluck('ibge_municipio')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $saebIbges = SaebIndicatorPoint::query()
            ->whereNotNull('ibge_municipio')
            ->distinct()
            ->pluck('ibge_municipio')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $fundebSet = array_flip($fundebIbges);
        $censoSet = array_flip($censoIbges);
        $saebSet = array_flip($saebIbges);

        $universe = array_unique(array_merge($fundebIbges, $censoIbges, $saebIbges));
        $withFullTriad = 0;
        foreach ($universe as $ibge) {
            if (isset($fundebSet[$ibge], $censoSet[$ibge], $saebSet[$ibge])) {
                $withFullTriad++;
            }
        }

        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $microdadosPath = InepMicrodadosCadastroEscolasPath::resolve($rel);

        $ibgeUfsWarmed = $this->countIbgeUfsWarmed();
        $ibgeUfsTotal = count(self::BRAZIL_UFS);
        $ibgeGeoUfsDone = HorizonteIbgeMunicipalGeoImportProgress::doneCount();
        $ibgeGeoUfsTotal = HorizonteIbgeMunicipalGeoImportProgress::totalUfs();
        $municipalAreaCount = \Illuminate\Support\Facades\Schema::hasTable('municipal_area_snapshots')
            ? (int) MunicipalAreaSnapshot::query()->distinct()->count('ibge_municipio')
            : 0;
        $cadunicoCount = \Illuminate\Support\Facades\Schema::hasTable('cadunico_municipio_snapshots')
            ? CadunicoMunicipioSnapshot::query()->distinct()->count('ibge_municipio')
            : 0;
        $demographyCount = \Illuminate\Support\Facades\Schema::hasTable('municipal_demography_snapshots')
            ? MunicipalDemographySnapshot::query()->distinct()->count('ibge_municipio')
            : 0;

        $educacensoWindow = HorizonteEducacensoYearWindow::years();
        $educacensoYearsIndexed = \Illuminate\Support\Facades\Schema::hasTable('inep_censo_municipio_matriculas')
            ? (int) InepCensoMunicipioMatricula::query()
                ->whereIn('ano', $educacensoWindow)
                ->where('matriculas_total', '>', 0)
                ->distinct()
                ->count('ano')
            : 0;

        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $siconfiPeriod = max(1, min(6, (int) config('horizonte.siconfi.period', 6)));
        $siconfiMunicipios = \Illuminate\Support\Facades\Schema::hasTable('municipal_fiscal_snapshots')
            ? (int) MunicipalFiscalSnapshot::query()
                ->where('ano', $refYear)
                ->whereNotNull('ibge_municipio')
                ->distinct()
                ->count('ibge_municipio')
            : 0;
        $transparencyMunicipios = \Illuminate\Support\Facades\Schema::hasTable('municipal_transparency_snapshots')
            ? (int) MunicipalTransparencySnapshot::query()
                ->whereNotNull('ibge_municipio')
                ->distinct()
                ->count('ibge_municipio')
            : 0;
        $transparencyApiKey = trim((string) config('ieducar.other_funding.public_queries.portal_transparencia.api_key', ''));
        $alertsMeta = HorizonteMunicipalAlertsCache::getMeta();

        return [
            'enabled' => (bool) config('horizonte.enabled', true),
            'feed_enabled' => (bool) config('horizonte.fortnightly_feed.enabled', true),
            'schedule_enabled' => (bool) config('horizonte.fortnightly_feed.schedule.enabled', true),
            'schedule_time' => HorizonteFortnightlyFeedScheduleCadence::time(),
            'schedule_day' => HorizonteFortnightlyFeedScheduleCadence::day(),
            'schedule_months' => HorizonteFortnightlyFeedScheduleCadence::months(),
            'schedule_summary' => HorizonteFortnightlyFeedScheduleCadence::summary(),
            'schedule_cron' => HorizonteFortnightlyFeedScheduleCadence::cronExpression(),
            'reference_year' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
                'coverage' => [
                'fundeb_municipios' => count($fundebIbges),
                'censo_municipios' => count($censoIbges),
                'saeb_municipios' => count($saebIbges),
                'cadunico_municipios' => $cadunicoCount,
                'demography_municipios' => $demographyCount,
                'educacenso_years_indexed' => $educacensoYearsIndexed,
                'educacenso_years_total' => count($educacensoWindow),
                'educacenso_window' => $educacensoWindow,
                'educacenso_steps_done' => HorizonteEducacensoImportProgress::doneStepCount(),
                'educacenso_steps_total' => HorizonteEducacensoImportProgress::totalSteps($educacensoWindow),
                'universe_municipios' => count($universe),
                'with_full_triad' => $withFullTriad,
                'ibge_ufs_warmed' => $ibgeUfsWarmed,
                'ibge_ufs_total' => $ibgeUfsTotal,
                'municipal_geo_ufs_done' => $ibgeGeoUfsDone,
                'municipal_geo_ufs_total' => $ibgeGeoUfsTotal,
                'municipal_area_municipios' => $municipalAreaCount,
                'microdados_ok' => $microdadosPath !== null && is_readable($microdadosPath),
                'fundeb_latest' => FundebMunicipioReference::query()->max('imported_at'),
                'censo_latest' => InepCensoMunicipioMatricula::query()->max('imported_at'),
                'saeb_latest' => SaebIndicatorPoint::query()->max('updated_at'),
            ],
            'phases' => $this->feedPhases(
                $fundebSet,
                $censoSet,
                $saebSet,
                $microdadosPath,
                $ibgeUfsWarmed,
                $ibgeUfsTotal,
                $cadunicoCount,
                $demographyCount,
                $educacensoWindow,
                $educacensoYearsIndexed,
                $refYear,
                $siconfiPeriod,
                $siconfiMunicipios,
                $transparencyMunicipios,
                $transparencyApiKey,
                $alertsMeta,
            ),
            'last_feed' => HorizonteFortnightlyFeedCache::get(),
            'pipeline' => HorizonteFortnightlyFeedPipeline::get(),
            'feed_staged' => filter_var(config('horizonte.fortnightly_feed.staged', true), FILTER_VALIDATE_BOOLEAN),
            'feed_step_interval' => max(5, (int) config('horizonte.fortnightly_feed.schedule.step_interval_minutes', 20)),
            'ibge_ufs_per_step' => max(1, (int) config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1)),
            'saeb_years_per_step' => max(1, (int) config('horizonte.fortnightly_feed.saeb_years_per_step', 1)),
            'educacenso_years_per_step' => max(1, (int) config('horizonte.fortnightly_feed.educacenso_years_per_step', 1)),
            'educacenso_steps_per_step' => max(1, (int) config('horizonte.fortnightly_feed.educacenso_steps_per_step', 1)),
            'ibge_warm_done' => HorizonteIbgeWarmProgress::doneUfs(),
            'sidra_import_done' => HorizonteSidraImportProgress::doneUfs(),
            'saeb_import_done' => HorizonteSaebImportProgress::doneYears(),
            'educacenso_import_done' => HorizonteEducacensoImportProgress::doneYears($educacensoWindow),
            'educacenso_steps_done' => HorizonteEducacensoImportProgress::doneStepCount(),
            'educacenso_steps_total' => HorizonteEducacensoImportProgress::totalSteps($educacensoWindow),
            'educacenso_recent_steps' => HorizonteEducacensoImportProgress::recentDoneSteps(15),
            'municipal_geo_ufs_done' => $ibgeGeoUfsDone,
            'municipal_geo_ufs_total' => $ibgeGeoUfsTotal,
            'municipal_geo_recent_steps' => HorizonteIbgeMunicipalGeoImportProgress::recentSteps(15),
            'municipal_area_municipios' => $municipalAreaCount,
            'bundle' => $this->bundleStatus(),
            'map_url' => route('dashboard.horizonte'),
            'doc_url' => route('admin.documentation.show', ['doc' => 'docs/HORIZONTE.md']),
        ];
    }

    /**
     * @param  array<string, int>  $fundebSet
     * @param  array<string, int>  $censoSet
     * @param  array<string, int>  $saebSet
     * @return list<array<string, mixed>>
     */
    private function feedPhases(
        array $fundebSet,
        array $censoSet,
        array $saebSet,
        ?string $microdadosPath,
        int $ibgeUfsWarmed,
        int $ibgeUfsTotal,
        int $cadunicoCount,
        int $demographyCount,
        array $educacensoWindow,
        int $educacensoYearsIndexed,
        int $refYear,
        int $siconfiPeriod,
        int $siconfiMunicipios,
        int $transparencyMunicipios,
        string $transparencyApiKey,
        ?array $alertsMeta,
    ): array {
        $phases = [
            [
                'key' => 'fundeb_receita',
                'label' => __('FUNDEB — receita nacional (CSV FNDE)'),
                'description' => __('Complementação e receita por ente — todos os IBGE com linha na tabela fundeb_municipio_references.'),
                'source_id' => 'fundeb_fnde',
                'hub_anchor' => '#source-fundeb_fnde',
                'admin_url' => route('admin.ieducar-compatibility.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --staged --reset --skip-censo --skip-educacenso --skip-saeb --skip-ibge --skip-sge --skip-verify',
                'ok' => count($fundebSet) >= 100,
                'metric' => count($fundebSet),
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'censo_matriculas',
                'label' => __('Censo INEP — matrículas municipais'),
                'description' => __('Indexa o microdados Educacenso mais recente (escala do mapa e segmentos em inep_censo_municipio_matriculas). Para a série histórica do gráfico, use a fase Educacenso.'),
                'source_id' => 'censo_inep_matriculas',
                'hub_anchor' => '#source-censo_inep_matriculas',
                'admin_url' => route('admin.geo-sync.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=censo_matriculas',
                'ok' => count($censoSet) >= 100,
                'metric' => count($censoSet),
                'metric_label' => __('municípios'),
                'needs_microdados' => true,
                'blocked' => ($microdadosPath === null || ! is_readable((string) $microdadosPath))
                    ? __('CSV Educacenso ausente em storage/app/public/inep/')
                    : null,
            ],
            [
                'key' => 'educacenso',
                'label' => __('Educacenso — série matrículas (gráfico Horizonte)'),
                'description' => __('Importa microdados INEP **ano × UF** (:steps passo(s)/execução) — segmentos, etapas e filtro por dependência. Comando dedicado: horizonte:sync-educacenso.', [
                    'steps' => (string) max(1, (int) config('horizonte.fortnightly_feed.educacenso_steps_per_step', 1)),
                ]),
                'source_id' => 'censo_inep_matriculas',
                'hub_anchor' => '#horizonte-educacenso-sync',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-educacenso-sync',
                'cli' => 'php artisan horizonte:sync-educacenso',
                'cli_reset' => 'php artisan horizonte:sync-educacenso --reset --all',
                'cli_verify' => 'php artisan horizonte:verify-educacenso-coverage --sample=50',
                'ok' => HorizonteEducacensoImportProgress::isComplete($educacensoWindow),
                'metric' => HorizonteEducacensoImportProgress::doneStepCount(),
                'metric_total' => HorizonteEducacensoImportProgress::totalSteps($educacensoWindow),
                'metric_label' => __('passos ano×UF'),
                'needs_microdados' => true,
            ],
            [
                'key' => 'cadunico_sync',
                'label' => __('CadÚnico — agregados municipais'),
                'description' => __('Sincronização Misocial/CECAD — faixas etárias e demanda social no score.'),
                'source_id' => 'cadunico_cecad',
                'hub_anchor' => '#source-cadunico_cecad',
                'admin_url' => route('admin.cadunico-sync.index'),
                'cli' => 'php artisan cadunico:auto-sync',
                'ok' => $cadunicoCount >= 100,
                'metric' => $cadunicoCount,
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'sidra_demography',
                'label' => __('IBGE SIDRA — população 4–17'),
                'description' => __('Denominador demográfico independente do Censo escolar (Censo 2022).'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-hub',
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=sidra_demography',
                'ok' => $demographyCount >= 100,
                'metric' => $demographyCount,
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'repasses_tesouro',
                'label' => __('Repasses Tesouro — FUNDEB CKAN'),
                'description' => __('Dependência de transferências federais — referência Horizonte + ano vigente (YTD).'),
                'source_id' => 'repasses_tesouro',
                'hub_anchor' => '#source-repasses_tesouro',
                'admin_url' => route('admin.public-data.index').'#source-repasses_tesouro',
                'cli' => 'php artisan horizonte:sync-repasses-tesouro --with-ref',
                'ok' => \Illuminate\Support\Facades\Schema::hasTable('municipal_transfer_snapshots')
                    && \App\Models\MunicipalTransferSnapshot::query()->distinct()->count('ibge_municipio') >= 100,
                'metric' => \Illuminate\Support\Facades\Schema::hasTable('municipal_transfer_snapshots')
                    ? \App\Models\MunicipalTransferSnapshot::query()->distinct()->count('ibge_municipio')
                    : 0,
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'siconfi_sync',
                'label' => __('SICONFI — indicadores fiscais (RREO)'),
                'description' => __('Capacidade fiscal municipal via API Tesouro — 1 UF inteira por passo no feed bimestral.'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-hub',
                'cli' => 'php artisan horizonte:sync-siconfi --reset --continue',
                'cli_reset' => 'php artisan horizonte:fortnightly-feed --phase=siconfi_sync --reset',
                'ok' => $siconfiMunicipios >= 100
                    || HorizonteSiconfiSyncProgress::isComplete($refYear, $siconfiPeriod),
                'metric' => $siconfiMunicipios,
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'transparency_sync',
                'label' => __('Portal da Transparência'),
                'description' => __('Convênios MEC/FNDE e empenhos educação/tecnologia — requer PORTAL_TRANSPARENCIA_API_KEY.'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-hub',
                'cli' => 'php artisan horizonte:sync-transparency --limit=5',
                'cli_reset' => 'php artisan horizonte:fortnightly-feed --phase=transparency_sync',
                'ok' => $transparencyApiKey !== '' && $transparencyMunicipios >= 50,
                'metric' => $transparencyMunicipios,
                'metric_label' => __('municípios'),
                'blocked' => $transparencyApiKey === ''
                    ? __('PORTAL_TRANSPARENCIA_API_KEY não configurada no .env')
                    : null,
            ],
            [
                'key' => 'saeb_planilhas',
                'label' => __('SAEB — planilhas INEP (nacional)'),
                'description' => __('Indicadores LP/MAT por município — :n ano(s) por passo (HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP). Repita o comando até concluir todos os anos.', [
                    'n' => (string) max(1, (int) config('horizonte.fortnightly_feed.saeb_years_per_step', 1)),
                ]),
                'source_id' => 'saeb_inep',
                'hub_anchor' => '#source-saeb_inep',
                'admin_url' => route('admin.pedagogical-sync.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=saeb_planilhas',
                'cli_reset' => 'php artisan horizonte:fortnightly-feed --phase=saeb_planilhas --reset',
                'ok' => count($saebSet) >= 100,
                'metric' => count($saebSet),
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'ibge_catalog',
                'label' => __('Catálogo IBGE — centroides'),
                'description' => __('Nome, UF e coordenadas — :n UF(s) por passo (HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP). Repita o comando até aquecer as 27 UFs.', [
                    'n' => (string) max(1, (int) config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1)),
                ]),
                'source_id' => 'geo_inep',
                'hub_anchor' => '#source-geo_inep',
                'admin_url' => route('admin.geo-sync.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=ibge_catalog',
                'cli_reset' => 'php artisan horizonte:fortnightly-feed --phase=ibge_catalog --reset',
                'ok' => $ibgeUfsWarmed >= $ibgeUfsTotal,
                'metric' => $ibgeUfsWarmed,
                'metric_label' => __('UFs aquecidas'),
            ],
            [
                'key' => 'ibge_municipal_geo',
                'label' => __('Malha municipal IBGE — área km²'),
                'description' => __('Polígonos por UF (qualidade intermediária) + área territorial em municipal_area_snapshots. :n UF(s) por passo — comando dedicado com --all.', [
                    'n' => (string) max(1, (int) config('horizonte.municipal_geo.ufs_per_step', 1)),
                ]),
                'source_id' => 'geo_inep',
                'hub_anchor' => '#horizonte-municipal-geo-sync',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-municipal-geo-sync',
                'cli' => 'php artisan horizonte:import-municipal-geo --all',
                'cli_reset' => 'php artisan horizonte:import-municipal-geo --reset --all',
                'ok' => HorizonteIbgeMunicipalGeoImportProgress::isComplete(),
                'metric' => HorizonteIbgeMunicipalGeoImportProgress::doneCount(),
                'metric_total' => HorizonteIbgeMunicipalGeoImportProgress::totalUfs(),
                'metric_label' => __('UFs com malha'),
            ],
            [
                'key' => 'sge_registry',
                'label' => __('SGE — sistemas de gestão educacional'),
                'description' => __('Registo opcional IBGE→SGE + catálogo i-Educar ServLITCYS (não bloqueia o mapa).'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-hub',
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=sge_registry --skip-fundeb --skip-censo --skip-saeb --skip-ibge --skip-verify',
                'ok' => true,
                'metric' => null,
                'metric_label' => null,
            ],
            [
                'key' => 'municipal_alerts',
                'label' => __('Alertas MEC/FNDE (VAAT)'),
                'description' => __('Lista oficial FNDE de municípios inabilitados + registo JSON manual — chip no modal Horizonte.'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-hub',
                'cli' => 'php artisan horizonte:sync-municipal-alerts',
                'cli_reset' => 'php artisan horizonte:sync-municipal-alerts --reset',
                'ok' => is_array($alertsMeta) && filled($alertsMeta['synced_at'] ?? null),
                'metric' => is_array($alertsMeta) ? count(HorizonteMunicipalAlertsCache::getIndex()) : 0,
                'metric_label' => __('IBGE com alerta'),
            ],
            [
                'key' => 'official_check',
                'label' => __('Verificação de fontes oficiais'),
                'description' => __('public-data:check-official --no-notify (cache no hub).'),
                'source_id' => null,
                'hub_anchor' => '#verificacao-oficial',
                'admin_url' => route('admin.public-data.index').'#verificacao-oficial',
                'cli' => 'php artisan public-data:check-official --no-notify',
                'ok' => PublicDataOfficialCheckCache::get() !== null,
                'metric' => null,
                'metric_label' => null,
            ],
            [
                'key' => 'data_bundle',
                'label' => __('Pacote offline — local → produção'),
                'description' => __('Exporta/importa FUNDEB, Censo, SAEB, cache IBGE e SGE via ZIP (sem git).'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-offline-bundle',
                'admin_url' => route('admin.horizonte-import.index').'#horizonte-offline-bundle',
                'cli' => 'php artisan horizonte:export-data-bundle',
                'ok' => Storage::disk('local')->exists('horizonte/bundles/latest.zip'),
                'metric' => null,
                'metric_label' => null,
            ],
        ];

        foreach ($phases as &$phase) {
            if (($phase['needs_microdados'] ?? false) && ($microdadosPath === null || ! is_readable($microdadosPath))) {
                $phase['ok'] = false;
                $phase['blocked'] = __('Microdados INEP em falta — ver Geo / pipeline passo 3.');
            }
            if ($phase['source_id'] !== null) {
                $hint = PublicDataImportCatalog::routineHint((string) $phase['source_id']);
                $phase['routine_label'] = $hint['label'];
            }
        }
        unset($phase);

        return $phases;
    }

    private function countIbgeUfsWarmed(): int
    {
        $count = 0;
        foreach (self::BRAZIL_UFS as $uf) {
            $cached = AdminHomeMapCache::get('ibge_municipality_catalog_uf:'.$uf);
            if (is_array($cached) && $cached !== []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{latest_exists: bool, latest_path: string, latest_updated_at: ?int, latest_size: ?int}
     */
    private function bundleStatus(): array
    {
        $rel = 'horizonte/bundles/latest.zip';
        $exists = Storage::disk('local')->exists($rel);

        return [
            'latest_exists' => $exists,
            'latest_path' => storage_path('app/'.$rel),
            'latest_updated_at' => $exists ? Storage::disk('local')->lastModified($rel) : null,
            'latest_size' => $exists ? Storage::disk('local')->size($rel) : null,
        ];
    }
}
