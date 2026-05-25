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
        $resolved = DocumentationCatalog::resolveReadablePath($path);
        if ($resolved === null) {
            throw new RuntimeException(__('Documento não permitido.'));
        }

        $absolute = base_path($resolved);
        if (! is_readable($absolute)) {
            throw new RuntimeException(__('Ficheiro não encontrado no servidor.'));
        }

        $markdown = File::get($absolute);
        $item = DocumentationCatalog::findItemByPath($resolved);

        return [
            'path' => $resolved,
            'label' => $item['label'] ?? $resolved,
            'markdown' => $markdown,
            'modified_at' => @filemtime($absolute) ?: null,
        ];
    }
}
