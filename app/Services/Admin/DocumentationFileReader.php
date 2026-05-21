<?php

namespace App\Services\Admin;

use App\Support\Admin\DocumentationCatalog;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DocumentationFileReader
{
    /**
     * @return array{path: string, label: string, markdown: string, modified_at: ?int}
     */
    public function read(string $path): array
    {
        if (! DocumentationCatalog::isAllowedPath($path)) {
            throw new RuntimeException(__('Documento não permitido.'));
        }

        $absolute = $this->resolveAbsolutePath($path);
        if ($absolute === null || ! is_readable($absolute)) {
            throw new RuntimeException(__('Ficheiro não encontrado no servidor.'));
        }

        $markdown = File::get($absolute);
        $item = DocumentationCatalog::findItemByPath($path);

        return [
            'path' => $path,
            'label' => $item['label'] ?? $path,
            'markdown' => $markdown,
            'modified_at' => @filemtime($absolute) ?: null,
        ];
    }

    private function resolveAbsolutePath(string $path): ?string
    {
        $root = realpath(base_path());
        if ($root === false) {
            return null;
        }

        $absolute = realpath(base_path($path));
        if ($absolute === false || ! is_file($absolute)) {
            return null;
        }

        if (! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR) && $absolute !== $root) {
            return null;
        }

        if (! str_ends_with(strtolower($absolute), '.md')) {
            return null;
        }

        $docsDir = realpath(base_path('docs'));
        $readme = realpath(base_path('README.md'));

        if ($docsDir !== false && str_starts_with($absolute, $docsDir.DIRECTORY_SEPARATOR)) {
            return $absolute;
        }

        if ($readme !== false && $absolute === $readme) {
            return $absolute;
        }

        return null;
    }
}
