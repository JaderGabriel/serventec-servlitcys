<?php

namespace App\Services\Admin;

use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\SaebIndicatorPoint;
use App\Support\Admin\PublicDataImportCatalog;
use App\Services\Admin\PublicDataOfficialCheckCache;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteFortnightlyFeedCache;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use App\Support\InepMicrodadosCadastroEscolasPath;

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

        $scheduleDays = config('horizonte.fortnightly_feed.schedule.days', [1, 15]);
        $day1 = (int) ($scheduleDays[0] ?? 1);
        $day2 = (int) ($scheduleDays[1] ?? 15);

        return [
            'enabled' => (bool) config('horizonte.enabled', true),
            'feed_enabled' => (bool) config('horizonte.fortnightly_feed.enabled', true),
            'schedule_enabled' => (bool) config('horizonte.fortnightly_feed.schedule.enabled', true),
            'schedule_time' => trim((string) config('horizonte.fortnightly_feed.schedule.time', '03:00')) ?: '03:00',
            'schedule_days' => [$day1, $day2],
            'reference_year' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'coverage' => [
                'fundeb_municipios' => count($fundebIbges),
                'censo_municipios' => count($censoIbges),
                'saeb_municipios' => count($saebIbges),
                'universe_municipios' => count($universe),
                'with_full_triad' => $withFullTriad,
                'ibge_ufs_warmed' => $this->countIbgeUfsWarmed(),
                'microdados_ok' => $microdadosPath !== null && is_readable($microdadosPath),
                'fundeb_latest' => FundebMunicipioReference::query()->max('imported_at'),
                'censo_latest' => InepCensoMunicipioMatricula::query()->max('imported_at'),
                'saeb_latest' => SaebIndicatorPoint::query()->max('updated_at'),
            ],
            'phases' => $this->feedPhases($fundebSet, $censoSet, $saebSet, $microdadosPath),
            'last_feed' => HorizonteFortnightlyFeedCache::get(),
            'pipeline' => HorizonteFortnightlyFeedPipeline::get(),
            'feed_staged' => filter_var(config('horizonte.fortnightly_feed.staged', true), FILTER_VALIDATE_BOOLEAN),
            'feed_step_interval' => max(5, (int) config('horizonte.fortnightly_feed.schedule.step_interval_minutes', 20)),
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
    private function feedPhases(array $fundebSet, array $censoSet, array $saebSet, ?string $microdadosPath): array
    {
        $phases = [
            [
                'key' => 'fundeb_receita',
                'label' => __('FUNDEB — receita nacional (CSV FNDE)'),
                'description' => __('Complementação e receita por ente — todos os IBGE com linha na tabela fundeb_municipio_references.'),
                'source_id' => 'fundeb_fnde',
                'hub_anchor' => '#source-fundeb_fnde',
                'admin_url' => route('admin.ieducar-compatibility.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --skip-censo --skip-saeb --skip-ibge --skip-verify',
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
                'key' => 'saeb_planilhas',
                'label' => __('SAEB — planilhas INEP (nacional)'),
                'description' => __('Indicadores LP/MAT por município (saeb_indicator_points) — cobertura nacional para prospectos.'),
                'source_id' => 'saeb_inep',
                'hub_anchor' => '#source-saeb_inep',
                'admin_url' => route('admin.pedagogical-sync.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --skip-fundeb --skip-censo --skip-ibge --skip-verify',
                'ok' => count($saebSet) >= 100,
                'metric' => count($saebSet),
                'metric_label' => __('municípios'),
            ],
            [
                'key' => 'ibge_catalog',
                'label' => __('Catálogo IBGE — centroides'),
                'description' => __('Nome, UF e coordenadas para municípios só com dados públicos (27 UFs).'),
                'source_id' => 'geo_inep',
                'hub_anchor' => '#source-geo_inep',
                'admin_url' => route('admin.geo-sync.index'),
                'cli' => 'php artisan horizonte:fortnightly-feed --skip-fundeb --skip-censo --skip-saeb --skip-verify',
                'ok' => $this->countIbgeUfsWarmed() >= 10,
                'metric' => $this->countIbgeUfsWarmed(),
                'metric_label' => __('UFs aquecidas'),
            ],
            [
                'key' => 'sge_registry',
                'label' => __('SGE — sistemas de gestão educacional'),
                'description' => __('Registo opcional IBGE→SGE + catálogo i-Educar ServLITCYS (não bloqueia o mapa).'),
                'source_id' => null,
                'hub_anchor' => '#horizonte-hub',
                'admin_url' => route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub',
                'cli' => 'php artisan horizonte:fortnightly-feed --skip-fundeb --skip-censo --skip-saeb --skip-ibge --skip-verify',
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
}
