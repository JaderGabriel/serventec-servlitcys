<?php

namespace App\Support\Filesystem;

/**
 * Resolve ficheiros legíveis apenas dentro de pastas permitidas (evita path traversal em CLI/admin).
 */
final class ContainedPathResolver
{
    /**
     * @param  list<string>  $allowedRoots  Pastas absolutas (ex.: storage_path('app'))
     */
    public static function resolveReadableFile(string $path, array $allowedRoots): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $roots = [];
        foreach ($allowedRoots as $root) {
            $realRoot = realpath($root);
            if ($realRoot !== false && is_dir($realRoot)) {
                $roots[] = $realRoot;
            }
        }

        if ($roots === []) {
            return null;
        }

        $candidates = str_starts_with($path, '/')
            ? [$path]
            : array_map(static fn (string $root): string => $root.DIRECTORY_SEPARATOR.ltrim($path, '/'), $roots);

        foreach ($candidates as $candidate) {
            $realFile = realpath($candidate);
            if ($realFile === false || ! is_file($realFile) || ! is_readable($realFile)) {
                continue;
            }

            foreach ($roots as $root) {
                if ($realFile === $root || str_starts_with($realFile, $root.DIRECTORY_SEPARATOR)) {
                    return $realFile;
                }
            }
        }

        return null;
    }
}
