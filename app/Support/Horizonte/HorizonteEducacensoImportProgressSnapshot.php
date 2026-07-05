<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Snapshot em disco do checkpoint Educacenso (sobrevive a cache:clear no deploy). */
final class HorizonteEducacensoImportProgressSnapshot
{
    private const VERSION = 1;

    /**
     * @return array{
     *     steps_done: list<string>,
     *     last_failed_step: ?string,
     *     source: ?string,
     *     updated_at: ?string
     * }|null
     */
    public static function read(): ?array
    {
        $path = self::relativePath();
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        try {
            $raw = Storage::disk('local')->get($path);
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::debug('horizonte.educacenso_progress_snapshot_read_failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $steps = is_array($payload['steps_done'] ?? null) ? $payload['steps_done'] : [];
        $stepsDone = array_values(array_unique(array_filter(array_map('strval', $steps))));
        $failed = $payload['last_failed_step'] ?? null;
        $lastFailed = is_string($failed) && $failed !== '' ? $failed : null;

        return [
            'steps_done' => $stepsDone,
            'last_failed_step' => $lastFailed,
            'source' => is_string($payload['source'] ?? null) ? $payload['source'] : null,
            'updated_at' => is_string($payload['updated_at'] ?? null) ? $payload['updated_at'] : null,
        ];
    }

    /**
     * @param  list<string>  $stepsDone
     */
    public static function write(array $stepsDone, ?string $lastFailedStep = null, string $source = 'runtime'): void
    {
        $path = self::relativePath();
        if ($path === '') {
            return;
        }

        $payload = [
            'version' => self::VERSION,
            'updated_at' => now()->toIso8601String(),
            'window_years' => self::documentedWindowYears(),
            'steps_done' => array_values(array_unique(array_filter(array_map('strval', $stepsDone)))),
            'last_failed_step' => $lastFailedStep,
            'source' => $source,
        ];

        try {
            Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            Log::warning('horizonte.educacenso_progress_snapshot_write_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function delete(): void
    {
        $path = self::relativePath();
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return;
        }

        try {
            Storage::disk('local')->delete($path);
        } catch (\Throwable $e) {
            Log::debug('horizonte.educacenso_progress_snapshot_delete_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function relativePath(): string
    {
        return trim((string) config(
            'horizonte.fortnightly_feed.educacenso_progress_snapshot_path',
            'horizonte/educacenso_import_progress.json',
        ));
    }

    /**
     * @return list<int>
     */
    private static function documentedWindowYears(): array
    {
        $anchor = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $count = max(2, min(10, (int) config('horizonte.enrollment_series.years', 5)));
        $years = [];
        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $years[] = $anchor - $offset;
        }

        return $years;
    }
}
