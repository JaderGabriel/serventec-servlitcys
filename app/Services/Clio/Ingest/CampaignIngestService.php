<?php

namespace App\Services\Clio\Ingest;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Pipeline S2: classificar, deduplicar e gravar artefatos (interpretação fica para S3).
 */
final class CampaignIngestService
{
    public function __construct(
        private readonly ArtifactClassifier $classifier,
        private readonly ZipExpander $zipExpander,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string|null>  $relativePaths
     * @return array{stored: int, ignored: int, duplicates: int, expanded: int, zip_ids: list<int>, artifacts: list<ClioCampaignArtifact>}
     */
    public function storeUploads(
        ClioCampaign $campaign,
        array $files,
        array $relativePaths = [],
        bool $expandZips = true,
    ): array {
        $stored = 0;
        $ignored = 0;
        $duplicates = 0;
        $expanded = 0;
        $artifacts = [];
        $zipArtifactIds = [];

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $relative = $relativePaths[$index] ?? $original;
            $absolute = $file->getRealPath() ?: $file->getPathname();

            $result = $this->ingestAbsoluteFile($campaign, $absolute, $original, (string) $relative, null);
            $ignored += $result['ignored'];
            $duplicates += $result['duplicates'];
            $stored += $result['stored'];
            foreach ($result['artifacts'] as $artifact) {
                $artifacts[] = $artifact;
                if ($artifact->kind === 'pacote_zip') {
                    $zipArtifactIds[] = $artifact->id;
                }
            }
        }

        if ($expandZips && $zipArtifactIds !== []) {
            $expand = $this->expandZipArtifacts($campaign, $zipArtifactIds);
            $expanded = $expand['stored'];
            $ignored += $expand['ignored'];
            $duplicates += $expand['duplicates'];
            foreach ($expand['artifacts'] as $artifact) {
                $artifacts[] = $artifact;
            }
        }

        $this->markIngesting($campaign, $stored + $expanded);

        return [
            'stored' => $stored + $expanded,
            'ignored' => $ignored,
            'duplicates' => $duplicates,
            'expanded' => $expanded,
            'zip_ids' => $zipArtifactIds,
            'artifacts' => $artifacts,
        ];
    }

