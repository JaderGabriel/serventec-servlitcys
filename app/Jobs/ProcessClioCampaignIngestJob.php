<?php

namespace App\Jobs;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * S2/S3 — ingerir (classificar/ZIP) e em seguida interpretar artefatos pendentes.
 * Se a coleta já estava analisada, reenfileira a análise (CLI-IND-04).
 */
class ProcessClioCampaignIngestJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    /**
     * @param  list<int>|null  $zipArtifactIds
     */
    public function __construct(
        public int $campaignId,
        public ?string $path = null,
        public ?array $zipArtifactIds = null,
        public bool $parse = true,
    ) {
        $this->onQueue((string) config('clio.queue', 'clio'));
    }

    public function handle(CampaignIngestService $ingest, CampaignParseService $parser): void
    {
        $campaign = ClioCampaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        if (filled($this->path)) {
            $ingest->ingestFromPath($campaign, (string) $this->path);
        } else {
            $ingest->expandPendingZips($campaign, $this->zipArtifactIds);
        }

        if ($this->parse) {
            $stats = $parser->parseCampaign($campaign->fresh() ?? $campaign);
            $fresh = $campaign->fresh() ?? $campaign;
            $ok = (int) ($stats['ok'] ?? 0) + (int) ($stats['warning'] ?? 0);
            if ($ok > 0 && in_array($fresh->status, [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ], true)) {
                ProcessClioCampaignAnalyzeJob::dispatch((int) $fresh->id, parseFirst: false);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('clio.ingest.job_failed', [
            'campaign_id' => $this->campaignId,
            'path' => $this->path,
            'message' => $exception?->getMessage(),
        ]);
    }
}
