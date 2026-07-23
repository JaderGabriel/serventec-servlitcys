<?php

namespace App\Services\Clio\Drive;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
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
     * Cataloga ficheiros do Drive com ticket, tamanho e lotes (quando > limiar).
     *
     * @return array{
     *   ok: bool,
     *   message: string,
     *   summary: array,
     *   warnings: list<string>,
     *   files_preview: list<array>,
     *   catalog: array,
     *   resource_type?: string,
     *   resource_id?: string,
     *   url?: string
     * }
     */
    public function catalog(ClioCampaign $campaign, ?string $url = null): array
    {
        $url = trim((string) ($url ?: $this->resolveDriveUrl($campaign)));
        if ($url === '') {
            return [
                'ok' => false,
                'message' => __('Defina o link da pasta Google Drive do município.'),
                'summary' => [],
                'warnings' => [],
                'files_preview' => [],
                'catalog' => [],
            ];
        }

        $result = $this->drive->verify($url);
        $previous = $this->readCatalog($campaign);
        $prevById = [];
        foreach ($previous['files'] ?? [] as $row) {
            if (! empty($row['id'])) {
                $prevById[(string) $row['id']] = $row;
            }
        }

        $threshold = (int) config('clio.drive.batch_threshold', 100);
        $batchSize = (int) config('clio.drive.batch_size', 40);
        $maxFiles = (int) config('clio.drive.max_files', 500);

        $relevant = array_values(array_filter(
            $result['files'],
            fn (array $f): bool => ($f['kind'] ?? 'unknown') !== 'unknown'
        ));
        $relevant = array_slice($relevant, 0, $maxFiles);
        $useBatches = count($relevant) > $threshold;

        $files = [];
        $ticket = 1;
        foreach ($relevant as $index => $file) {
            $id = (string) $file['id'];
            $prev = $prevById[$id] ?? null;
            $batch = $useBatches ? (int) floor($index / $batchSize) + 1 : 1;
            $status = (string) ($prev['status'] ?? 'pending');
            if (! in_array($status, ['pending', 'ingested', 'parsed', 'skipped', 'failed', 'duplicate'], true)) {
                $status = 'pending';
            }

            $files[] = [
                'ticket' => 'DRV-'.str_pad((string) $ticket, 3, '0', STR_PAD_LEFT),
                'id' => $id,
                'name' => (string) $file['name'],
                'path' => (string) $file['path'],
                'kind' => (string) $file['kind'],
                'size' => $file['size'] ?? ($prev['size'] ?? null),
                'size_local' => $prev['size_local'] ?? null,
                'status' => $status,
                'batch' => $batch,
                'error' => $prev['error'] ?? null,
                'artifact_id' => $prev['artifact_id'] ?? null,
            ];
            $ticket++;
        }

        $totalBatches = $useBatches
            ? (int) max(1, (int) ceil(count($files) / max(1, $batchSize)))
            : 1;

        $counts = $this->countCatalogStatuses($files);
        $nextBatch = $this->nextPendingBatch($files);

        $catalog = [
            'cataloged_at' => Carbon::now()->toIso8601String(),
            'folder_url' => $url,
            'resource_id' => $result['resource_id'] ?? null,
            'resource_type' => $result['resource_type'] ?? null,
            'batch_mode' => $useBatches,
            'batch_size' => $batchSize,
            'batch_threshold' => $threshold,
            'total_batches' => $totalBatches,
            'next_batch' => $nextBatch,
            'counts' => $counts,
            'files' => $files,
            'summary' => $result['summary'],
            'warnings' => $result['warnings'],
            'message' => $result['message'],
        ];

        $this->persistCatalog($campaign, $catalog, $url, $result);

        $preview = array_slice($files, 0, 80);
        $message = $result['message'];
        if ($useBatches) {
            $message .= ' · '.__('Importação em :n lotes de até :s ficheiros (evita timeout).', [
                'n' => $totalBatches,
                's' => $batchSize,
            ]);
        }

        return [
            'ok' => $result['ok'],
            'message' => $message,
            'summary' => $result['summary'],
            'warnings' => $result['warnings'],
            'files_preview' => $preview,
            'catalog' => $catalog,
            'resource_type' => $result['resource_type'] ?? null,
            'resource_id' => $result['resource_id'] ?? null,
            'url' => $url,
        ];
    }

    /**
     * @return array{ok: bool, message: string, summary: array, warnings: list<string>, files_preview: list<array>}
     */
    public function verify(ClioCampaign $campaign, ?string $url = null): array
    {
        return $this->catalog($campaign, $url);
    }

    /**
     * Importa o próximo lote (ou todos se abaixo do limiar / mode=all restantes num lote).
     *
     * @return array{
     *   stored: int,
     *   expanded: int,
     *   duplicates: int,
     *   ignored: int,
     *   parsed: int,
     *   downloaded: int,
     *   skipped: int,
     *   batch: int,
     *   total_batches: int,
     *   pending: int,
     *   complete: bool,
     *   message: string,
     *   catalog: array
     * }
     */
    public function importNextBatch(ClioCampaign $campaign, ?string $url = null, bool $parse = true): array
    {
        @set_time_limit(max(120, (int) config('clio.drive.request_timeout', 120) * 3));

        $catalog = $this->readCatalog($campaign);
        if (($catalog['files'] ?? []) === []) {
            $cataloged = $this->catalog($campaign, $url);
            if (! $cataloged['ok'] && ($cataloged['catalog']['files'] ?? []) === []) {
                throw new \InvalidArgumentException($cataloged['message']);
            }
            $catalog = $cataloged['catalog'];
        }

        $batchNo = (int) ($catalog['next_batch'] ?? $this->nextPendingBatch($catalog['files'] ?? []));
        if ($batchNo < 1) {
            return [
                'stored' => 0,
                'expanded' => 0,
                'duplicates' => 0,
                'ignored' => 0,
                'parsed' => 0,
                'downloaded' => 0,
                'skipped' => 0,
                'batch' => 0,
                'total_batches' => (int) ($catalog['total_batches'] ?? 1),
                'pending' => 0,
                'complete' => true,
                'message' => __('Nada pendente no catálogo Drive — todos os tickets já foram processados.'),
                'catalog' => $catalog,
            ];
        }

        $batchFiles = array_values(array_filter(
            $catalog['files'],
            fn (array $f): bool => (int) ($f['batch'] ?? 1) === $batchNo
                && in_array(($f['status'] ?? 'pending'), ['pending', 'failed'], true)
        ));

        if ($batchFiles === []) {
            $catalog['next_batch'] = $this->nextPendingBatch($catalog['files']);
            $this->writeCatalogOnly($campaign, $catalog);

            return $this->importNextBatch($campaign, $url, $parse);
        }

        $download = $this->drive->downloadSelectedToTemp($batchFiles);

        try {
            $driveMeta = [];
            foreach ($download['by_path'] as $rel => $info) {
                if (($info['status'] ?? '') === 'downloaded') {
                    $driveMeta[$rel] = ['id' => $info['id']];
                }
            }

            $result = $this->ingest->ingestFromPath($campaign, $download['dir'], $driveMeta);
            $parsed = 0;
            if ($parse) {
                $parseStats = $this->parser->parseCampaign($campaign->fresh() ?? $campaign);
                $parsed = (int) ($parseStats['parsed'] ?? 0);
            }

            $artifactsByDriveId = [];
            foreach ($result['artifacts'] as $artifact) {
                $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
                $driveId = (string) ($meta['drive_file_id'] ?? '');
                if ($driveId !== '') {
                    $artifactsByDriveId[$driveId] = $artifact;
                }
            }

            foreach ($catalog['files'] as &$file) {
                if ((int) ($file['batch'] ?? 1) !== $batchNo) {
                    continue;
                }
                $id = (string) ($file['id'] ?? '');
                $path = (string) ($file['path'] ?? '');
                $downloadInfo = $download['by_path'][$path] ?? null;
                // sanitize may change path — try also basename match
                if ($downloadInfo === null) {
                    foreach ($download['by_path'] as $rel => $info) {
                        if (($info['id'] ?? '') === $id) {
                            $downloadInfo = $info;
                            $path = $rel;
                            break;
                        }
                    }
                }

                if ($downloadInfo === null) {
                    continue;
                }

                $file['size_local'] = $downloadInfo['size'] ?? $file['size_local'] ?? null;
                if (($downloadInfo['size'] ?? 0) > 0) {
                    $file['size'] = $downloadInfo['size'];
                }

                $dlStatus = (string) ($downloadInfo['status'] ?? '');
                if (in_array($dlStatus, ['skipped', 'failed'], true)) {
                    $file['status'] = $dlStatus;
                    $file['error'] = $downloadInfo['error'] ?? null;

                    continue;
                }

                $artifact = $artifactsByDriveId[$id] ?? null;
                if ($artifact === null) {
                    $file['status'] = 'failed';
                    $file['error'] = 'not_ingested';

                    continue;
                }

                $file['artifact_id'] = $artifact->id;
                $file['error'] = null;
                if ($artifact->parse_status === ClioCampaignArtifact::PARSE_PENDING) {
                    $file['status'] = 'ingested';
                } elseif (in_array($artifact->parse_status, [ClioCampaignArtifact::PARSE_OK, ClioCampaignArtifact::PARSE_WARNING], true)) {
                    $file['status'] = 'parsed';
                } elseif ($artifact->parse_status === ClioCampaignArtifact::PARSE_FAILED) {
                    $file['status'] = 'failed';
                    $file['error'] = 'parse_failed';
                } else {
                    $file['status'] = 'ingested';
                }

                // Duplicates still return existing artifact — mark duplicate if already had sha
                if (($result['duplicates'] ?? 0) > 0 && ($file['status'] ?? '') === 'parsed') {
                    // keep parsed
                }
            }
            unset($file);

            // Mark any remaining pending in this batch that weren't in download map
            foreach ($catalog['files'] as &$file) {
                if ((int) ($file['batch'] ?? 1) !== $batchNo) {
                    continue;
                }
                if (($file['status'] ?? '') === 'pending') {
                    $file['status'] = 'failed';
                    $file['error'] = $file['error'] ?? 'not_in_download';
                }
            }
            unset($file);

            $catalog['counts'] = $this->countCatalogStatuses($catalog['files']);
            $catalog['next_batch'] = $this->nextPendingBatch($catalog['files']);
            $pending = (int) ($catalog['counts']['pending'] ?? 0) + (int) ($catalog['counts']['failed'] ?? 0);
            // failed can be retried — count pending+failed as remaining work for "complete"?
            $stillPending = (int) ($catalog['counts']['pending'] ?? 0) + (int) ($catalog['counts']['failed'] ?? 0);
            $catalog['last_batch_at'] = Carbon::now()->toIso8601String();
            $catalog['last_batch'] = $batchNo;
            $complete = $stillPending === 0;

            $meta = is_array($campaign->meta) ? $campaign->meta : [];
            $meta['drive_folder_url'] = $catalog['folder_url'] ?? $this->resolveDriveUrl($campaign);
            $meta['drive_folder_id'] = $catalog['resource_id'] ?? ($meta['drive_folder_id'] ?? null);
            $meta['drive_resource_type'] = $catalog['resource_type'] ?? ($meta['drive_resource_type'] ?? null);
            $meta['drive_last_import_at'] = Carbon::now()->toIso8601String();
            $meta['drive_last_import'] = [
                'batch' => $batchNo,
                'downloaded' => $download['downloaded'],
                'skipped' => $download['skipped'],
                'bytes' => $download['bytes'],
                'stored' => $result['stored'],
                'parsed' => $parsed,
            ];
            $meta['drive_catalog'] = $catalog;

            $campaign->update([
                'source' => 'drive_upload',
                'meta' => $meta,
            ]);

            if ($campaign->city && blank($campaign->city->clio_drive_url) && filled($meta['drive_folder_url'] ?? null)) {
                $campaign->city->update(['clio_drive_url' => $meta['drive_folder_url']]);
            }

            $totalBatches = (int) ($catalog['total_batches'] ?? 1);
            $message = __('Lote :b/:t concluído — :dl descarregado(s), :stored ingerido(s), :dup duplicado(s), :parsed interpretado(s).', [
                'b' => $batchNo,
                't' => $totalBatches,
                'dl' => $download['downloaded'],
                'stored' => $result['stored'],
                'dup' => $result['duplicates'],
                'parsed' => $parsed,
            ]);
            if ($complete) {
                $message .= ' '.__('Catálogo Drive completo — pode analisar a coleta.');
            } elseif ($stillPending > 0) {
                $message .= ' '.__('Restam :n ficheiro(s). Clique em «Continuar lote» para seguir.', ['n' => $stillPending]);
            }

            return [
                'stored' => $result['stored'],
                'expanded' => $result['expanded'],
                'duplicates' => $result['duplicates'],
                'ignored' => $result['ignored'],
                'parsed' => $parsed,
                'downloaded' => $download['downloaded'],
                'skipped' => $download['skipped'],
                'batch' => $batchNo,
                'total_batches' => $totalBatches,
                'pending' => $stillPending,
                'complete' => $complete,
                'message' => $message,
                'catalog' => $catalog,
            ];
        } finally {
            $this->drive->deleteDirectory($download['dir']);
        }
    }

    /**
     * @return array{stored: int, expanded: int, duplicates: int, ignored: int, parsed: int, downloaded: int, skipped: int, message: string}
     */
    public function import(ClioCampaign $campaign, ?string $url = null, bool $parse = true): array
    {
        $result = $this->importNextBatch($campaign, $url, $parse);

        return [
            'stored' => $result['stored'],
            'expanded' => $result['expanded'],
            'duplicates' => $result['duplicates'],
            'ignored' => $result['ignored'],
            'parsed' => $result['parsed'],
            'downloaded' => $result['downloaded'],
            'skipped' => $result['skipped'],
            'message' => $result['message'],
            'batch' => $result['batch'],
            'total_batches' => $result['total_batches'],
            'pending' => $result['pending'],
            'complete' => $result['complete'],
            'catalog' => $result['catalog'],
        ];
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
                'catalog' => [],
            ];
        }

        $result = $this->drive->verify($url);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'summary' => $result['summary'],
            'warnings' => $result['warnings'],
            'files_preview' => array_slice($result['files'], 0, 40),
            'catalog' => [],
            'resource_type' => $result['resource_type'],
            'resource_id' => $result['resource_id'],
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readCatalog(ClioCampaign $campaign): array
    {
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $catalog = $meta['drive_catalog'] ?? [];

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @return array{pending: int, ingested: int, parsed: int, skipped: int, failed: int, duplicate: int, total: int}
     */
    public function countCatalogStatuses(array $files): array
    {
        $counts = [
            'pending' => 0,
            'ingested' => 0,
            'parsed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'duplicate' => 0,
            'total' => count($files),
        ];
        foreach ($files as $file) {
            $status = (string) ($file['status'] ?? 'pending');
            if (! array_key_exists($status, $counts)) {
                $status = 'pending';
            }
            $counts[$status]++;
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $files
     */
    public function nextPendingBatch(array $files): int
    {
        $min = null;
        foreach ($files as $file) {
            if (! in_array(($file['status'] ?? ''), ['pending', 'failed'], true)) {
                continue;
            }
            $batch = (int) ($file['batch'] ?? 1);
            $min = $min === null ? $batch : min($min, $batch);
        }

        return $min ?? 0;
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / (1024 * 1024), 2, ',', '.').' MB';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('Aguardando'),
            'ingested' => __('Ingerido'),
            'parsed' => __('Interpretado'),
            'skipped' => __('Ignorado'),
            'failed' => __('Falhou'),
            'duplicate' => __('Duplicado'),
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $verifyResult
     */
    private function persistCatalog(ClioCampaign $campaign, array $catalog, string $url, array $verifyResult): void
    {
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $meta['drive_folder_url'] = $url;
        if (! empty($verifyResult['resource_id'])) {
            $meta['drive_folder_id'] = $verifyResult['resource_id'];
            $meta['drive_resource_type'] = $verifyResult['resource_type'] ?? null;
        }
        $meta['drive_catalog'] = $catalog;
        $meta['drive_cataloged_at'] = $catalog['cataloged_at'] ?? Carbon::now()->toIso8601String();
        $campaign->update(['meta' => $meta]);

        if ($campaign->city && blank($campaign->city->clio_drive_url)) {
            $campaign->city->update(['clio_drive_url' => $url]);
        }
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function writeCatalogOnly(ClioCampaign $campaign, array $catalog): void
    {
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $meta['drive_catalog'] = $catalog;
        $campaign->update(['meta' => $meta]);
    }
}
