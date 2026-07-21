<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessClioCampaignIngestJob;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignUploadController extends Controller
{
    public function edit(ClioCampaign $campaign): View
    {
        $this->authorize('upload', $campaign);

        $campaign->load([
            'artifacts' => fn ($q) => $q->with('school')->latest(),
            'schools',
        ]);

        $counts = [
            'total' => $campaign->artifacts->count(),
            'pending' => $campaign->artifacts->where('parse_status', ClioCampaignArtifact::PARSE_PENDING)->count(),
            'by_kind' => $campaign->artifacts->groupBy('kind')->map->count()->all(),
        ];

        return view('clio.campaigns.upload', [
            'campaign' => $campaign,
            'counts' => $counts,
            'maxMb' => (int) config('clio.upload_max_mb', 64),
            'maxFiles' => (int) config('clio.max_files_per_upload', 200),
        ]);
    }

    public function store(
        Request $request,
        ClioCampaign $campaign,
        CampaignIngestService $ingest,
        CampaignParseService $parser,
    ): RedirectResponse {
        $this->authorize('upload', $campaign);

        $maxKb = max(1024, (int) config('clio.upload_max_mb', 64) * 1024);
        $maxFiles = (int) config('clio.max_files_per_upload', 200);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKb],
            'relative_paths' => ['nullable', 'array', 'max:'.$maxFiles],
            'relative_paths.*' => ['nullable', 'string', 'max:1024'],
            'async_zip' => ['nullable', 'boolean'],
        ]);

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = array_values(array_filter($request->file('files', [])));
        /** @var list<string|null> $relativePaths */
        $relativePaths = array_values($request->input('relative_paths', []));

        $asyncZip = $request->boolean('async_zip');
        $result = $ingest->storeUploads($campaign, $files, $relativePaths, expandZips: ! $asyncZip);

        if ($asyncZip && $result['zip_ids'] !== []) {
            ProcessClioCampaignIngestJob::dispatch($campaign->id, null, $result['zip_ids'], true);
        } else {
            $parseStats = $parser->parseCampaign($campaign->fresh() ?? $campaign);
            $result['parsed'] = $parseStats['parsed'];
        }

        $message = __('Upload Clio: :stored guardado(s), :exp de ZIP, :dup duplicado(s), :ign ignorado(s).', [
            'stored' => $result['stored'],
            'exp' => $result['expanded'],
            'dup' => $result['duplicates'],
            'ign' => $result['ignored'],
        ]);

        if (isset($result['parsed'])) {
            $message .= ' '.__('Parse: :n ficheiro(s).', ['n' => $result['parsed']]);
        }

        if ($asyncZip && $result['zip_ids'] !== []) {
            $message .= ' '.__('Expansão/parse ZIP na fila :queue.', ['queue' => (string) config('clio.queue', 'clio')]);
        }

        return redirect()
            ->route('clio.campaigns.upload', $campaign)
            ->with($result['stored'] > 0 || $result['zip_ids'] !== [] ? 'success' : 'warning', $message);
    }
}
