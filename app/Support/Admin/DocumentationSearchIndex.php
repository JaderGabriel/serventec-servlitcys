<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Índice de pesquisa full-text leve sobre a documentação curada (menu + cabeçalhos MD).
 */
final class DocumentationSearchIndex
{
    private const MIN_QUERY_LENGTH = 2;

    private const CACHE_SECONDS = 600;

    /**
     * @return list<array{path: string, label: string, hint: string, section_title: string, url: string, excerpt: string, score: int}>
     */
    public function search(?User $user, string $query, int $limit = 20, ?string $routePrefix = null): array
    {
        $terms = $this->tokenize($query);
        if ($terms === []) {
            return [];
        }

        $routePrefix ??= DocumentationCatalog::readerRoutePrefix($user);
        $entries = $this->indexedEntriesForUser($user);
        $scored = [];

        foreach ($entries as $entry) {
            $score = $this->scoreEntry($entry, $terms);
            if ($score <= 0) {
                continue;
            }
            $scored[] = [
                'path' => $entry['path'],
                'label' => $entry['label'],
                'hint' => $entry['hint'],
                'section_title' => $entry['section_title'],
                'url' => route($routePrefix.'.show', ['doc' => $entry['path']]),
                'excerpt' => $entry['excerpt'],
                'score' => $score,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $top = array_slice($scored, 0, max(1, $limit));
        foreach ($top as &$row) {
            unset($row['score']);
        }

        return $top;
    }

    /**
     * @return list<array{path: string, label: string, hint: string, section_title: string, search_blob: string, excerpt: string}>
     */
    private function indexedEntriesForUser(?User $user): array
    {
        $isAdmin = $user?->isAdmin() ?? false;
        $cacheKey = 'documentation_search_index:'.($isAdmin ? 'admin' : 'user').':'.self::contentFingerprint();

        /** @var list<array{path: string, label: string, hint: string, section_title: string, search_blob: string, excerpt: string}> $cached */
        $cached = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($user): array {
            $entries = [];
            $seen = [];

            foreach (DocumentationCatalog::flatEntriesForUser($user) as $item) {
                $path = $item['path'];
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;

                $contentMeta = $this->contentMetaForPath($path, $item['label'] ?? '');
                $blob = Str::lower(implode(' ', array_filter([
                    $item['label'],
                    $item['hint'] ?? '',
                    $item['section_title'],
                    $path,
                    basename($path, '.md'),
                    $contentMeta['headings'],
                    $contentMeta['keywords'],
                ])));

                $entries[] = [
                    'path' => $path,
                    'label' => $item['label'],
                    'hint' => (string) ($item['hint'] ?? ''),
                    'section_title' => $item['section_title'],
                    'search_blob' => $blob,
                    'excerpt' => $contentMeta['excerpt'],
                ];
            }

            return $entries;
        });

        return $cached;
    }

    /**
     * @return array{headings: string, keywords: string, excerpt: string}
     */
    private function contentMetaForPath(string $path, string $label = ''): array
    {
        $resolved = DocumentationCatalog::resolveReadablePath($path);
        if ($resolved === null) {
            return ['headings' => '', 'keywords' => '', 'excerpt' => ''];
        }

        $absolute = base_path($resolved);
        if (! is_readable($absolute)) {
            return ['headings' => '', 'keywords' => '', 'excerpt' => ''];
        }

        $markdown = File::get($absolute);
        $headingRows = app(DocumentationHeadingIndex::class)->headingsFromMarkdown($markdown);
        $headingTexts = array_column($headingRows, 'text');
        $headings = implode(' ', $headingTexts);
        $keywords = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/\*\*Versão do produto:\*\*/i', $trimmed)) {
                continue;
            }
            if (preg_match('/^>\s+\*\*Índice:\*\*/i', $trimmed)) {
                continue;
            }
            if (count($keywords) < 12 && ! str_starts_with($trimmed, '#') && ! str_starts_with($trimmed, '>')) {
                $plain = preg_replace('/`([^`]+)`/', '$1', $trimmed) ?? $trimmed;
                $plain = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $plain) ?? $plain;
                $plain = trim(strip_tags($plain));
                if (mb_strlen($plain) >= 20) {
                    $keywords[] = $plain;
                }
            }
        }

        $excerpt = $label !== '' ? $label : '';
        if ($headingTexts !== []) {
            $excerpt = Str::limit($headingTexts[0], 140);
        } elseif ($keywords !== []) {
            $excerpt = Str::limit($keywords[0], 140);
        }

        return [
            'headings' => $headings,
            'keywords' => implode(' ', $keywords),
            'excerpt' => $excerpt,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $query): array
    {
        $normalized = Str::lower(Str::ascii(trim($query)));
        if (mb_strlen($normalized) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $parts = preg_split('/[^a-z0-9]+/i', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $t): bool => mb_strlen($t) >= self::MIN_QUERY_LENGTH,
        )));
    }

    /**
     * @param  array{path: string, label: string, hint: string, section_title: string, search_blob: string, excerpt: string}  $entry
     * @param  list<string>  $terms
     */
    private function scoreEntry(array $entry, array $terms): int
    {
        $score = 0;
        $label = Str::lower(Str::ascii($entry['label']));
        $hint = Str::lower(Str::ascii($entry['hint']));
        $section = Str::lower(Str::ascii($entry['section_title']));
        $blob = $entry['search_blob'];

        foreach ($terms as $term) {
            if (str_contains($label, $term)) {
                $score += 40;
                if ($label === $term || str_starts_with($label, $term.' ')) {
                    $score += 20;
                }
            }
            if ($hint !== '' && str_contains($hint, $term)) {
                $score += 25;
            }
            if (str_contains($section, $term)) {
                $score += 15;
            }
            if (str_contains($blob, $term)) {
                $score += 10;
            }
        }

        return $score;
    }

    private static function contentFingerprint(): string
    {
        $docsDir = base_path('docs');
        if (! is_dir($docsDir)) {
            return '0';
        }

        $mtime = @filemtime($docsDir) ?: 0;
        $readme = @filemtime(base_path('README.md')) ?: 0;

        return (string) max($mtime, $readme);
    }
}
