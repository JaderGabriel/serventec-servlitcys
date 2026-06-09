<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\OverviewRepository;
use App\Services\CityDataConnection;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsLoadProfiler;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bateria de testes para apurar causas de erro 500/timeout no painel analítico.
 */
final class AnalyticsDiagnosticsRunner
{
    /** @var list<array<string, mixed>> */
    private array $steps = [];

    private float $startedAt;

    public function __construct(
        private CityDataConnection $cityData,
        private FilterOptionsService $filterOptions,
        private OverviewRepository $overviewRepository,
    ) {
        $this->startedAt = microtime(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Request $request): array
    {
        $this->steps = [];
        $maxStep = max(30, (int) config('analytics.diagnostics_max_step_seconds', 120));
        $previousLimit = ini_get('max_execution_time');
        @set_time_limit(min(900, $maxStep * 8));

        $cityId = $request->integer('city_id');
        $anoLetivo = $request->input('ano_letivo', '2024');
        $skipSlow = $request->boolean('skip_slow');

        $this->step('environment', fn () => $this->collectEnvironment());
        $this->step('analytics_config', fn () => $this->collectAnalyticsConfig());

        $city = null;
        $this->step('resolve_city', function () use ($cityId, &$city) {
            if ($cityId <= 0) {
                return [
                    'ok' => false,
                    'hint' => __('Passe ?city_id=ID na URL (cidade ativa com BD i-Educar).'),
                ];
            }

            $city = City::query()->whereKey($cityId)->first();
            if ($city === null) {
                return ['ok' => false, 'message' => __('Cidade :id não encontrada.', ['id' => $cityId])];
            }

            return [
                'ok' => true,
                'city_id' => $city->id,
                'name' => $city->name,
                'uf' => $city->uf,
                'driver' => $city->effectiveIeducarDriver(),
                'host' => $city->db_host,
                'port' => $city->db_port,
                'database' => $city->db_database,
                'schema' => $city->ieducar_schema,
                'is_active' => $city->is_active,
                'has_data_setup' => $city->hasDataSetup(),
            ];
        });

        if ($city instanceof City) {
            $this->step('city_connection_probe', fn () => array_merge(
                ['label' => __('Teste PDO (probe)')],
                $this->cityData->probe($city),
            ));

            $this->step('city_connection_status', fn () => array_merge(
                ['label' => __('Indicador rápido (listagem Cidades)')],
                $this->cityData->connectionStatus($city),
            ));

            $filters = IeducarFilterState::fromRequest(new Request([
                'ano_letivo' => $anoLetivo,
                'city_id' => $city->id,
            ]));

            $this->step('filter_years_light', function () use ($city, $filters) {
                $profiler = new AnalyticsLoadProfiler();
                $result = $profiler->measure('filter_years', fn () => $this->filterOptions->loadForAnalyticsIndex(
                    $city,
                    $filters,
                    light: true,
                    profiler: $profiler,
                ));

                return [
                    'year_count' => count($result['years'] ?? []),
                    'errors' => $result['errors'] ?? [],
                    'profiler_steps' => $profiler->steps(),
                ];
            });

            if (! $skipSlow) {
                $this->step('filter_bootstrap', function () use ($city, $filters) {
                    $profiler = new AnalyticsLoadProfiler();
                    $result = $profiler->measure('bootstrap', fn () => $this->filterOptions->loadBootstrap(
                        $city,
                        $filters,
                        $profiler,
                    ));

                    return [
                        'escolas' => count($result['escolas'] ?? []),
                        'cursos' => count($result['cursos'] ?? []),
                        'turnos' => count($result['turnos'] ?? []),
                        'errors' => $result['errors'] ?? [],
                        'profiler_steps' => $profiler->steps(),
                    ];
                }, $maxStep);
            }

            if (! $skipSlow) {
                $this->step('overview_repository', function () use ($city, $filters) {
                    $profiler = new AnalyticsLoadProfiler();
                    $data = $profiler->measure('overview', fn () => $this->overviewRepository->summary($city, $filters));
                    $charts = is_array($data['charts'] ?? null) ? count($data['charts']) : 0;
                    $hasError = ! empty($data['error']);

                    return [
                        'has_error' => $hasError,
                        'error' => $data['error'] ?? null,
                        'charts_count' => $charts,
                        'has_kpis' => $data['kpis'] !== null,
                        'filter_note' => $data['filter_note'] ?? null,
                        'profiler_steps' => $profiler->steps(),
                    ];
                }, $maxStep);
            }

            $this->step('simulate_index_payload', function () use ($city, $filters, $skipSlow) {
                return $this->simulateIndexPath($city, $filters, $skipSlow);
            }, $maxStep);

            $this->step('render_analytics_view', function () use ($city, $filters) {
                return $this->renderAnalyticsView($city, $filters);
            }, $maxStep);

            if (! $skipSlow) {
                $this->step('render_overview_partial', function () use ($city, $filters) {
                    return $this->renderOverviewPartial($city, $filters);
                }, $maxStep);
            }
        }

        $this->step('recent_analytics_logs', fn () => $this->tailAnalyticsLogs());

        if ($previousLimit !== false && $previousLimit !== '') {
            @set_time_limit((int) $previousLimit);
        }

        return $this->buildReport($cityId, $anoLetivo, $skipSlow);
    }

    /**
     * @param  callable(): array<string, mixed>  $fn
     */
    private function step(string $id, callable $fn, ?int $softMaxSeconds = null): void
    {
        $t0 = microtime(true);
        $entry = [
            'id' => $id,
            'ok' => true,
            'ms' => 0.0,
            'data' => [],
            'error' => null,
            'exception' => null,
            'trace' => null,
            'timed_out_hint' => false,
        ];

        try {
            $data = $fn();
            $entry['data'] = is_array($data) ? $data : ['result' => $data];
            if (array_key_exists('ok', $entry['data']) && $entry['data']['ok'] === false) {
                $entry['ok'] = false;
                $entry['error'] = $entry['data']['message'] ?? $entry['data']['hint'] ?? __('Passo falhou.');
            }
        } catch (Throwable $e) {
            $entry['ok'] = false;
            $entry['error'] = $e->getMessage();
            $entry['exception'] = $e::class;
            if (config('app.debug')) {
                $entry['trace'] = $e->getTraceAsString();
            }
            Log::warning('analytics.diagnostics.step_failed', [
                'step' => $id,
                'message' => $e->getMessage(),
            ]);
        }

        $entry['ms'] = round((microtime(true) - $t0) * 1000, 1);
        if ($softMaxSeconds !== null && $entry['ms'] > $softMaxSeconds * 1000) {
            $entry['timed_out_hint'] = true;
            $entry['ok'] = false;
            $entry['error'] = $entry['error'] ?? __('Passo excedeu :s s (possível timeout PHP/nginx).', ['s' => $softMaxSeconds]);
        }

        $this->steps[] = $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectEnvironment(): array
    {
        return [
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'sapi' => PHP_SAPI,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectAnalyticsConfig(): array
    {
        return [
            'lazy_tab_loading' => config('analytics.lazy_tab_loading'),
            'index_light_filters' => config('analytics.index_light_filters'),
            'index_load_overview' => config('analytics.index_load_overview'),
            'index_funding_context' => config('analytics.index_funding_context'),
            'debug_log' => config('analytics.debug_log'),
            'diagnostics_route_enabled' => config('analytics.diagnostics_route_enabled'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateIndexPath(City $city, IeducarFilterState $filters, bool $skipSlow): array
    {
        $profiler = new AnalyticsLoadProfiler();
        $warnings = [];
        $loadOverview = ! config('analytics.lazy_tab_loading', true)
            || (bool) config('analytics.index_load_overview', false);

        $ieducarOptions = $profiler->measure('filter_options', fn () => $this->filterOptions->loadForAnalyticsIndex(
            $city,
            $filters,
            (bool) config('analytics.index_light_filters', true),
            $profiler,
        ));

        $overviewData = ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        if ($loadOverview && ! $skipSlow) {
            try {
                $overviewData = $profiler->measure('overview', fn () => $this->overviewRepository->summary($city, $filters));
            } catch (Throwable $e) {
                $warnings[] = $e->getMessage();
                $overviewData['error'] = $e->getMessage();
            }
        }

        return [
            'load_overview_on_index' => $loadOverview,
            'defer_overview' => ! $loadOverview,
            'warnings' => $warnings,
            'filter_errors' => $ieducarOptions['errors'] ?? [],
            'overview_error' => $overviewData['error'] ?? null,
            'profiler_steps' => $profiler->steps(),
            'recommendation' => $this->recommendationFor500($loadOverview, $overviewData, $ieducarOptions),
        ];
    }

    /**
     * @param  array<string, mixed>  $overviewData
     * @param  array<string, mixed>  $ieducarOptions
     */
    private function recommendationFor500(bool $loadOverview, array $overviewData, array $ieducarOptions): string
    {
        if (! empty($ieducarOptions['errors'])) {
            return __('Falha ao carregar filtros i-Educar — verifique conexão à BD da cidade e config/ieducar.php.');
        }

        if ($loadOverview && ! empty($overviewData['error'])) {
            return __('Visão geral no index com erro — use ANALYTICS_INDEX_LOAD_OVERVIEW=false e lazy tabs.');
        }

        if ($loadOverview) {
            return __('Index ainda carrega overview na BD remota; confirme ANALYTICS_INDEX_LOAD_OVERVIEW=false em produção.');
        }

        return __('Configuração lazy parece correcta; se 500 persistir, verifique timeout nginx/php-fpm ou erro na renderização da view.');
    }

    /**
     * @return array<string, mixed>
     */
    private function renderAnalyticsView(City $city, IeducarFilterState $filters): array
    {
        $ieducarOptions = ['years' => [], 'escolas' => [], 'cursos' => [], 'turnos' => [], 'errors' => []];
        $overviewData = ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];

        $html = view('dashboard.analytics', [
            'cities' => collect([$city]),
            'selectedCity' => $city,
            'filters' => $filters,
            'yearOptions' => ['2024' => '2024'],
            'yearFilterReady' => $filters->hasYearSelected(),
            'ieducarOptions' => $ieducarOptions,
            'overviewData' => $overviewData,
            'schoolUnitsData' => AnalyticsEmptyPayloads::schoolUnits(),
            'enrollmentData' => AnalyticsEmptyPayloads::enrollment(),
            'performanceData' => AnalyticsEmptyPayloads::performance(),
            'attendanceData' => AnalyticsEmptyPayloads::attendance(),
            'inclusionData' => AnalyticsEmptyPayloads::inclusion(),
            'networkData' => AnalyticsEmptyPayloads::network(),
            'fundebData' => AnalyticsEmptyPayloads::fundeb(),
            'otherFundingData' => AnalyticsEmptyPayloads::otherFunding(),
            'workDoneData' => AnalyticsEmptyPayloads::workDone(),
            'discrepanciesData' => AnalyticsEmptyPayloads::discrepancies(),
            'municipalityHealthData' => AnalyticsEmptyPayloads::municipalityHealth(),
            'fundingLossModalData' => DiscrepanciesCheckCatalog::modalPayload($city, $filters),
            'chartExportContext' => ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions),
            'tabs' => AnalyticsTabCatalog::tabsOrdered(),
            'tabGroups' => AnalyticsTabCatalog::groups(),
            'analyticsInitialTab' => 'overview',
            'lazyTabLoading' => (bool) config('analytics.lazy_tab_loading', true),
            'pdfExportsRecent' => [],
            'municipalityContext' => null,
            'analyticsLoadWarnings' => [],
            'deferSecondaryFilters' => true,
            'deferOverviewOnIndex' => true,
            'analyticsDebugEnabled' => true,
            'analyticsDebugSteps' => [],
            'analyticsDebugTotalMs' => 0,
            'indexFatalMessage' => null,
        ])->render();

        return [
            'html_bytes' => strlen($html),
            'html_kb' => round(strlen($html) / 1024, 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function renderOverviewPartial(City $city, IeducarFilterState $filters): array
    {
        $overviewData = $this->overviewRepository->summary($city, $filters);
        $html = view('dashboard.analytics.partials.overview', [
            'overviewData' => $overviewData,
            'schoolUnits' => null,
            'yearFilterReady' => true,
            'chartExportContext' => ChartExportMeta::forAnalytics($city, $filters, []),
            'municipalityContext' => null,
        ])->render();

        return [
            'html_bytes' => strlen($html),
            'overview_error' => $overviewData['error'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tailAnalyticsLogs(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! File::isFile($path)) {
            return ['found' => false, 'lines' => []];
        }

        $content = File::get($path);
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $matched = [];
        foreach ($lines as $line) {
            if (stripos($line, 'analytics.') !== false
                || stripos($line, 'Analytics') !== false
                || stripos($line, 'ieducar.') !== false) {
                $matched[] = $line;
            }
        }

        return [
            'found' => true,
            'path' => $path,
            'lines' => array_slice($matched, -40),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(int $cityId, mixed $anoLetivo, bool $skipSlow): array
    {
        $failed = array_values(array_filter(
            $this->steps,
            static fn (array $s): bool => $s['ok'] === false,
        ));

        $firstFailure = $failed[0] ?? null;
        $summaryOk = $failed === [];

        return [
            'generated_at' => now()->toIso8601String(),
            'total_ms' => round((microtime(true) - $this->startedAt) * 1000, 1),
            'params' => [
                'city_id' => $cityId,
                'ano_letivo' => $anoLetivo,
                'skip_slow' => $skipSlow,
            ],
            'summary' => [
                'ok' => $summaryOk,
                'steps_total' => count($this->steps),
                'steps_failed' => count($failed),
                'first_failure' => $firstFailure !== null ? $firstFailure['id'] : null,
                'headline' => $summaryOk
                    ? __('Nenhuma falha detectada nesta bateria.')
                    : __('Falha em :step — :msg', [
                        'step' => $firstFailure['id'] ?? '?',
                        'msg' => Str::limit((string) ($firstFailure['error'] ?? ''), 200),
                    ]),
            ],
            'steps' => $this->steps,
        ];
    }
}
