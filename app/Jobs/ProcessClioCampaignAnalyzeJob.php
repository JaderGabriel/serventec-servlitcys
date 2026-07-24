<?php

namespace App\Jobs;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reanálise Modo A após import Drive ou reparse (o analyzer também refresca o BI).
 */
class ProcessClioCampaignAnalyzeJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(
        public int $campaignId,
        public bool $parseFirst = false,
    ) {
        $this->onQueue((string) config('clio.queue', 'clio'));
    }

    public function handle(CampaignAnalyzer $analyzer): void
    {
        $campaign = ClioCampaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        $analyzer->analyze($campaign, parseFirst: $this->parseFirst);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('clio.analyze.job_failed', [
            'campaign_id' => $this->campaignId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
