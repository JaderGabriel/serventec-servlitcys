<?php

namespace Tests\Unit;

use App\Services\Admin\DocumentationMarkdownRenderer;
use Tests\TestCase;

class DocumentationMarkdownRendererTest extends TestCase
{
    public function test_rewrites_relative_doc_links_to_admin_reader(): void
    {
        $renderer = new DocumentationMarkdownRenderer;
        $html = $renderer->toHtml(
            '[Entregas](ENTREGAS_ESCALONADAS_MAIO_2026.md)',
            'docs/README.md'
        );

        $this->assertStringContainsString(
            route('admin.documentation.show', ['doc' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md']),
            $html
        );
    }

    public function test_rewrites_parent_readme_link(): void
    {
        $renderer = new DocumentationMarkdownRenderer;
        $html = $renderer->toHtml(
            '[Instalação](../README.md)',
            'docs/IMPLANTACAO_PRODUCAO.md'
        );

        $this->assertStringContainsString(
            route('admin.documentation.show', ['doc' => 'README.md']),
            $html
        );
    }
}