    /**
     * Ingere arquivo único, pasta ou ZIP a partir do filesystem (CLI U5).
     *
     * @param  array<string, array{id?: string}>  $driveMetaByRelative
     * @return array{stored: int, ignored: int, duplicates: int, expanded: int, artifacts: list<ClioCampaignArtifact>}
     */
    public function ingestFromPath(ClioCampaign $campaign, string $path, array $driveMetaByRelative = []): array
    {
        $path = rtrim($path);
        if (! file_exists($path)) {
            throw new \InvalidArgumentException(__('Caminho Clio inexistente: :path', ['path' => $path]));
        }

        if (is_file($path)) {
            $base = basename($path);
            $result = $this->ingestAbsoluteFile(
                $campaign,
                $path,
                $base,
                $base,
                null,
                $driveMetaByRelative[$base] ?? [],
            );
            $expanded = 0;
            $artifacts = $result['artifacts'];

            foreach ($result['artifacts'] as $artifact) {
                if ($artifact->kind === 'pacote_zip') {
                    $expand = $this->expandZipArtifacts($campaign, [$artifact->id]);
                    $expanded = $expand['stored'];
                    $result['ignored'] += $expand['ignored'];
                    $result['duplicates'] += $expand['duplicates'];
                    foreach ($expand['artifacts'] as $child) {
                        $artifacts[] = $child;
                    }
                }
            }

            $this->markIngesting($campaign, $result['stored'] + $expanded);

            return [
                'stored' => $result['stored'] + $expanded,
                'ignored' => $result['ignored'],
                'duplicates' => $result['duplicates'],
                'expanded' => $expanded,
                'artifacts' => $artifacts,
            ];
        }

        $stored = 0;
        $ignored = 0;
        $duplicates = 0;
        $artifacts = [];
        $zipIds = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($absolute, rtrim($path, DIRECTORY_SEPARATOR))), '/');
            $result = $this->ingestAbsoluteFile(
                $campaign,
                $absolute,
                basename($absolute),
                $relative,
                null,
                $driveMetaByRelative[$relative] ?? [],
            );
            $stored += $result['stored'];
            $ignored += $result['ignored'];
            $duplicates += $result['duplicates'];
            foreach ($result['artifacts'] as $artifact) {
                $artifacts[] = $artifact;
                if ($artifact->kind === 'pacote_zip') {
                    $zipIds[] = $artifact->id;
                }
            }
        }

        $expanded = 0;
        if ($zipIds !== []) {
            $expand = $this->expandZipArtifacts($campaign, $zipIds);
            $expanded = $expand['stored'];
            $ignored += $expand['ignored'];
            $duplicates += $expand['duplicates'];
            foreach ($expand['artifacts'] as $artifact) {
                $artifacts[] = $artifact;
            }
        }

        $this->markIngesting($campaign, $stored + $expanded);

        return [
            'stored' => $stored + $expanded,
            'ignored' => $ignored,
            'duplicates' => $duplicates,
            'expanded' => $expanded,
            'artifacts' => $artifacts,
        ];
    }

    /**
     * Expande ZIPs pendentes da campanha (job / CLI sem --path).
     *
     * @param  list<int>|null  $artifactIds
     * @return array{stored: int, ignored: int, duplicates: int, expanded: int, artifacts: list<ClioCampaignArtifact>}
     */
    public function expandPendingZips(ClioCampaign $campaign, ?array $artifactIds = null): array
    {
        $query = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->where('kind', 'pacote_zip');

        if ($artifactIds !== null) {
            $query->whereIn('id', $artifactIds);
        }

        $ids = [];
        foreach ($query->get() as $zip) {
            $meta = is_array($zip->parse_meta) ? $zip->parse_meta : [];
            if (! empty($meta['expanded_at'])) {
                continue;
            }
            $ids[] = $zip->id;
        }

        if ($ids === []) {
            return [
                'stored' => 0,
                'ignored' => 0,
                'duplicates' => 0,
                'expanded' => 0,
                'artifacts' => [],
            ];
        }

        $result = $this->expandZipArtifacts($campaign, $ids);
        $this->markIngesting($campaign, $result['stored']);

        return [
            'stored' => $result['stored'],
            'ignored' => $result['ignored'],
            'duplicates' => $result['duplicates'],
            'expanded' => $result['stored'],
            'artifacts' => $result['artifacts'],
        ];
    }

    /**
     * @param  list<int>  $zipArtifactIds
     * @return array{stored: int, ignored: int, duplicates: int, artifacts: list<ClioCampaignArtifact>}
     */
    public function expandZipArtifacts(ClioCampaign $campaign, array $zipArtifactIds): array
    {
        $disk = (string) config('clio.disk', 'local');
        $stored = 0;
        $ignored = 0;
        $duplicates = 0;
        $artifacts = [];

        foreach ($zipArtifactIds as $zipId) {
            $zipArtifact = ClioCampaignArtifact::query()
                ->where('campaign_id', $campaign->id)
                ->whereKey($zipId)
                ->first();

            if ($zipArtifact === null || $zipArtifact->kind !== 'pacote_zip') {
                continue;
            }

            $meta = is_array($zipArtifact->parse_meta) ? $zipArtifact->parse_meta : [];
            if (! empty($meta['expanded_at'])) {
                continue;
            }

            $zipAbsolute = Storage::disk($disk)->path($zipArtifact->storage_path);
            $extractRoot = Storage::disk($disk)->path(
                trim((string) config('clio.storage_root', 'clio'), '/').'/'.$campaign->uuid.'/extracted/'.$zipArtifact->id
            );

            File::ensureDirectoryExists($extractRoot);
            $extracted = $this->zipExpander->expand($zipAbsolute, $extractRoot);
            $childCount = 0;

            foreach ($extracted as $entry) {
                $result = $this->ingestAbsoluteFile(
                    $campaign,
                    $entry['absolute_path'],
                    basename($entry['relative_path']),
                    $entry['relative_path'],
                    $zipArtifact->id,
                );
                $stored += $result['stored'];
                $ignored += $result['ignored'];
                $duplicates += $result['duplicates'];
                $childCount += $result['stored'];
                foreach ($result['artifacts'] as $artifact) {
                    $artifacts[] = $artifact;
                }
            }

            $zipArtifact->update([
                'parse_meta' => array_merge($meta, [
                    'expanded_at' => now()->toIso8601String(),
                    'extracted_files' => $childCount,
                ]),
            ]);
        }

        return [
            'stored' => $stored,
            'ignored' => $ignored,
            'duplicates' => $duplicates,
            'artifacts' => $artifacts,
        ];
    }

    /**
     * @param  array{id?: string}  $driveMeta
     * @return array{stored: int, ignored: int, duplicates: int, artifacts: list<ClioCampaignArtifact>}
     */
    private function ingestAbsoluteFile(
        ClioCampaign $campaign,
        string $absolutePath,
        string $originalName,
        string $relativePath,
        ?int $parentZipId,
        array $driveMeta = [],
    ): array {
        $classified = $this->classifier->classify($originalName, $relativePath);

        if ($classified['ignored']) {
            return ['stored' => 0, 'ignored' => 1, 'duplicates' => 0, 'artifacts' => []];
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['stored' => 0, 'ignored' => 1, 'duplicates' => 0, 'artifacts' => []];
        }

        $hash = hash_file('sha256', $absolutePath);
        $existing = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->where('sha256', $hash)
            ->first();

        if ($existing !== null) {
            if (filled($driveMeta['id'] ?? null)) {
                $meta = is_array($existing->parse_meta) ? $existing->parse_meta : [];
                if (($meta['drive_file_id'] ?? null) !== $driveMeta['id']) {
                    $meta['drive_file_id'] = $driveMeta['id'];
                    $existing->update(['parse_meta' => $meta]);
                }
            }

            return ['stored' => 0, 'ignored' => 0, 'duplicates' => 1, 'artifacts' => [$existing]];
        }

        $schoolId = $this->resolveSchoolId($campaign, $classified['inep_code'], $relativePath);

        $disk = (string) config('clio.disk', 'local');
        $root = trim((string) config('clio.storage_root', 'clio'), '/');
        $dir = $root.'/'.$campaign->uuid.'/artifacts';
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'arquivo';
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $filename = $safeName.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $storagePath = $dir.'/'.$filename;

        Storage::disk($disk)->put($storagePath, File::get($absolutePath));

        $meta = [
            'classified_at' => now()->toIso8601String(),
            'relative_path' => $relativePath,
        ];
        if ($parentZipId !== null) {
            $meta['parent_zip_id'] = $parentZipId;
        }
        if (filled($driveMeta['id'] ?? null)) {
            $meta['drive_file_id'] = $driveMeta['id'];
        }

        $artifact = ClioCampaignArtifact::query()->create([
            'campaign_id' => $campaign->id,
            'school_id' => $schoolId,
            'kind' => $classified['kind'],
            'original_name' => $originalName,
            'storage_path' => $storagePath,
            'sha256' => $hash,
            'size_bytes' => (int) filesize($absolutePath),
            'parse_status' => ClioCampaignArtifact::PARSE_PENDING,
            'parse_meta' => $meta,
        ]);

        return [
            'stored' => 1,
            'ignored' => 0,
            'duplicates' => 0,
            'artifacts' => [$artifact],
        ];
    }

    private function resolveSchoolId(ClioCampaign $campaign, ?string $inepCode, string $relativePath): ?int
    {
        if (blank($inepCode)) {
            return null;
        }

        $label = $this->classifier->schoolLabelFromPath($relativePath);
        $name = $label['name'] ?? __('Escola :inep', ['inep' => $inepCode]);

        $school = ClioCampaignSchool::query()->firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'inep_code' => $inepCode,
            ],
            [
                'name' => $name,
            ],
        );

        if ($school->name === __('Escola :inep', ['inep' => $inepCode]) && filled($label['name'] ?? null)) {
            $school->update(['name' => $label['name']]);
        }

        return $school->id;
    }

    private function markIngesting(ClioCampaign $campaign, int $stored): void
    {
        if ($stored <= 0) {
            return;
        }

        if (in_array($campaign->status, [ClioCampaign::STATUS_DRAFT, ClioCampaign::STATUS_INGESTING], true)) {
            $campaign->update([
                'status' => ClioCampaign::STATUS_INGESTING,
                'source' => $campaign->source ?: 'manual_upload',
            ]);
        }
    }
}
