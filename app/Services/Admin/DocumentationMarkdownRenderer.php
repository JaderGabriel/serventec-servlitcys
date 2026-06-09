<?php

namespace App\Services\Admin;

use App\Support\Admin\DocumentationCatalog;
use App\Support\Admin\DocumentationHeadingIndex;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class DocumentationMarkdownRenderer
{
    public function __construct(
        private DocumentationHeadingIndex $headingIndex = new DocumentationHeadingIndex,
    ) {}

    /**
     * @return array{html: string, headings: list<array{id: string, level: int, text: string}>}
     */
    public function render(string $markdown, string $currentPath, ?string $documentationRoutePrefix = null): array
    {
        $headings = $this->headingIndex->headingsFromMarkdown($markdown);
        $html = $this->toHtml($markdown, $currentPath, $documentationRoutePrefix);
        $html = $this->headingIndex->injectHeadingIds($html, $headings);

        return [
            'html' => $html,
            'headings' => $headings,
        ];
    }

    public function toHtml(string $markdown, string $currentPath, ?string $documentationRoutePrefix = null): string
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        $converter = new MarkdownConverter($environment);
        $html = $converter->convert($markdown)->getContent();
        $html = $this->transformMermaidFences($html);

        return $this->rewriteInternalDocLinks(
            $html,
            $currentPath,
            $documentationRoutePrefix ?? DocumentationCatalog::readerRoutePrefix(),
        );
    }

    public function markdownUsesMermaid(string $markdown): bool
    {
        return (bool) preg_match('/```mermaid\b/i', $markdown);
    }

    private function transformMermaidFences(string $html): string
    {
        return (string) preg_replace_callback(
            '/<pre><code class="language-mermaid">(.*?)<\/code><\/pre>/si',
            static function (array $matches): string {
                $diagram = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

                return '<div class="mermaid">'.trim($diagram).'</div>';
            },
            $html,
        );
    }

    private function rewriteInternalDocLinks(string $html, string $currentPath, string $documentationRoutePrefix): string
    {
        $baseDir = dirname($currentPath);
        if ($baseDir === '.') {
            $baseDir = '';
        }

        return (string) preg_replace_callback(
            '/<a\s+href="([^"]+)"/i',
            function (array $matches) use ($baseDir, $documentationRoutePrefix): string {
                $href = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

                if ($href === '' || str_contains($href, '://') || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                    return $matches[0];
                }

                $fragment = '';
                if (str_contains($href, '#')) {
                    [$hrefPath, $fragmentPart] = explode('#', $href, 2);
                    $href = $hrefPath;
                    $fragment = '#'.Str::slug(Str::ascii(urldecode($fragmentPart)));
                    if ($fragment === '#') {
                        $fragment = '';
                    }
                }

                $target = $this->resolveRelativeDocPath($href, $baseDir);
                $resolved = $target !== null ? DocumentationCatalog::resolveReadablePath($target) : null;
                if ($resolved === null) {
                    return $matches[0];
                }

                $url = route($documentationRoutePrefix.'.show', ['doc' => $resolved]).$fragment;

                return '<a href="'.e($url).'"';
            },
            $html,
        );
    }

    private function resolveRelativeDocPath(string $href, string $baseDir): ?string
    {
        $href = strtok($href, '#') ?: $href;
        $href = strtok($href, '?') ?: $href;

        if (! str_ends_with(strtolower($href), '.md')) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            $path = ltrim($href, '/');
        } elseif (str_starts_with($href, 'docs/') || $href === 'README.md') {
            $path = $href;
        } elseif ($baseDir === 'docs' || str_starts_with($baseDir, 'docs/')) {
            $path = $baseDir !== '' ? $baseDir.'/'.$href : 'docs/'.$href;
        } else {
            $path = $baseDir !== '' ? $baseDir.'/'.$href : $href;
        }

        $path = str_replace(['\\', '//'], ['/', '/'], $path);
        $segments = explode('/', $path);
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }
            $resolved[] = $segment;
        }
        $path = implode('/', $resolved);

        return $path !== '' ? $path : null;
    }
}
