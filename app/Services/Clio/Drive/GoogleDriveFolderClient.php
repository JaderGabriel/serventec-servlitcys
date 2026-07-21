<?php

namespace App\Services\Clio\Drive;

use App\Services\Clio\Ingest\ArtifactClassifier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lista e descarrega pastas/ficheiros Google Drive partilhados (anyone with link)
 * via Drive API v3 + API key.
 */
final class GoogleDriveFolderClient
{
    public function __construct(
        private readonly ArtifactClassifier $classifier,
    ) {}

    public function apiKeyConfigured(): bool
    {
        return filled($this->apiKey());
    }

    /**
     * @return array{type: 'folder'|'file', id: string}|null
     */
    public function parseResource(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('~drive\.google\.com/drive/(?:u/\d+/)?folders/([a-zA-Z0-9_-]+)~', $url, $m)) {
            return ['type' => 'folder', 'id' => $m[1]];
        }

        if (preg_match('~drive\.google\.com/file/d/([a-zA-Z0-9_-]+)~', $url, $m)) {
            return ['type' => 'file', 'id' => $m[1]];
        }

        if (preg_match('~[?&]id=([a-zA-Z0-9_-]+)~', $url, $m)) {
            // Ambíguo: tratar como ficheiro se open?id=; pastas usam /folders/
            return ['type' => 'file', 'id' => $m[1]];
        }

        if (preg_match('~^[a-zA-Z0-9_-]{20,}$~', $url)) {
            return ['type' => 'folder', 'id' => $url];
        }

