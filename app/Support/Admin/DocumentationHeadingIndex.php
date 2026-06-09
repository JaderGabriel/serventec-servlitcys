<?php

namespace App\Support\Admin;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Extrai cabeçalhos Markdown e injecta âncoras no HTML para navegação «Neste documento».
 */
final class DocumentationHeadingIndex
{
    /**
     * @return list<array{id: string, level: int, text: string}>
     */
    public function headingsFromMarkdown(string $markdown): array
    {
        $headings = [];
        $slugCounts = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (! preg_match('/^(#{1,4})\s+(.+)$/u', trim($line), $matches)) {
                continue;
            }

            $level = strlen($matches[1]);
            $text = $this->plainHeadingText($matches[2]);
            if ($text === '') {
                continue;
            }

            $base = Str::slug(Str::ascii($text));
            if ($base === '') {
                $base = 'secao';
            }

            $slugCounts[$base] = ($slugCounts[$base] ?? 0) + 1;
            $id = $slugCounts[$base] > 1 ? $base.'-'.$slugCounts[$base] : $base;

            $headings[] = [
                'id' => $id,
                'level' => $level,
                'text' => $text,
            ];
        }

        return $headings;
    }

    /**
     * @param  list<array{id: string, level: int, text: string}>  $headings
     */
    public function injectHeadingIds(string $html, array $headings): string
    {
        if ($headings === [] || trim($html) === '') {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapper = '<div id="serv-docs-root">'.$html.'</div>';
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapper,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="serv-docs-root"]//h1 | //*[@id="serv-docs-root"]//h2 | //*[@id="serv-docs-root"]//h3 | //*[@id="serv-docs-root"]//h4');
        if ($nodes === false) {
            return $html;
        }

        $index = 0;
        foreach ($nodes as $node) {
            if ($index >= count($headings)) {
                break;
            }
            $node->setAttribute('id', $headings[$index]['id']);
            $node->setAttribute('class', trim($node->getAttribute('class').' serv-docs-heading'));
            $index++;
        }

        $root = $dom->getElementById('serv-docs-root');
        if ($root === null) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    private function plainHeadingText(string $raw): string
    {
        $text = preg_replace('/`([^`]+)`/', '$1', $raw) ?? $raw;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/\s+#+\s*$/', '', $text) ?? $text;

        return trim($text);
    }
}
