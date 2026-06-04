<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Admin\PublicDataImportStatusService;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Fundeb\FundebImportMode;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Admin\ImportHubThemeCatalog;
use App\Support\Admin\PublicDataImportCatalog;
use App\Support\SyncQueue\SyncQueueUserScope;
use App\Support\AdminSync\WeeklyMassSyncCheckpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDataImportController extends Controller
{
    public function __construct(
        private PublicDataImportStatusService $status,
        private AdminSyncQueueService $syncQueue,
    ) {}

    public function index(): View
    {
        $cities = City::query()->forAnalytics()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio']);
        $snapshot = $this->status->build();
        $refYear = (int) $snapshot['reference_year'];
        $maxYear = (int) date('Y');

        $sources = $snapshot['sources'];
        $themeSections = ImportHubThemeCatalog::sectionsForSources($sources);

        return view('admin.public-data.index', [
            'cities' => $cities,
            'snapshot' => $snapshot,
            'sources' => $sources,
            'themeSections' => $themeSections,
            'themeOverviewCards' => ImportHubThemeCatalog::overviewCardsForSections($themeSections),
            'gapIndex' => PublicDataImportCatalog::gapIndex(),
            'defaultYear' => $refYear,
            'yearOptions' => range($maxYear, max(2000, $maxYear - 8)),
            'importModes' => [FundebImportMode::UPDATE, FundebImportMode::REPLACE],
            'syncQueueRoutePrefix' => SyncQueueUserScope::routePrefix(request()->user()),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|string|max:64',
            'action_key' => 'required|string|max:64',
            'city_id' => 'nullable|integer|exists:cities,id',
            'ano' => 'nullable|integer|min:2000|max:'.((int) date('Y') + 1),
            'ano_from' => 'nullable|integer|min:2000|max:'.((int) date('Y') + 1),
            'ano_to' => 'nullable|integer|min:2000|max:'.((int) date('Y') + 1),
            'use_nearest_year' => 'sometimes|boolean',
            'import_mode' => 'sometimes|string|in:'.FundebImportMode::REPLACE.','.FundebImportMode::UPDATE,
            'include_cached_years' => 'sometimes|boolean',
        ]);

        $source = PublicDataImportCatalog::findSource($validated['source_id']);
        if ($source === null) {
            return redirect()
                ->route('admin.public-data.index')
                ->with('public_data_error', __('Fonte de dados desconhecida.'));
        }

        $action = $this->findAction($source, $validated['action_key']);
        if ($action === null) {
            return redirect()
                ->route('admin.public-data.index')
                ->with('public_data_error', __('Ação de importação inválida para esta fonte.'));
        }

        if (($action['needs_city'] ?? false) && empty($validated['city_id'])) {
            return redirect()
                ->route('admin.public-data.index')
                ->withInput()
                ->with('public_data_error', __('Selecione um município para esta importação.'));
        }

        if (($action['needs_year'] ?? false) && empty($validated['ano'])) {
            return redirect()
                ->route('admin.public-data.index')
                ->withInput()
                ->with('public_data_error', __('Indique o ano de referência.'));
        }

        if ($action['key'] === 'import_transfers_all_cities') {
            return $this->dispatchTransfersAllCities((int) $validated['ano']);
        }

        if ($action['key'] === 'sync_all_years') {
            return $this->dispatchFundebSyncAll($request, $validated);
        }

        if ($action['key'] === 'auto_sync' && ($source['id'] ?? '') === 'cadunico_cecad') {
            return $this->dispatchCadunicoAutoSync($validated);
        }

        if ($action['key'] === 'auto_sync_year' && ($source['id'] ?? '') === 'cadunico_cecad') {
            return $this->dispatchCadunicoAutoSync($validated, singleYear: true);
        }

        if ($action['key'] === 'weekly_mass_sync') {
            $task = $this->syncQueue->dispatch(
                AdminSyncDomain::System,
                WeeklyMassSyncCheckpoint::TASK_KEY,
                __('Sincronização massiva semanal (dados públicos)'),
                [],
                null,
            );

            return redirect()
                ->route('admin.public-data.index')
                ->with('admin_sync_queued', [
                    'task_id' => $task->id,
                    'message' => AdminSyncQueueService::flashQueuedMessage($task),
                ]);
        }

        $domain = AdminSyncDomain::tryFrom($action['task_domain']) ?? AdminSyncDomain::Fundeb;
        $payload = $this->buildPayload($action, $validated, $request);
        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;
        $city = $cityId !== null ? City::query()->find($cityId) : null;

        $label = $city !== null
            ? __(':source — :city', ['source' => $source['title'], 'city' => $city->name])
            : (string) $source['title'];

        if (isset($validated['ano'])) {
            $label .= ' ('.$validated['ano'].')';
        }

        $task = $this->syncQueue->dispatch(
            $domain,
            $action['task_key'],
            $label,
            $payload,
            $cityId,
        );

        return redirect()
            ->route('admin.public-data.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>|null
     */
    private function findAction(array $source, string $actionKey): ?array
    {
        foreach ($source['actions'] as $action) {
            if (($action['key'] ?? '') === $actionKey) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPayload(array $action, array $validated, Request $request): array
    {
        $payload = [];

        if (! empty($validated['city_id'])) {
            $payload['city_id'] = (int) $validated['city_id'];
        }
        if (! empty($validated['ano'])) {
            $payload['ano'] = (int) $validated['ano'];
        }

        $taskKey = (string) ($action['task_key'] ?? '');
        if (in_array($taskKey, ['import_city_year', 'import_bulk_year', 'sync_all_years'], true)) {
            $payload['use_nearest_year'] = $request->boolean('use_nearest_year');
            $payload['import_mode'] = FundebImportMode::normalize($validated['import_mode'] ?? null);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dispatchFundebSyncAll(Request $request, array $validated): RedirectResponse
    {
        $anoFrom = (int) ($validated['ano_from'] ?? config('ieducar.fundeb.open_data.sync_from_year', 2020));
        $anoTo = (int) ($validated['ano_to'] ?? FundebOpenDataImportService::suggestedImportYear());
        if ($anoFrom > $anoTo) {
            [$anoFrom, $anoTo] = [$anoTo, $anoFrom];
        }

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Fundeb,
            'sync_all_years',
            __('FUNDEB — sincronizar anos :from–:to', ['from' => (string) $anoFrom, 'to' => (string) $anoTo]),
            [
                'use_nearest_year' => $request->boolean('use_nearest_year'),
                'ano_from' => $anoFrom,
                'ano_to' => $anoTo,
                'include_cached_years' => $request->boolean('include_cached_years', true),
                'import_mode' => FundebImportMode::normalize($validated['import_mode'] ?? null),
            ],
            null,
        );

        return redirect()
            ->route('admin.public-data.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }

    private function dispatchTransfersAllCities(int $ano): RedirectResponse
    {
        $cities = City::query()->forAnalytics()->get();
        $queued = 0;
        $skipped = 0;

        foreach ($cities as $city) {
            if (FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) === null) {
                $skipped++;

                continue;
            }

            $this->syncQueue->dispatch(
                AdminSyncDomain::Funding,
                'import_transfers_city_year',
                __('Repasses — :city, ano :ano', ['city' => $city->name, 'ano' => (string) $ano]),
                ['city_id' => $city->id, 'ano' => $ano],
                $city->id,
            );
            $queued++;
        }

        $message = $queued > 0
            ? trans_choice(
                ':count tarefa de repasses enfileirada.|:count tarefas de repasses enfileiradas.',
                $queued,
                ['count' => $queued],
            )
            : __('Nenhuma tarefa enfileirada — verifique IBGE nos municípios.');

        if ($skipped > 0) {
            $message .= ' '.trans_choice(
                ':count município ignorado (sem IBGE).|:count municípios ignorados (sem IBGE).',
                $skipped,
                ['count' => $skipped],
            );
        }

        return redirect()
            ->route('admin.public-data.index')
            ->with('public_data_bulk_queued', [
                'queued' => $queued,
                'message' => $message,
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dispatchCadunicoAutoSync(array $validated, bool $singleYear = false): RedirectResponse
    {
        $ano = isset($validated['ano']) ? (int) $validated['ano'] : CadunicoOpenDataImportService::suggestedImportYear();
        $label = $singleYear
            ? __('CadÚnico automático — ano :ano', ['ano' => (string) $ano])
            : __('CadÚnico automático — anos configurados');

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Cadastro,
            'auto_sync',
            $label,
            [
                'ano' => $ano,
                'all_years' => ! $singleYear,
                'fill_gaps' => true,
            ],
            null,
        );

        return redirect()
            ->route('admin.public-data.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }
}
