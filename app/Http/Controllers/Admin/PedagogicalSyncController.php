<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\Inep\SaebCsvPedagogicalImportService;
use App\Services\Inep\SaebMicrodadosOpenDataImportService;
use App\Services\Inep\SaebOfficialMunicipalImportService;
use App\Services\Inep\SaebPedagogicalImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PedagogicalSyncController extends Controller
{
    public function index(): View
    {
        $rel = trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json'));
        $disk = Storage::disk('public');
        $exists = $rel !== '' && $disk->exists($rel);
        $meta = null;
        $pontosCount = 0;
        if ($exists) {
            $decoded = json_decode((string) $disk->get($rel), true);
            if (is_array($decoded)) {
                $meta = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : null;
                $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
                $pontosCount = is_array($pontos) ? count($pontos) : 0;
            }
        }

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
            'absPath' => storage_path('app/public/'.$rel),
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

    public function run(
        Request $request,
        SaebPedagogicalImportService $import,
        SaebOfficialMunicipalImportService $official,
        SaebCsvPedagogicalImportService $csvImport,
        SaebMicrodadosOpenDataImportService $microdados,
    ): RedirectResponse {
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

        @set_time_limit(300);

        if ($validated['action'] === 'import_microdados') {
            @set_time_limit(0);
            $merge = $request->boolean('md_merge', true);
            $resolveInep = $request->boolean('md_resolve_inep', true);
            $purgeExtract = ! $request->boolean('md_keep_cache', false);

            $fallbackYear = isset($validated['md_year']) && is_numeric($validated['md_year'])
                ? (int) $validated['md_year']
                : max(2000, (int) date('Y') - 1);
            $fallbackYear = max(2000, min(2100, $fallbackYear));

            $url = trim((string) ($validated['md_url'] ?? ''));
            if ($url === '') {
                $configured = trim((string) config('ieducar.saeb.microdados_opendata_csv_url', ''));
                if ($configured !== '') {
                    $url = $configured;
                }
            }

            if ($url !== '') {
                $result = $microdados->syncFromMicrodadosFormUrl($url, $merge, $resolveInep, $purgeExtract, $fallbackYear);
            } else {
                $result = $microdados->syncFromInepZip($fallbackYear, $merge, $resolveInep, $purgeExtract, null);
            }

            $message = $result['message'];
            if (! empty($result['avisos']) && is_array($result['avisos'])) {
                $slice = array_slice($result['avisos'], 0, 25);
                $message .= "\n\n".implode("\n", $slice);
                if (count($result['avisos']) > 25) {
                    $message .= "\n".__('… e mais :n avisos.', ['n' => (string) (count($result['avisos']) - 25)]);
                }
            }

            return redirect()
                ->route('admin.pedagogical-sync.index')
                ->with(
                    $result['ok'] ? 'pedagogical_sync_success' : 'pedagogical_sync_error',
                    $message
                );
        }

        if ($validated['action'] === 'import_csv') {
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

            try {
                $merge = $request->boolean('csv_merge', true);
                $resolveInep = $request->boolean('csv_resolve_inep', true);
                $result = $csvImport->importFromCsvFile($absolute, $merge, $resolveInep);
            } finally {
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }

            $message = $result['message'];
            if (! empty($result['avisos']) && is_array($result['avisos']) && count($result['avisos']) > 0) {
                $slice = array_slice($result['avisos'], 0, 25);
                $message .= "\n\n".implode("\n", $slice);
                if (count($result['avisos']) > 25) {
                    $message .= "\n".__('… e mais :n avisos.', ['n' => (string) (count($result['avisos']) - 25)]);
                }
            }

            return redirect()
                ->route('admin.pedagogical-sync.index')
                ->with(
                    $result['ok'] ? 'pedagogical_sync_success' : 'pedagogical_sync_error',
                    $message
                );
        }

        if ($validated['action'] === 'import_official') {
            $override = null;
            if ($request->boolean('use_custom_official_url')) {
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
            }
            $result = $official->importFromOfficialTemplate($override);
        } else {
            $result = $import->importFromConfiguredSources();
        }

        return redirect()
            ->route('admin.pedagogical-sync.index')
            ->with(
                $result['ok'] ? 'pedagogical_sync_success' : 'pedagogical_sync_error',
                $result['message']
            );
    }
}
