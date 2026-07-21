<?php

namespace App\Services\Clio\Drive;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Support\Carbon;

final class CampaignDriveImportService
{
    public function __construct(
        private readonly GoogleDriveFolderClient $drive,
        private readonly CampaignIngestService $ingest,
        private readonly CampaignParseService $parser,
    ) {}

    public function resolveDriveUrl(ClioCampaign $campaign): ?string
    {
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $fromMeta = trim((string) ($meta['drive_folder_url'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $fromCity = trim((string) ($campaign->city?->clio_drive_url ?? ''));

        return $fromCity !== '' ? $fromCity : null;
    }

    /**
     * @return array{ok: bool, message: string, summary: array, warnings: list<string>, files_preview: list<array>}
     */
    public function verify(ClioCampaign $campaign, ?string $url = null): array
    {
        $url = trim((string) ($url ?: $this->resolveDriveUrl($campaign)));
        if ($url === '') {
            return [
                'ok' => false,
                'message' => __('Defina o link da pasta Google Drive do município.'),
                'summary' => [],
                'warnings' => [],
                'files_preview' => [],
            ];
        }

        $result = $this->drive->verify($url);
        $preview = array_slice($result['files'], 0, 40);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'summary' => $result['summary'],
            'warnings' => $result['warnings'],
            'files_preview' => $preview,
            'resource_type' => $result['resource_type'],
            'resource_id' => $result['resource_id'],
            'url' => $url,
        ];
    }

    /**
     * @return array{stored: int, expanded: int, duplicates: int, ignored: int, parsed: int, downloaded: int, skipped: int, message: string}
     */
    public function import(ClioCampaign $campaign, ?string $url = null, bool $parse = true): array
    {
        $url = trim((string) ($url ?: $this->resolveDriveUrl($campaign)));
        if ($url === '') {
            throw new \InvalidArgumentException(__('Defina o link da pasta Google Drive do município.'));
        }

        $download = $this->drive->downloadRelevantToTemp($url);

        try {
            $result = $this->ingest->ingestFromPath($campaign, $download['dir']);
            $parsed = 0;
            if ($parse) {
                $parseStats = $this->parser->parseCampaign($campaign->fresh() ?? $campaign);
                $parsed = (int) ($parseStats['parsed'] ?? 0);
            }

            $resource = $this->drive->parseResource($url);
            $meta = is_array($campaign->meta) ? $campaign->meta : [];
            $meta['drive_folder_url'] = $url;
            if ($resource !== null) {
                $meta['drive_folder_id'] = $resource['id'];
                $meta['drive_resource_type'] = $resource['type'];
            }
            $meta['drive_last_import_at'] = Carbon::now()->toIso8601String();
            $meta['drive_last_import'] = [
                'downloaded' => $download['downloaded'],
                'skipped' => $download['skipped'],
                'bytes' => $download['bytes'],
                'stored' => $result['stored'],
            ];

            $campaign->update([
                'source' => 'drive_upload',
                'meta' => $meta,
            ]);

            if ($campaign->city && blank($campaign->city->clio_drive_url)) {
                $campaign->city->update(['clio_drive_url' => $url]);
            }

            $message = __('Importação Drive: :dl descarregado(s), :stored ingerido(s), :exp de ZIP, :dup duplicado(s), :ign ignorado(s), :parsed interpretado(s).', [
                'dl' => $download['downloaded'],
                'stored' => $result['stored'],
                'exp' => $result['expanded'],
                'dup' => $result['duplicates'],
                'ign' => $result['ignored'],
                'parsed' => $parsed,
            ]);

            return [
                'stored' => $result['stored'],
                'expanded' => $result['expanded'],
                'duplicates' => $result['duplicates'],
                'ignored' => $result['ignored'],
                'parsed' => $parsed,
                'downloaded' => $download['downloaded'],
                'skipped' => $download['skipped'],
                'message' => $message,
            ];
        } finally {
            $this->drive->deleteDirectory($download['dir']);
        }
    }

    public function syncUrlToCityAndCampaign(ClioCampaign $campaign, string $url): void
    {
        $url = trim($url);
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $meta['drive_folder_url'] = $url !== '' ? $url : null;
        $resource = $url !== '' ? $this->drive->parseResource($url) : null;
        if ($resource !== null) {
            $meta['drive_folder_id'] = $resource['id'];
            $meta['drive_resource_type'] = $resource['type'];
        } else {
            unset($meta['drive_folder_id'], $meta['drive_resource_type']);
        }

        $campaign->update(['meta' => $meta]);

        if ($campaign->city) {
            $campaign->city->update(['clio_drive_url' => $url !== '' ? $url : null]);
        }
    }

    public function verifyCityUrl(City $city): array
    {
        $url = trim((string) ($city->clio_drive_url ?? ''));
        if ($url === '') {
            return [
                'ok' => false,
                'message' => __('Este município ainda não tem link Drive.'),
                'summary' => [],
                'warnings' => [],
                'files_preview' => [],
            ];
        }

        $result = $this->drive->verify($url);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'summary' => $result['summary'],
            'warnings' => $result['warnings'],
            'files_preview' => array_slice($result['files'], 0, 40),
            'resource_type' => $result['resource_type'],
            'resource_id' => $result['resource_id'],
            'url' => $url,
        ];
    }
}
