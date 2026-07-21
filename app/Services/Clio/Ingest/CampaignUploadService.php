<?php

namespace App\Services\Clio\Ingest;

use App\Models\Clio\ClioCampaign;
use Illuminate\Http\UploadedFile;

/**
 * @deprecated Preferir CampaignIngestService — mantido como fachada para chamadas antigas.
 */
final class CampaignUploadService
{
    public function __construct(
        private readonly CampaignIngestService $ingest,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string|null>  $relativePaths
     * @return array{stored: int, ignored: int, duplicates: int, expanded: int, zip_ids: list<int>, artifacts: list<\App\Models\Clio\ClioCampaignArtifact>}
     */
    public function storeUploads(ClioCampaign $campaign, array $files, array $relativePaths = []): array
    {
        return $this->ingest->storeUploads($campaign, $files, $relativePaths);
    }
}
