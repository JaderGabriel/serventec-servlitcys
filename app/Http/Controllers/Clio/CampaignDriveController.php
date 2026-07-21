<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Drive\CampaignDriveImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignDriveController extends Controller
{
    public function updateUrl(Request $request, ClioCampaign $campaign, CampaignDriveImportService $driveImport): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'clio_drive_url' => ['nullable', 'string', 'max:1024'],
        ]);

        $url = trim((string) ($data['clio_drive_url'] ?? ''));
        $driveImport->syncUrlToCityAndCampaign($campaign, $url);

        return back()->with('success', $url !== ''
            ? __('Link Drive guardado no município e na coleta.')
            : __('Link Drive removido.'));
    }

    public function verify(Request $request, ClioCampaign $campaign, CampaignDriveImportService $driveImport): RedirectResponse
    {
        $this->authorize('view', $campaign);

        if ($request->has('clio_drive_url') && $request->user()?->can('update', $campaign)) {
            $data = $request->validate([
                'clio_drive_url' => ['nullable', 'string', 'max:1024'],
            ]);
            $driveImport->syncUrlToCityAndCampaign($campaign, trim((string) ($data['clio_drive_url'] ?? '')));
            $campaign->refresh();
            $campaign->load('city');
        }

        $result = $driveImport->verify($campaign);

        $flash = $result['ok'] ? 'success' : 'warning';
        $message = $result['message'];

        if (($result['summary']['by_kind'] ?? []) !== []) {
            $parts = [];
            foreach ($result['summary']['by_kind'] as $kind => $count) {
                $parts[] = $kind.': '.$count;
            }
            $message .= ' · '.implode(', ', $parts);
        }

        if ($result['warnings'] !== []) {
            $message .= ' — '.implode(' ', $result['warnings']);
        }

        return back()
            ->with($flash, $message)
            ->with('clio_drive_verify', $result);
    }

    public function import(Request $request, ClioCampaign $campaign, CampaignDriveImportService $driveImport): RedirectResponse
    {
        $this->authorize('upload', $campaign);

        if ($request->has('clio_drive_url')) {
            $data = $request->validate([
                'clio_drive_url' => ['nullable', 'string', 'max:1024'],
            ]);
            $driveImport->syncUrlToCityAndCampaign($campaign, trim((string) ($data['clio_drive_url'] ?? '')));
            $campaign->refresh();
            $campaign->load('city');
        }

        try {
            $result = $driveImport->import($campaign);
        } catch (\Throwable $e) {
            return back()->with('warning', __('Importação Drive falhou: :m', ['m' => $e->getMessage()]));
        }

        return redirect()
            ->route('clio.campaigns.upload', $campaign)
            ->with($result['stored'] > 0 ? 'success' : 'warning', $result['message']);
    }
}
