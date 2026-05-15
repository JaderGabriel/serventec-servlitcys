<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Inep\SaebHistoricoDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PedagogicalSyncController extends Controller
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
    ) {}

    public function index(): View
    {
        $historico = app(SaebHistoricoDatabase::class);
        $pontosCount = $historico->pointsCount();
        $meta = $historico->meta();
        $exists = $pontosCount > 0 || (is_array($meta) && $meta !== []);
        $rel = SaebHistoricoDatabase::STORAGE_LABEL;

        $appUrl = rtrim((string) config('app.url', ''), '/');
        $officialEnvSet = trim((string) config('ieducar.saeb.official_url_template', '')) !== '';
        $appUrlOk = $appUrl !== '' && str_starts_with($appUrl, 'http');

        $defaultMdYear = max(2000, (int) date('Y') - 1);

        $cities = City::query()->forAnalytics()->orderBy('name')->get();
        $effectiveOfficialTemplate = $officialEnvSet
            ? trim((string) config('ieducar.saeb.official_url_template', ''))
            : ($appUrlOk ? $appUrl.'/api/saeb/municipio/{ibge}.json' : '');
        $importUrlsRaw = trim((string) config('ieducar.saeb.import_urls', ''));
        $microdadosZipTemplate = (string) config(
            'ieducar.saeb.microdados_inep_zip_url_template',
            'https://download.inep.gov.br/microdados/microdados_saeb_{year}.zip'
        );
        $microdadosZipExample = str_replace('{year}', (string) $defaultMdYear, $microdadosZipTemplate);
        $opendataCsvUrl = trim((string) config('ieducar.saeb.microdados_opendata_csv_url', ''));

        return view('admin.pedagogical-sync.index', [
            'jsonPath' => $rel,
            'absPath' => null,
            'fileExists' => $exists,
            'meta' => $meta,
            'pontosCount' => $pontosCount,
            'importUrlsConfigured' => $importUrlsRaw !== '',
            'importUrlDefaultsCount' => is_array(config('ieducar.saeb.import_url_defaults')) ? count(config('ieducar.saeb.import_url_defaults')) : 0,
            'officialTemplateConfigured' => $officialEnvSet,
            'officialUrlUsesAppDefault' => ! $officialEnvSet && $appUrlOk,
            'appUrl' => $appUrl,
            'microdadosEnabled' => filter_var(config('ieducar.saeb.microdados_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'defaultMicrodadosYear' => $defaultMdYear,
            'opendataCsvUrlConfigured' => $opendataCsvUrl !== '',
            'cities' => $cities,
            'cityCount' => $cities->count(),
            'effectiveOfficialTemplate' => $effectiveOfficialTemplate,
            'importUrlsDisplay' => $importUrlsRaw,
            'microdadosZipTemplate' => $microdadosZipTemplate,
            'microdadosZipExample' => $microdadosZipExample,
            'opendataCsvUrl' => $opendataCsvUrl,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:import_official,import_urls,import_csv,import_microdados',
            'csv_file' => 'exclude_unless:action,import_csv|required|file|max:15360',
            'csv_merge' => 'sometimes|boolean',
            'csv_resolve_inep' => 'sometimes|boolean',
            'md_year' => 'exclude_unless:action,import_microdados|nullable|integer|min:2000|max:2100',
            'md_url' => 'exclude_unless:action,import_microdados|nullable|string|max:2048',
            'md_merge' => 'sometimes|boolean',
            'md_resolve_inep' => 'sometimes|boolean',
            'md_keep_cache' => 'sometimes|boolean',
            'use_custom_official_url' => 'exclude_unless:action,import_official|sometimes|boolean',
            'official_url_override' => 'exclude_unless:action,import_official|nullable|string|max:2048',
        ]);

        $action = $validated['action'];
        $label = match ($action) {
            'import_official' => __('SAEB — importação oficial por município'),
            'import_urls' => __('SAEB — importação por URLs configuradas'),
            'import_csv' => __('SAEB — importação CSV'),
            'import_microdados' => __('SAEB — microdados INEP'),
            default => $action,
        };

        $payload = ['action' => $action];

        if ($action === 'import_csv') {
            $upload = $request->file('csv_file');
            if ($upload === null || ! $upload->isValid()) {
                return redirect()
                    ->route('admin.pedagogical-sync.index')
                    ->with('pedagogical_sync_error', __('Ficheiro CSV inválido ou em falta.'));
            }

            $ext = strtolower((string) $upload->getClientOriginalExtension());
            if (! in_array($ext, ['csv', 'txt'], true)) {
                return redirect()
                    ->route('admin.pedagogical-sync.index')
                    ->with('pedagogical_sync_error', __('Use um ficheiro .csv ou .txt.'));
            }

            $dir = storage_path('app/saeb/csv_imports');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $safe = 'import_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
            $absolute = $dir.'/'.$safe;
            $upload->move($dir, $safe);

            $payload['csv_path'] = $absolute;
            $payload['csv_merge'] = $request->boolean('csv_merge', true);
            $payload['csv_resolve_inep'] = $request->boolean('csv_resolve_inep', true);
        }

        if ($action === 'import_official' && $request->boolean('use_custom_official_url')) {
            $override = trim((string) $request->input('official_url_override', ''));
            if ($override === '' || ! str_contains($override, '{ibge}')) {
                return redirect()
                    ->route('admin.pedagogical-sync.index')
                    ->withInput()
                    ->with('pedagogical_sync_error', __('Indique uma URL modelo com o placeholder {ibge}.'));
            }
            if (! str_starts_with($override, 'http://') && ! str_starts_with($override, 'https://')) {
                return redirect()
                    ->route('admin.pedagogical-sync.index')
                    ->withInput()
                    ->with('pedagogical_sync_error', __('A URL deve começar por http:// ou https://.'));
            }
            $payload['official_url_override'] = $override;
        }

        if ($action === 'import_microdados') {
            $payload['md_year'] = isset($validated['md_year']) && is_numeric($validated['md_year'])
                ? (int) $validated['md_year']
                : max(2000, (int) date('Y') - 1);
            $payload['md_url'] = trim((string) ($validated['md_url'] ?? ''));
            $payload['md_merge'] = $request->boolean('md_merge', true);
            $payload['md_resolve_inep'] = $request->boolean('md_resolve_inep', true);
            $payload['md_keep_cache'] = $request->boolean('md_keep_cache', false);
        }

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Pedagogical,
            $action,
            $label,
            $payload,
            null,
        );

        return redirect()
            ->route('admin.pedagogical-sync.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }
}
