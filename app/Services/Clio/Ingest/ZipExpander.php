<?php

namespace App\Services\Clio\Ingest;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Expande ZIP municipal da coleta Educacenso (U1), com protecção zip-slip.
 */
final class ZipExpander
{
    public function __construct(
        private readonly ArtifactClassifier $classifier,
    ) {}

    /**
     * @return list<array{relative_path: string, absolute_path: string, size: int}>
     */
    public function expand(string $zipAbsolutePath, string $destDirectory): array
    {
        if (! is_file($zipAbsolutePath) || ! is_readable($zipAbsolutePath)) {
            throw new RuntimeException(__('ZIP Clio não legível: :path', ['path' => $zipAbsolutePath]));
        }

        File::ensureDirectoryExists($destDirectory);

        $zip = new ZipArchive;
        $opened = $zip->open($zipAbsolutePath);
        if ($opened !== true) {
            throw new RuntimeException(__('Não foi possível abrir o ZIP Clio (código :c).', ['c' => (string) $opened]));
        }

        $destReal = realpath($destDirectory);
        if ($destReal === false) {
            $zip->close();
            throw new RuntimeException(__('Destino de extracção Clio inválido.'));
        }

        $files = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }

                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                $base = basename($name);
                if ($this->classifier->classify($base, $name)['ignored']) {
                    continue;
                }

                if (str_contains($name, '..')) {
                    continue;
                }

                $target = $destReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                $targetDir = dirname($target);
                File::ensureDirectoryExists($targetDir);

                $targetRealDir = realpath($targetDir);
                if ($targetRealDir === false || ! str_starts_with($targetRealDir, $destReal)) {
                    continue;
                }

                if ($zip->extractTo($destReal, $name) !== true) {
                    continue;
                }

                $absolute = $destReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (! is_file($absolute)) {
                    continue;
                }

                $files[] = [
                    'relative_path' => $name,
                    'absolute_path' => $absolute,
                    'size' => (int) filesize($absolute),
                ];
            }
        } finally {
            $zip->close();
        }

        return $files;
    }
}
