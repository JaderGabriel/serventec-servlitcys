<?php

namespace App\Services\Admin;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalDemographySnapshot;
use App\Models\SaebIndicatorPoint;
use App\Support\Admin\PublicDataImportCatalog;
use App\Services\Admin\PublicDataOfficialCheckCache;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use App\Support\Horizonte\HorizonteFortnightlyFeedCache;
use App\Support\Horizonte\HorizonteFortnightlyFeedScheduleCadence;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use App\Support\Horizonte\HorizonteIbgeWarmProgress;
use App\Support\Horizonte\HorizonteSidraImportProgress;
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
                'universe_municipios' => count($universe),
                'with_full_triad' => $withFullTriad,
                'ibge_ufs_warmed' => $ibgeUfsWarmed,
                'ibge_ufs_total' => $ibgeUfsTotal,
                'microdados_ok' => $microdadosPath !== null && is_readable($microdadosPath),
                'fundeb_latest' => FundebMunicipioReference::query()->max('imported_at'),
                'censo_latest' => InepCensoMunicipioMatricula::query()->max('imported_at'),
                'saeb_latest' => SaebIndicatorPoint::query()->max('updated_at'),
            ],
            'phases' => $this->feedPhases($fundebSet, $censoSet, $saebSet, $microdadosPath, $ibgeUfsWarmed, $ibgeUfsTotal, $cadunicoCount, $demographyCount, $educacensoWindow, $educacensoYearsIndexed),
            'last_feed' => HorizonteFortnightlyFeedCache::get(),
            'pipeline' => HorizonteFortnightlyFeedPipeline::get(),
            'feed_staged' => filter_var(config('horizonte.fortnightly_feed.staged', true), FILTER_VALIDATE_BOOLEAN),
            'feed_step_interval' => max(5, (int) config('horizonte.fortnightly_feed.schedule.step_interval_minutes', 20)),
            'ibge_ufs_per_step' => max(1, (int) config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1)),
            'saeb_years_per_step' => max(1, (int) config('horizonte.fortnightly_feed.saeb_years_per_step', 1)),
            'ibge_warm_done' => HorizonteIbgeWarmProgress::doneUfs(),
            'sidra_import_done' => HorizonteSidraImportProgress::doneUfs(),
            'saeb_import_done' => HorizonteSaebImportProgress::doneYears(),
            'educacenso_import_done' => HorizonteEducacensoImportProgress::doneYears(),
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
    private function feedPhases(array $fundebSet, array $censoSet, array $saebSet, ?string $microdadosPath, int $ibgeUfsWarmed, int $ibgeUfsTotal, int $cadunicoCount, int $demographyCount, array $educacensoWindow, int $educacensoYearsIndexed): array
    {
        $phases = [
            [
                'key' => 'fundeb_receita',
                'label' => __('FUNDEB — receita nacional (CSV FNDE)'),
                'description' => __('Complementação e receita por ente — todos os IBGE com linha na tabela fundeb_municipio_references.'),
                'source_id' => 'fundeb_fnde',
                'hub_anchor' => '#source-fundeb_fnde',
                'admin_url' => route('admin.ieducar-compatibility.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --staged --reset --skip-censo --skip-saeb --skip-ibge --skip-sge --skip-verify',
                'ok' => count($fundebSet) >= 100,
                'metric' => count($fundebSet),
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'censo_matriculas',
                'label' => __('Censo INEP — matrículas municipais'),
                'description' => __('Indexação a partir de microdados Educacenso (inep_censo_municipio_matriculas).'),
                'source_id' => 'censo_inep_matriculas',
                'hub_anchor' => '#source-censo_inep_matriculas',
                'admin_url' => route('admin.geo-sync.index'),
                'cli' => 'php artisan inep:index-censo-geo-agg',
                'ok' => count($censoSet) >= 100,
                'metric' => count($censoSet),
                'metric_label' => __('municípios'),
                'needs_microdados' => true,
            ],
            [
                'key' => 'educacenso',
                'label' => __('Educacenso — série matrículas (gráfico Horizonte)'),
                'description' => __('Importa microdados INEP ano a ano para a janela do gráfico de matrículas (§6.9).'),
                'source_id' => 'censo_inep_matriculas',
                'hub_anchor' => '#source-censo_inep_matriculas',
                'admin_url' => route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub',
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=educacenso',
                'ok' => $educacensoYearsIndexed >= count($educacensoWindow),
                'metric' => $educacensoYearsIndexed,
                'metric_label' => __('anos indexados'),
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
                'admin_url' => route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub',
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
                'key' => 'sge_registry',
                'label' => __('SGE — sistemas de gestão educacional'),
                'description' => __('Registo opcional IBGE→SGE + catálogo i-Educar ServLITCYS (não bloqueia o mapa).'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub',
                'cli' => 'php artisan horizonte:fortnightly-feed --phase=sge_registry --skip-fundeb --skip-censo --skip-saeb --skip-ibge --skip-verify',
                'ok' => true,
                'metric' => null,
                'metric_label' => null,
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
                'admin_url' => route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-offline-bundle',
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
