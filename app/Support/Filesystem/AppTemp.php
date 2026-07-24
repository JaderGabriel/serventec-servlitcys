<?php

namespace App\Support\Filesystem;

use RuntimeException;

/**
 * Temp da aplicação (volume da VPS), não o /tmp do SO.
 *
 * Default: storage/app/tmp — override com APP_TEMP_PATH.
 */
final class AppTemp
{
    public static function root(): string
    {
        $configured = trim((string) config('tmp.path', ''));
        if ($configured !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), DIRECTORY_SEPARATOR);
        }

        return storage_path('app'.DIRECTORY_SEPARATOR.'tmp');
    }

    /**
     * Garante a pasta (e opcionalmente um namespace) e devolve o caminho absoluto.
     */
    public static function ensure(?string $namespace = null): string
    {
        $dir = self::root();
        $ns = $namespace !== null ? trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $namespace), DIRECTORY_SEPARATOR) : '';
        if ($ns !== '') {
            $dir .= DIRECTORY_SEPARATOR.$ns;
        }

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException(__('Não foi possível criar a pasta temporária da aplicação (:path).', [
                'path' => $dir,
            ]));
        }

        return $dir;
    }

    /**
     * Equivalente a tempnam(), mas no temp da app (namespace opcional).
     */
    public static function tempnam(string $prefix, ?string $namespace = null): string
    {
        $dir = self::ensure($namespace);
        $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix) ?: 'tmp_';
        $path = tempnam($dir, $safePrefix);
        if ($path === false) {
            throw new RuntimeException(__('Não foi possível criar arquivo temporário em :path.', [
                'path' => $dir,
            ]));
        }

        return $path;
    }

    /**
     * Cria um directório único sob o namespace (ex.: downloads Drive).
     */
    public static function directory(string $prefix, ?string $namespace = null, int $mode = 0755): string
    {
        $parent = self::ensure($namespace);
        $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix) ?: 'dir_';
        $dir = $parent.DIRECTORY_SEPARATOR.$safePrefix.bin2hex(random_bytes(6));
        if (! mkdir($dir, $mode, true) && ! is_dir($dir)) {
            throw new RuntimeException(__('Não foi possível criar pasta temporária em :path.', [
                'path' => $parent,
            ]));
        }

        return $dir;
    }

    /**
     * Caminho de ficheiro único (sem criar o ficheiro) — útil para XLSX de export.
     */
    public static function path(string $filename, ?string $namespace = null): string
    {
        $dir = self::ensure($namespace);
        $base = basename(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename));
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'file_'.bin2hex(random_bytes(4));
        }

        return $dir.DIRECTORY_SEPARATOR.$base;
    }
}
