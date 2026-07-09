<?php

namespace App\Support\Product;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Publicação alinhada: tag Git + GitHub Release + nota RELEASE_*.md.
 */
final class ProductReleasePublisher
{
    /**
     * @return array{date: string, suffix: string, codename: string, sort_key: string}
     */
    public function parseTag(string $tag): array
    {
        $parsed = ProductReleaseTag::parse(trim($tag));
        if ($parsed === null) {
            throw new RuntimeException(
                'Tag inválida. Use YYYYMMDD[-letra]-Codename (ex.: 20260709-Calliope).'
            );
        }

        return $parsed;
    }

    public function releaseNotesPath(string $tag): string
    {
        $path = ProductReleaseTag::releaseDocPath($tag);
        if ($path === null) {
            throw new RuntimeException('Não foi possível derivar o caminho RELEASE_*.md a partir da tag.');
        }

        return $path;
    }

    public function assertReleaseNotesExist(string $relativePath): void
    {
        $absolute = base_path($relativePath);
        if (! is_file($absolute)) {
            throw new RuntimeException("Nota de release ausente: {$relativePath}");
        }
    }

    /**
     * @return list<string>
     */
    public function configMismatches(string $tag, string $version): array
    {
        $product = config('documentation.product', []);
        $errors = [];

        if ((string) ($product['version'] ?? '') !== $version) {
            $errors[] = 'config/documentation.php product.version ≠ '.$version;
        }

        if ((string) ($product['release_tag'] ?? '') !== $tag) {
            $errors[] = 'config/documentation.php product.release_tag ≠ '.$tag;
        }

        $commitShort = trim((string) ($product['commit_short'] ?? ''));
        if ($commitShort === '' || $commitShort === 'pending') {
            $errors[] = 'config/documentation.php product.commit_short pendente';
        }

        return $errors;
    }

    public function headShortHash(): string
    {
        $result = Process::run(['git', 'rev-parse', '--short=7', 'HEAD']);
        if (! $result->successful()) {
            throw new RuntimeException('git rev-parse HEAD falhou.');
        }

        return trim($result->output());
    }

    public function commitCount(): int
    {
        $result = Process::run(['git', 'rev-list', '--count', 'HEAD']);
        if (! $result->successful()) {
            throw new RuntimeException('git rev-list --count HEAD falhou.');
        }

        return (int) trim($result->output());
    }

    public function tagExists(string $tag, bool $remote = false): bool
    {
        $args = $remote
            ? ['git', 'ls-remote', '--tags', 'origin', 'refs/tags/'.$tag]
            : ['git', 'rev-parse', '--verify', 'refs/tags/'.$tag];

        $result = Process::run($args);

        return $result->successful() && trim($result->output()) !== '';
    }

    public function githubReleaseExists(string $tag): bool
    {
        $result = Process::run(['gh', 'release', 'view', $tag, '--json', 'tagName']);

        return $result->successful();
    }

    /**
     * @return array{tag: string, notes: string, version: string, config_ok: bool, tag_local: bool, tag_remote: bool, gh_release: bool, mismatches: list<string>}
     */
    public function status(string $tag, string $version): array
    {
        $this->parseTag($tag);
        $notes = $this->releaseNotesPath($tag);
        $notesExist = is_file(base_path($notes));
        $mismatches = $this->configMismatches($tag, $version);

        return [
            'tag' => $tag,
            'notes' => $notes,
            'version' => $version,
            'notes_exist' => $notesExist,
            'config_ok' => $mismatches === [],
            'tag_local' => $this->tagExists($tag, false),
            'tag_remote' => $this->tagExists($tag, true),
            'gh_release' => $this->githubReleaseExists($tag),
            'mismatches' => $mismatches,
        ];
    }

    public function createAnnotatedTag(string $tag, string $message): void
    {
        if ($this->tagExists($tag, false)) {
            throw new RuntimeException("Tag local já existe: {$tag}");
        }

        $result = Process::run(['git', 'tag', '-a', $tag, '-m', $message]);
        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'git tag falhou.');
        }
    }

    public function pushTag(string $tag): void
    {
        $result = Process::run(['git', 'push', 'origin', $tag]);
        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'git push tag falhou.');
        }
    }

    public function createGitHubRelease(string $tag, string $title, string $notesRelativePath): void
    {
        if ($this->githubReleaseExists($tag)) {
            throw new RuntimeException("GitHub Release já existe: {$tag}");
        }

        $notesAbsolute = base_path($notesRelativePath);
        $result = Process::run([
            'gh', 'release', 'create', $tag,
            '--title', $title,
            '--notes-file', $notesAbsolute,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'gh release create falhou.');
        }
    }

    public function defaultReleaseTitle(string $version, string $tag): string
    {
        return 'ServLitcys '.$version.' — '.$tag;
    }
}
