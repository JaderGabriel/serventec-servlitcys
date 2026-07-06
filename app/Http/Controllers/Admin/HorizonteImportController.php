<?php

namespace App\Http\Controllers\Admin;

use App\Authorization\PublicDataHub;
use App\Http\Controllers\Controller;
use App\Services\Admin\HorizonteImportHubStatusService;
use App\Services\Horizonte\HorizonteDataBundleService;
use App\Services\Horizonte\HorizonteEducacensoMatriculasSyncService;
use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use App\Services\Horizonte\HorizonteIbgeMunicipalGeoImportService;
use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use App\Support\Horizonte\HorizonteFeedPhaseOptions;
use App\Support\Horizonte\HorizonteIbgeMunicipalGeoImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub admin dedicado ao abastecimento nacional do mapa Horizonte. */
class HorizonteImportController extends Controller
{
    public function __construct(
        private HorizonteImportHubStatusService $horizonteHub,
    ) {}

    public function index(): View
    {
        return view('admin.horizonte-import.index', [
            'horizonteHub' => $this->horizonteHub->build(),
        ]);
    }

    public function feed(Request $request, HorizonteFortnightlyFeedService $feed): RedirectResponse
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToHub();
        }

        $this->authorize('sync', PublicDataHub::class);

        if (! (bool) config('horizonte.enabled', true)) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Horizonte desactivado (HORIZONTE_ENABLED).'));
        }

        if (! (bool) config('horizonte.fortnightly_feed.enabled', true)) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Abastecimento bimestral Horizonte desactivado (HORIZONTE_FORTNIGHTLY_FEED_ENABLED).'));
        }

        @set_time_limit(600);

        $selectedPhases = $request->input('phases', []);
        if (! is_array($selectedPhases)) {
            $selectedPhases = [];
        }
        $selectedPhases = array_values(array_filter(array_map('strval', $selectedPhases)));

        if ($selectedPhases === []) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Selecione pelo menos uma fase para executar.'));
        }

        $invalid = array_diff($selectedPhases, HorizonteFeedPhaseOptions::defaultSelectedPhaseKeys());
        if ($invalid !== []) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Fase inválida: :key', ['key' => implode(', ', $invalid)]));
        }

        $ufRaw = trim((string) $request->input('uf', ''));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            return $this->redirectToHub()
                ->with('public_data_error', __('UF inválida: :uf — escolha uma sigla válida ou deixe em branco para abastecimento nacional.', [
                    'uf' => $ufRaw,
                ]));
        }

        $feedOptions = array_merge(
            HorizonteFeedPhaseOptions::skipOptionsFromSelectedPhases($selectedPhases),
            [
                'uf' => $ufRaw,
                'reset' => true,
                'selected_phases' => $selectedPhases,
            ],
        );

        $staged = filter_var(config('horizonte.fortnightly_feed.staged', true), FILTER_VALIDATE_BOOLEAN);
        $result = $staged
            ? $feed->runStaged($feedOptions)
            : $feed->run($feedOptions);

        return $this->redirectToHub()
            ->with('horizonte_feed', [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'phases' => is_array($result['phases'] ?? null) ? $result['phases'] : [],
                'pipeline' => is_array($result['pipeline'] ?? null) ? $result['pipeline'] : null,
                'staged' => $staged,
                'selected_phases' => $selectedPhases,
            ]);
    }

    public function educacensoSync(Request $request, HorizonteEducacensoMatriculasSyncService $sync): RedirectResponse
    {
        $this->authorize('sync', PublicDataHub::class);

        if (! (bool) config('horizonte.enabled', true)) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Horizonte desactivado (HORIZONTE_ENABLED).'));
        }

        @set_time_limit(900);
        $memory = trim((string) config('horizonte.fortnightly_feed.educacenso_memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $request->input('uf', ''));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            return $this->redirectToHub('horizonte-educacenso-sync')
                ->with('public_data_error', __('UF inválida: :uf', ['uf' => $ufRaw]));
        }

        $yearRaw = trim((string) $request->input('year', ''));
        $year = $yearRaw !== '' && ctype_digit($yearRaw) ? (int) $yearRaw : null;
        $years = HorizonteEducacensoYearWindow::years();
        if ($year !== null && ! in_array($year, $years, true)) {
            return $this->redirectToHub('horizonte-educacenso-sync')
                ->with('public_data_error', __('Ano :ano fora da janela Educacenso.', ['ano' => (string) $year]));
        }

        $steps = max(1, min(27, (int) $request->input('steps', config('horizonte.fortnightly_feed.educacenso_steps_per_step', 1))));

        if ($request->boolean('reset')) {
            HorizonteEducacensoImportProgress::reset();
        }

        $result = $sync->syncBatch(array_filter([
            'reset' => false,
            'year' => $year,
            'uf' => $ufRaw !== '' ? $ufRaw : null,
            'steps' => $steps,
        ], static fn ($v): bool => $v !== null));

        return $this->redirectToHub('horizonte-educacenso-sync')
            ->with('horizonte_educacenso_sync', [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'completed_steps' => is_array($result['completed_steps'] ?? null) ? $result['completed_steps'] : [],
                'educacenso_done' => (int) ($result['educacenso_done'] ?? 0),
                'educacenso_total' => (int) ($result['educacenso_total'] ?? 0),
            ]);
    }

    public function municipalGeoSync(Request $request, HorizonteIbgeMunicipalGeoImportService $import): RedirectResponse
    {
        $this->authorize('sync', PublicDataHub::class);

        if (! (bool) config('horizonte.enabled', true)) {
            return $this->redirectToHub()
                ->with('public_data_error', __('Horizonte desactivado (HORIZONTE_ENABLED).'));
        }

        $mode = (string) $request->input('mode', 'step');
        @set_time_limit($mode === 'all' ? 3600 : 900);
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $request->input('uf', ''));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            return $this->redirectToHub('horizonte-municipal-geo-sync')
                ->with('public_data_error', __('UF inválida: :uf', ['uf' => $ufRaw]));
        }

        if ($request->boolean('reset')) {
            HorizonteIbgeMunicipalGeoImportProgress::reset();
        }

        $ufsPerStep = max(1, min(3, (int) $request->input('ufs_per_step', config('horizonte.municipal_geo.ufs_per_step', 1))));

        $baseOptions = array_filter([
            'uf' => $ufRaw !== '' ? $ufRaw : null,
            'ufs_per_step' => $ufsPerStep,
            'force' => $request->boolean('force'),
        ], static fn ($v): bool => $v !== null);

        $completedSteps = [];
        $result = ['success' => false, 'message' => ''];

        if ($mode === 'all') {
            $iteration = 0;
            $maxIterations = HorizonteIbgeMunicipalGeoImportProgress::totalUfs() + 5;

            while (! HorizonteIbgeMunicipalGeoImportProgress::isComplete() && $iteration < $maxIterations) {
                $iteration++;
                $doneBefore = HorizonteIbgeMunicipalGeoImportProgress::doneCount();
                $result = $import->importNextUfBatch($baseOptions);
                $completedSteps = array_merge($completedSteps, is_array($result['steps'] ?? null) ? $result['steps'] : []);

                if ($result['skipped'] ?? false) {
                    break;
                }

                if (HorizonteIbgeMunicipalGeoImportProgress::doneCount() === $doneBefore && ($result['partial'] ?? false)) {
                    break;
                }

                if ($result['complete'] ?? false) {
                    break;
                }

                if (! ($result['partial'] ?? false)) {
                    break;
                }
            }
        } else {
            $result = $import->importNextUfBatch($baseOptions);
            $completedSteps = is_array($result['steps'] ?? null) ? $result['steps'] : [];
        }

        return $this->redirectToHub('horizonte-municipal-geo-sync')
            ->with('horizonte_municipal_geo_sync', [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'completed_steps' => $completedSteps,
                'municipal_geo_done' => HorizonteIbgeMunicipalGeoImportProgress::doneCount(),
                'municipal_geo_total' => HorizonteIbgeMunicipalGeoImportProgress::totalUfs(),
            ]);
    }

    public function bundleExport(Request $request, HorizonteDataBundleService $bundle): RedirectResponse
    {
        $sections = [
            'fundeb' => $request->boolean('section_fundeb', true),
            'censo' => $request->boolean('section_censo', true),
            'saeb' => $request->boolean('section_saeb', true),
            'cadunico' => $request->boolean('section_cadunico', true),
            'demography' => $request->boolean('section_demography', true),
            'transfers' => $request->boolean('section_transfers', true),
            'ibge_cache' => $request->boolean('section_ibge_cache', true),
            'sge_registry' => $request->boolean('section_sge_registry', true),
        ];

        try {
            $result = $bundle->export($sections);
        } catch (\Throwable $e) {
            return $this->redirectToHub('horizonte-offline-bundle')
                ->with('public_data_error', __('Exportação Horizonte falhou: :msg', ['msg' => $e->getMessage()]));
        }

        return $this->redirectToHub('horizonte-offline-bundle')
            ->with('horizonte_bundle', [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'path' => (string) ($result['path'] ?? ''),
            ]);
    }

    public function bundleImport(Request $request, HorizonteDataBundleService $bundle): RedirectResponse
    {
        $request->validate([
            'bundle' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        $file = $request->file('bundle');
        if ($file === null) {
            return $this->redirectToHub('horizonte-offline-bundle')
                ->with('public_data_error', __('Arquivo ZIP em falta.'));
        }

        $dir = storage_path('app/horizonte/bundles/uploads');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $this->redirectToHub('horizonte-offline-bundle')
                ->with('public_data_error', __('Não foi possível criar directório de upload.'));
        }

        $storedPath = $dir.'/upload-'.now()->format('Ymd-His').'.zip';
        $file->move($dir, basename($storedPath));

        $sections = [
            'fundeb' => $request->boolean('section_fundeb', true),
            'censo' => $request->boolean('section_censo', true),
            'saeb' => $request->boolean('section_saeb', true),
            'cadunico' => $request->boolean('section_cadunico', true),
            'demography' => $request->boolean('section_demography', true),
            'transfers' => $request->boolean('section_transfers', true),
            'ibge_cache' => $request->boolean('section_ibge_cache', true),
            'sge_registry' => $request->boolean('section_sge_registry', true),
        ];

        try {
            $result = $bundle->import($storedPath, $sections, $request->boolean('dry_run'));
        } catch (\Throwable $e) {
            return $this->redirectToHub('horizonte-offline-bundle')
                ->with('public_data_error', __('Importação Horizonte falhou: :msg', ['msg' => $e->getMessage()]));
        }

        return $this->redirectToHub('horizonte-offline-bundle')
            ->with('horizonte_bundle', [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'imported' => is_array($result['imported'] ?? null) ? $result['imported'] : [],
            ]);
    }

    private function redirectToHub(?string $fragment = null): RedirectResponse
    {
        $url = route('admin.horizonte-import.index');
        if ($fragment !== null && $fragment !== '') {
            $url .= '#'.$fragment;
        }

        return redirect()->to($url);
    }
}
