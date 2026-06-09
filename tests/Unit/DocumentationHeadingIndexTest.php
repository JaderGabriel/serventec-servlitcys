<?php

namespace Tests\Unit;

use App\Support\Admin\DocumentationHeadingIndex;
use Tests\TestCase;

class DocumentationHeadingIndexTest extends TestCase
{
    public function test_extracts_headings_with_unique_slugs(): void
    {
        $markdown = <<<'MD'
# Título principal

## Resumo executivo

## Resumo executivo

### Detalhe
MD;

        $headings = (new DocumentationHeadingIndex)->headingsFromMarkdown($markdown);

        $this->assertCount(4, $headings);
        $this->assertSame('titulo-principal', $headings[0]['id']);
        $this->assertSame('resumo-executivo', $headings[1]['id']);
        $this->assertSame('resumo-executivo-2', $headings[2]['id']);
        $this->assertSame(3, $headings[3]['level']);
    }

    public function test_injects_ids_into_html_headings(): void
    {
        $index = new DocumentationHeadingIndex;
        $markdown = "# Título\n\n## Secção A\n";
        $headings = $index->headingsFromMarkdown($markdown);
        $html = '<h1>Título</h1><p>Texto</p><h2>Secção A</h2>';
        $result = $index->injectHeadingIds($html, $headings);

        $this->assertStringContainsString('id="titulo"', $result);
        $this->assertStringContainsString('id="seccao-a"', $result);
        $this->assertStringContainsString('serv-docs-heading', $result);
    }
}
