<?php

namespace App\Console\Commands;

use App\Support\Filesystem\AppTemp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Remove ficheiros temporários / processados descartáveis no volume da app.
 */
class TmpPurgeCommand extends Command
{
    protected $signature = 'tmp:purge
                            {--hours= : Idade mínima em horas para storage/app/tmp (default: config)}
                            {--dry-run : Só listar o que seria apagado}
                            {--only=tmp : Alvos separados por vírgula: tmp,extra,all}';

    protected $description = 'Apaga ficheiros temporários da app (storage/app/tmp e extracts/órfãos configurados)';

    public function handle(): int
    {
        $hours = max(1, (int) ($this->option('hours') ?: config('tmp.retention_hours', 24)));
        $dry = (bool) $this->option('dry-run');
        $only = strtolower(trim((string) $this->option('only')));
        if ($only === '' || $only === 'all') {
            $targets = ['tmp', 'extra'];
        } else {
            $targets = array_values(array_filter(array_map('trim', explode(',', $only))));
        }

        $deletedFiles = 0;
        $deletedDirs = 0;
        $bytes = 0;

        if (in_array('tmp', $targets, true)) {
            $root = AppTemp::ensure();
            $this->line(__('Temp app: :path (retenção :h h)', ['path' => $root, 'h' => $hours]));
            [$f, $d, $b] = $this->purgeTree($root, $hours, $dry, null);
            $deletedFiles += $f;
            $deletedDirs += $d;
            $bytes += $b;
        }

        if (in_array('extra', $targets, true)) {
            $extras = config('tmp.extra_targets', []);
            if (! is_array($extras)) {
                $extras = [];
            }
            foreach ($extras as $target) {
                if (! is_array($target)) {
                    continue;
                }
                $relative = trim((string) ($target['relative'] ?? ''), '/');
                if ($relative === '') {
                    continue;
                }
                $targetHours = max(1, (int) ($target['hours'] ?? $hours));
                $glob = isset($target['glob']) ? (string) $target['glob'] : null;
                $path = storage_path('app/'.$relative);
                if (! is_dir($path)) {
                    continue;
                }
                $this->line(__('Extra: :path (retenção :h h:glob)', [
                    'path' => $path,
                    'h' => $targetHours,
                    'glob' => $glob ? ' · '.$glob : '',
                ]));
                [$f, $d, $b] = $this->purgeTree($path, $targetHours, $dry, $glob);
                $deletedFiles += $f;
                $deletedDirs += $d;
                $bytes += $b;
            }
        }

        $mb = round($bytes / 1048576, 2);
        $this->info($dry
            ? __('Dry-run: :f ficheiro(s), :d pasta(s), ~:m MB seriam removidos.', [
                'f' => $deletedFiles,
                'd' => $deletedDirs,
                'm' => $mb,
            ])
            : __('Removidos :f ficheiro(s), :d pasta(s), ~:m MB.', [
                'f' => $deletedFiles,
                'd' => $deletedDirs,
                'm' => $mb,
            ]));

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: int} files, dirs, bytes
     */
    private function purgeTree(string $root, int $hours, bool $dry, ?string $glob): array
    {
        $cutoff = time() - ($hours * 3600);
        $files = 0;
        $dirs = 0;
        $bytes = 0;

        if ($glob !== null && $glob !== '') {
            foreach ($this->matchChildren($root, $glob) as $child) {
                if (! file_exists($child)) {
                    continue;
                }
                $mtime = (int) @filemtime($child);
                if ($mtime > 0 && $mtime > $cutoff) {
                    continue;
                }
                $isDir = is_dir($child);
                $size = $isDir ? $this->dirSize($child) : (int) @filesize($child);
                if ($dry) {
                    $this->line('  · '.$child.($isDir ? '/' : ''));
                } elseif ($isDir) {
                    File::deleteDirectory($child);
                } else {
                    @unlink($child);
                }
                if ($isDir) {
                    $dirs++;
                } else {
                    $files++;
                }
                $bytes += max(0, $size);
            }

            return [$files, $dirs, $bytes];
        }

        if (! is_dir($root)) {
            return [0, 0, 0];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            $path = $info->getPathname();
            if (basename($path) === '.gitignore') {
                continue;
            }
            $mtime = (int) $info->getMTime();
            if ($mtime > $cutoff) {
                continue;
            }
            if ($info->isFile()) {
                $bytes += (int) $info->getSize();
                if ($dry) {
                    $this->line('  · '.$path);
                } else {
                    @unlink($path);
                }
                $files++;
            } elseif ($info->isDir() && $this->dirIsEmpty($path)) {
                if ($dry) {
                    $this->line('  · '.$path.'/');
                } else {
                    @rmdir($path);
                }
                $dirs++;
            }
        }

        return [$files, $dirs, $bytes];
    }

    /**
     * @return list<string>
     */
    private function matchChildren(string $root, string $glob): array
    {
        $patterns = str_contains($glob, '{')
            ? $this->expandBraceGlob($glob)
            : [$glob];

        $out = [];
        foreach ($patterns as $pattern) {
            $matches = glob($root.DIRECTORY_SEPARATOR.$pattern, GLOB_NOSORT) ?: [];
            foreach ($matches as $match) {
                $out[] = $match;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function expandBraceGlob(string $glob): array
    {
        if (preg_match('/^\{([^}]+)\}$/', $glob, $m) !== 1) {
            return [$glob];
        }

        return array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    }

    private function dirIsEmpty(string $path): bool
    {
        $items = @scandir($path);
        if (! is_array($items)) {
            return false;
        }

        return count($items) <= 2;
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        if (! is_dir($path)) {
            return (int) @filesize($path);
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $size;
    }
}