        return null;
    }

    /**
     * @return array{
     *   ok: bool,
     *   resource_type: ?string,
     *   resource_id: ?string,
     *   message: string,
     *   files: list<array{id: string, name: string, path: string, mime: string, size: ?int, kind: string}>,
     *   summary: array{total: int, by_kind: array<string, int>, folders: int, ignored: int},
     *   warnings: list<string>
     * }
     */
    public function verify(string $url): array
    {
        $empty = [
            'ok' => false,
            'resource_type' => null,
            'resource_id' => null,
            'message' => '',
            'files' => [],
            'summary' => ['total' => 0, 'by_kind' => [], 'folders' => 0, 'ignored' => 0],
            'warnings' => [],
        ];

        $resource = $this->parseResource($url);
        if ($resource === null) {
            return [...$empty, 'message' => __('URL do Google Drive inválida. Use o link da pasta (…/folders/…) ou do ficheiro (…/file/d/…).')];
        }

        if (! $this->apiKeyConfigured()) {
            return [
                ...$empty,
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'message' => __('Configure CLIO_DRIVE_API_KEY para verificar e importar pastas do Drive.'),
                'warnings' => [__('A URL foi reconhecida (:type :id), mas a API key não está definida.', [
                    'type' => $resource['type'],
                    'id' => $resource['id'],
                ])],
            ];
        }

        try {
            if ($resource['type'] === 'file') {
                $meta = $this->getFileMeta($resource['id']);
                $name = (string) ($meta['name'] ?? 'arquivo');
                $kindInfo = $this->classifier->classify($name);
                $file = [
                    'id' => $resource['id'],
                    'name' => $name,
                    'path' => $name,
                    'mime' => (string) ($meta['mimeType'] ?? ''),
                    'size' => isset($meta['size']) ? (int) $meta['size'] : null,
                    'kind' => (string) ($kindInfo['kind'] ?? 'unknown'),
                ];
                $byKind = [$file['kind'] => 1];

                return [
                    'ok' => true,
                    'resource_type' => 'file',
                    'resource_id' => $resource['id'],
                    'message' => __('Ficheiro Drive acessível.'),
                    'files' => [$file],
                    'summary' => [
                        'total' => 1,
                        'by_kind' => $byKind,
                        'folders' => 0,
                        'ignored' => ($kindInfo['ignored'] ?? false) ? 1 : 0,
                    ],
                    'warnings' => [],
                ];
            }

            $listed = $this->listFolderRecursive($resource['id']);
            $files = [];
            $byKind = [];
            $ignored = 0;
            $warnings = $listed['warnings'];

            foreach ($listed['files'] as $row) {
                $kindInfo = $this->classifier->classify($row['name']);
                if ($kindInfo['ignored'] ?? false) {
                    $ignored++;

                    continue;
                }
                $kind = (string) ($kindInfo['kind'] ?? 'unknown');
                $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;
                $files[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'path' => $row['path'],
                    'mime' => $row['mime'],
                    'size' => $row['size'],
                    'kind' => $kind,
                ];
            }

            $maxFiles = (int) config('clio.drive.max_files', 500);
            if (count($files) > $maxFiles) {
                $warnings[] = __('A pasta tem :n ficheiros relevantes; o limite de importação é :max.', [
                    'n' => count($files),
                    'max' => $maxFiles,
                ]);
            }

            $relevant = array_filter($files, fn (array $f) => $f['kind'] !== 'unknown');
            $message = count($relevant) > 0
                ? __('Pasta Drive acessível: :n ficheiro(s) reconhecido(s) para Clio.', ['n' => count($relevant)])
                : __('Pasta Drive acessível, mas nenhum CSV/ZIP reconhecido pelo classificador Clio.');

            return [
                'ok' => count($files) > 0,
                'resource_type' => 'folder',
                'resource_id' => $resource['id'],
                'message' => $message,
                'files' => $files,
                'summary' => [
                    'total' => count($files),
                    'by_kind' => $byKind,
                    'folders' => $listed['folders'],
                    'ignored' => $ignored,
                ],
                'warnings' => $warnings,
            ];
        } catch (\Throwable $e) {
            return [
                ...$empty,
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'message' => __('Falha ao aceder ao Drive: :m', ['m' => $e->getMessage()]),
            ];
        }
    }

    /**
     * Descarrega ficheiros relevantes para um diretório local (preserva paths relativos).
     *
     * @return array{dir: string, downloaded: int, skipped: int, bytes: int, verify: array}
     */
    public function downloadRelevantToTemp(string $url): array
    {
        $verify = $this->verify($url);
        if (! $verify['ok'] && $verify['files'] === []) {
            throw new RuntimeException($verify['message'] ?: __('Não foi possível verificar o Drive.'));
        }

        $maxFiles = (int) config('clio.drive.max_files', 500);
        $maxBytes = max(1, (int) config('clio.drive.max_file_mb', 64)) * 1024 * 1024;

        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'clio-drive-'.Str::lower(Str::random(12));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException(__('Não foi possível criar pasta temporária para o Drive.'));
        }

        $downloaded = 0;
        $skipped = 0;
        $bytes = 0;

        foreach (array_slice($verify['files'], 0, $maxFiles) as $file) {
            if (in_array($file['kind'], ['unknown'], true)) {
                $skipped++;

                continue;
            }

            if ($file['size'] !== null && $file['size'] > $maxBytes) {
                $skipped++;

                continue;
            }

            $relative = $this->sanitizeRelativePath((string) $file['path']);
            if ($relative === '') {
                $skipped++;

                continue;
            }

            $dest = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $parent = dirname($dest);
            if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                $skipped++;

                continue;
            }

            $this->downloadFileBinary((string) $file['id'], $dest);
            $downloaded++;
            $bytes += is_file($dest) ? (int) filesize($dest) : 0;
        }

        if ($downloaded === 0) {
            $this->deleteDirectory($dir);
            throw new RuntimeException(__('Nenhum ficheiro Clio foi descarregado do Drive.'));
        }

        return [
            'dir' => $dir,
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'bytes' => $bytes,
            'verify' => $verify,
        ];
    }

    public function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    /**
     * @return array{files: list<array{id: string, name: string, path: string, mime: string, size: ?int}>, folders: int, warnings: list<string>}
     */
    private function listFolderRecursive(string $folderId, string $prefix = '', int $depth = 0): array
    {
        $maxDepth = (int) config('clio.drive.max_depth', 4);
        $files = [];
        $folders = 0;
        $warnings = [];

        if ($depth > $maxDepth) {
            $warnings[] = __('Profundidade máxima do Drive atingida (:d).', ['d' => $maxDepth]);

            return compact('files', 'folders', 'warnings');
        }

        $pageToken = null;
        do {
            $query = [
                'q' => sprintf("'%s' in parents and trashed = false", $folderId),
                'fields' => 'nextPageToken, files(id, name, mimeType, size)',
                'pageSize' => 1000,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $payload = $this->http()->get('https://www.googleapis.com/drive/v3/files', $query)->throw()->json();
            $pageToken = $payload['nextPageToken'] ?? null;

            foreach ($payload['files'] ?? [] as $item) {
                $mime = (string) ($item['mimeType'] ?? '');
                $name = (string) ($item['name'] ?? '');
                $id = (string) ($item['id'] ?? '');
                if ($id === '' || $name === '') {
                    continue;
                }

                $path = $prefix === '' ? $name : $prefix.'/'.$name;

                if ($mime === 'application/vnd.google-apps.folder') {
                    $folders++;
                    $child = $this->listFolderRecursive($id, $path, $depth + 1);
                    $files = array_merge($files, $child['files']);
                    $folders += $child['folders'];
                    $warnings = array_merge($warnings, $child['warnings']);

                    continue;
                }

                // Ignorar docs Google nativos (não CSV)
                if (str_starts_with($mime, 'application/vnd.google-apps.')) {
                    continue;
                }

                $files[] = [
                    'id' => $id,
                    'name' => $name,
                    'path' => $path,
                    'mime' => $mime,
                    'size' => isset($item['size']) ? (int) $item['size'] : null,
                ];
            }
        } while ($pageToken);

        return compact('files', 'folders', 'warnings');
    }

    /**
     * @return array<string, mixed>
     */
    private function getFileMeta(string $fileId): array
    {
        return $this->http()
            ->get('https://www.googleapis.com/drive/v3/files/'.$fileId, [
                'fields' => 'id,name,mimeType,size',
                'supportsAllDrives' => 'true',
            ])
            ->throw()
            ->json();
    }

    private function downloadFileBinary(string $fileId, string $destPath): void
    {
        $key = $this->apiKey();
        if ($key === null) {
            throw new RuntimeException(__('CLIO_DRIVE_API_KEY não configurada.'));
        }

        $response = Http::timeout((int) config('clio.drive.request_timeout', 120))
            ->withOptions(['sink' => $destPath])
            ->get('https://www.googleapis.com/drive/v3/files/'.$fileId, [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
                'key' => $key,
            ]);

        if ($response->failed()) {
            @unlink($destPath);
            throw new RuntimeException(__('Falha ao descarregar ficheiro Drive :id (:status).', [
                'id' => $fileId,
                'status' => $response->status(),
            ]));
        }
    }

    private function sanitizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = preg_replace('/[^\pL\pN\-_. ()\[\]]+/u', '_', $part) ?: 'file';
        }

        return implode('/', $parts);
    }

    private function http(): PendingRequest
    {
        $key = $this->apiKey();
        if ($key === null) {
            throw new RuntimeException(__('CLIO_DRIVE_API_KEY não configurada.'));
        }

        return Http::timeout((int) config('clio.drive.request_timeout', 120))
            ->acceptJson()
            ->withQueryParameters(['key' => $key]);
    }

    private function apiKey(): ?string
    {
        $key = trim((string) config('clio.drive.api_key', ''));

        return $key !== '' ? $key : null;
    }
}
