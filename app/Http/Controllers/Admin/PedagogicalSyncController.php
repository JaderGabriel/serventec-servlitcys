<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.pedagogical-sync.index', [
            'jsonPath' => $rel,
            'absPath' => storage_path('app/public/'.$rel),
            'fileExists' => $exists,
            'meta' => $meta,
            'pontosCount' => $pontosCount,
            'importUrlsConfigured' => trim((string) config('ieducar.saeb.import_urls', '')) !== '',
            'importUrlDefaultsCount' => is_array(config('ieducar.saeb.import_url_defaults')) ? count(config('ieducar.saeb.import_url_defaults')) : 0,
            'officialTemplateConfigured' => trim((string) config('ieducar.saeb.official_url_template', '')) !== '',
        ]);
    }

    public function run(
        Request $request,
        SaebPedagogicalImportService $import,
        SaebOfficialMunicipalImportService $official,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => 'required|string|in:import_official,import_urls',
        ]);

        @set_time_limit(300);

        $result = $validated['action'] === 'import_official'
            ? $official->importFromOfficialTemplate()
            : $import->importFromConfiguredSources();

        return redirect()
            ->route('admin.pedagogical-sync.index')
            ->with(
                $result['ok'] ? 'pedagogical_sync_success' : 'pedagogical_sync_error',
                $result['message']
            );
    }
}
