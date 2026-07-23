<?php

namespace App\Services\Clio\Drive;

use App\Services\Clio\Ingest\ArtifactClassifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lista e descarrega pastas/ficheiros Google Drive partilhados («qualquer pessoa com o link»).
 *
 * Por defeito usa a vista pública (embeddedfolderview) + uc?export=download — sem API key.
 * Se CLIO_DRIVE_API_KEY estiver definida, usa Drive API v3 como fallback/reforço.
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

        try {
            if ($resource['type'] === 'file') {
                $name = $this->guessFileName($resource['id']);
                $kindInfo = $this->classifier->classify($name);
                $file = [
                    'id' => $resource['id'],
                    'name' => $name,
                    'path' => $name,
                    'mime' => $this->mimeFromName($name),
                    'size' => null,
                    'kind' => (string) ($kindInfo['kind'] ?? 'unknown'),
                ];

                return [
                    'ok' => true,
                    'resource_type' => 'file',
                    'resource_id' => $resource['id'],
                    'message' => __('Ficheiro Drive reconhecido.'),
                    'files' => [$file],
                    'summary' => [
                        'total' => 1,
                        'by_kind' => [$file['kind'] => 1],
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

            if ($files === [] && ($listed['folders'] ?? 0) === 0) {
                return [
                    ...$empty,
                    'resource_type' => 'folder',
                    'resource_id' => $resource['id'],
                    'message' => __('Pasta Drive vazia ou sem acesso público. Partilhe com «qualquer pessoa com o link».'),
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
            $size = is_file($dest) ? (int) filesize($dest) : 0;
            if ($size <= 0 || $this->looksLikeHtmlErrorPage($dest)) {
                @unlink($dest);
                $skipped++;

                continue;
            }
            if ($size > $maxBytes) {
                @unlink($dest);
                $skipped++;

                continue;
            }

            $downloaded++;
            $bytes += $size;
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

    /**
     * Descarrega um subconjunto de ficheiros já catalogados (lote).
     *
     * @param  list<array{id: string, name: string, path: string, mime?: string, size?: ?int, kind: string}>  $files
     * @return array{dir: string, downloaded: int, skipped: int, bytes: int, by_path: array<string, array{id: string, size: int, status: string, error?: string}>}
     */
    public function downloadSelectedToTemp(array $files): array
    {
        $maxBytes = max(1, (int) config('clio.drive.max_file_mb', 64)) * 1024 * 1024;

        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'clio-drive-'.Str::lower(Str::random(12));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException(__('Não foi possível criar pasta temporária para o Drive.'));
        }

        $downloaded = 0;
        $skipped = 0;
        $bytes = 0;
        $byPath = [];

        foreach ($files as $file) {
            $kind = (string) ($file['kind'] ?? 'unknown');
            if ($kind === 'unknown') {
                $skipped++;
                $byPath[(string) $file['path']] = [
                    'id' => (string) $file['id'],
                    'size' => 0,
                    'status' => 'skipped',
                    'error' => 'unknown',
                ];

                continue;
            }

            if (($file['size'] ?? null) !== null && (int) $file['size'] > $maxBytes) {
                $skipped++;
                $byPath[(string) $file['path']] = [
                    'id' => (string) $file['id'],
                    'size' => (int) $file['size'],
                    'status' => 'skipped',
                    'error' => 'too_large',
                ];

                continue;
            }

            $relative = $this->sanitizeRelativePath((string) $file['path']);
            if ($relative === '') {
                $skipped++;
                $byPath[(string) ($file['path'] ?? '')] = [
                    'id' => (string) $file['id'],
                    'size' => 0,
                    'status' => 'failed',
                    'error' => 'bad_path',
                ];

                continue;
            }

            $dest = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $parent = dirname($dest);
            if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                $skipped++;
                $byPath[$relative] = [
                    'id' => (string) $file['id'],
                    'size' => 0,
                    'status' => 'failed',
                    'error' => 'mkdir',
                ];

                continue;
            }

            try {
                $this->downloadFileBinary((string) $file['id'], $dest);
            } catch (\Throwable $e) {
                @unlink($dest);
                $skipped++;
                $byPath[$relative] = [
                    'id' => (string) $file['id'],
                    'size' => 0,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            $size = is_file($dest) ? (int) filesize($dest) : 0;
            if ($size <= 0 || $this->looksLikeHtmlErrorPage($dest)) {
                @unlink($dest);
                $skipped++;
                $byPath[$relative] = [
                    'id' => (string) $file['id'],
                    'size' => 0,
                    'status' => 'failed',
                    'error' => 'empty_or_html',
                ];

                continue;
            }
            if ($size > $maxBytes) {
                @unlink($dest);
                $skipped++;
                $byPath[$relative] = [
                    'id' => (string) $file['id'],
                    'size' => $size,
                    'status' => 'skipped',
                    'error' => 'too_large',
                ];

                continue;
            }

            $downloaded++;
            $bytes += $size;
            $byPath[$relative] = [
                'id' => (string) $file['id'],
                'size' => $size,
                'status' => 'downloaded',
            ];
        }

        if ($downloaded === 0) {
            $this->deleteDirectory($dir);
            throw new RuntimeException(__('Nenhum ficheiro Clio foi descarregado neste lote do Drive.'));
        }

        return [
            'dir' => $dir,
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'bytes' => $bytes,
            'by_path' => $byPath,
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

        try {
            $entries = $this->listFolderPublicEmbed($folderId);
            if ($entries === [] && $this->apiKeyConfigured()) {
                $entries = $this->listFolderViaApi($folderId);
            }
        } catch (\Throwable $e) {
            if ($this->apiKeyConfigured()) {
                $entries = $this->listFolderViaApi($folderId);
            } else {
                throw $e;
            }
        }

        foreach ($entries as $entry) {
            $path = $prefix === '' ? $entry['name'] : $prefix.'/'.$entry['name'];

            if ($entry['type'] === 'folder') {
                $folders++;
                $child = $this->listFolderRecursive($entry['id'], $path, $depth + 1);
                $files = array_merge($files, $child['files']);
                $folders += $child['folders'];
                $warnings = array_merge($warnings, $child['warnings']);

                continue;
            }

            $files[] = [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'path' => $path,
                'mime' => $entry['mime'],
                'size' => $entry['size'] ?? null,
            ];
        }

        return compact('files', 'folders', 'warnings');
    }

    /**
     * @return list<array{type: 'folder'|'file', id: string, name: string, mime: string}>
     */
    private function listFolderPublicEmbed(string $folderId): array
    {
        $response = Http::timeout((int) config('clio.drive.request_timeout', 120))
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ServLitcys-Clio/1.0)'])
            ->get('https://drive.google.com/embeddedfolderview', [
                'id' => $folderId,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(__('Vista pública do Drive indisponível (:status). Confirme que a pasta está partilhada com «qualquer pessoa com o link».', [
                'status' => $response->status(),
            ]));
        }

        $html = $response->body();
        if ($html === '') {
            throw new RuntimeException(__('Resposta vazia do Google Drive.'));
        }

        // Pastas vazias (ou páginas sem listagem) — não abortar a árvore inteira.
        if (! str_contains($html, 'flip-entry')) {
            return [];
        }

        $entries = [];
        if (preg_match_all(
            '~<div class="flip-entry"[^>]*\bid="entry-([^"]+)"[^>]*>[\s\S]*?<a href="(https://drive\.google\.com/(?:drive/folders/|file/d/)[^"]+)"[\s\S]*?<div class="flip-entry-title">([^<]+)</div>~u',
            $html,
            $matches,
            PREG_SET_ORDER
        ) === false || $matches === []) {
            return [];
        }

        foreach ($matches as $match) {
            $id = $match[1];
            $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
            $name = html_entity_decode(trim($match[3]), ENT_QUOTES | ENT_HTML5);
            if ($id === '' || $name === '') {
                continue;
            }

            $isFolder = str_contains($href, '/folders/');
            $entries[] = [
                'type' => $isFolder ? 'folder' : 'file',
                'id' => $id,
                'name' => $name,
                'mime' => $isFolder ? 'application/vnd.google-apps.folder' : $this->mimeFromName($name),
                'size' => null,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{type: 'folder'|'file', id: string, name: string, mime: string}>
     */
    private function listFolderViaApi(string $folderId): array
    {
        $key = $this->apiKey();
        if ($key === null) {
            throw new RuntimeException(__('CLIO_DRIVE_API_KEY não configurada.'));
        }

        $entries = [];
        $pageToken = null;
        do {
            $query = [
                'q' => sprintf("'%s' in parents and trashed = false", $folderId),
                'fields' => 'nextPageToken, files(id, name, mimeType, size)',
                'pageSize' => 1000,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'key' => $key,
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $payload = Http::timeout((int) config('clio.drive.request_timeout', 120))
                ->acceptJson()
                ->get('https://www.googleapis.com/drive/v3/files', $query)
                ->throw()
                ->json();

            $pageToken = $payload['nextPageToken'] ?? null;
            foreach ($payload['files'] ?? [] as $item) {
                $mime = (string) ($item['mimeType'] ?? '');
                $name = (string) ($item['name'] ?? '');
                $id = (string) ($item['id'] ?? '');
                if ($id === '' || $name === '') {
                    continue;
                }
                if (str_starts_with($mime, 'application/vnd.google-apps.') && $mime !== 'application/vnd.google-apps.folder') {
                    continue;
                }
                $entries[] = [
                    'type' => $mime === 'application/vnd.google-apps.folder' ? 'folder' : 'file',
                    'id' => $id,
                    'name' => $name,
                    'mime' => $mime !== '' ? $mime : $this->mimeFromName($name),
                    'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                ];
            }
        } while ($pageToken);

        return $entries;
    }

    private function downloadFileBinary(string $fileId, string $destPath): void
    {
        $timeout = (int) config('clio.drive.request_timeout', 120);
        $url = 'https://drive.google.com/uc?export=download&id='.$fileId.'&confirm=t';

        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ServLitcys-Clio/1.0)'])
            ->withOptions(['sink' => $destPath, 'allow_redirects' => true])
            ->get($url);

        if ($response->failed()) {
            @unlink($destPath);
            throw new RuntimeException(__('Falha ao descarregar ficheiro Drive :id (:status).', [
                'id' => $fileId,
                'status' => $response->status(),
            ]));
        }

        // Confirmação anti-vírus (ficheiros grandes): HTML com confirm=
        if ($this->looksLikeHtmlErrorPage($destPath)) {
            $html = (string) file_get_contents($destPath);
            $confirm = null;
            if (preg_match('~confirm=([0-9A-Za-z_-]+)~', $html, $m)) {
                $confirm = $m[1];
            } elseif (preg_match('~name="confirm"\s+value="([^"]+)"~', $html, $m)) {
                $confirm = $m[1];
            }

            if ($confirm !== null) {
                $response = Http::timeout($timeout)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ServLitcys-Clio/1.0)'])
                    ->withOptions(['sink' => $destPath, 'allow_redirects' => true])
                    ->get('https://drive.google.com/uc', [
                        'export' => 'download',
                        'id' => $fileId,
                        'confirm' => $confirm,
                    ]);

                if ($response->failed() || $this->looksLikeHtmlErrorPage($destPath)) {
                    @unlink($destPath);
                    throw new RuntimeException(__('Falha ao confirmar download do ficheiro Drive :id.', ['id' => $fileId]));
                }
            }
        }
    }

    private function looksLikeHtmlErrorPage(string $path): bool
    {
        if (! is_file($path) || filesize($path) === 0) {
            return true;
        }

        $sample = (string) file_get_contents($path, false, null, 0, 512);

        return str_contains($sample, '<!DOCTYPE html')
            || str_contains($sample, '<html')
            || str_contains($sample, 'drive.google.com');
    }

    private function guessFileName(string $fileId): string
    {
        // Sem API, usamos extensão neutra; o classificador pode falhar — o download mantém bytes.
        return 'drive-'.$fileId.'.bin';
    }

    private function mimeFromName(string $name): string
    {
        $lower = Str::lower($name);
        if (str_ends_with($lower, '.csv')) {
            return 'text/csv';
        }
        if (str_ends_with($lower, '.zip')) {
            return 'application/zip';
        }
        if (str_ends_with($lower, '.txt')) {
            return 'text/plain';
        }

        return 'application/octet-stream';
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

    private function apiKey(): ?string
    {
        $key = trim((string) config('clio.drive.api_key', ''));

        return $key !== '' ? $key : null;
    }
}
