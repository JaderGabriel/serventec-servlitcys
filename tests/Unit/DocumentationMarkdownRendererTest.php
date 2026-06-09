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
            'docs/README.md',
            'admin.documentation',
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
            'docs/IMPLANTACAO_PRODUCAO.md',
            'admin.documentation',
        );

        $this->assertStringContainsString(
            route('admin.documentation.show', ['doc' => 'README.md']),
            $html
        );
    }

    public function test_transforma_blocos_mermaid_em_div(): void
    {
        $renderer = new DocumentationMarkdownRenderer;
        $html = $renderer->toHtml(
            "```mermaid\nflowchart LR\nA-->B\n```",
            'docs/HUB_DOCUMENTACAO.md'
        );

        $this->assertStringContainsString('<div class="mermaid">', $html);
        $this->assertStringContainsString('flowchart LR', $html);
        $this->assertStringNotContainsString('language-mermaid', $html);
    }

    public function test_render_injects_heading_anchors(): void
    {
        $renderer = new DocumentationMarkdownRenderer;
        $result = $renderer->render(
            "# Título\n\nParágrafo.\n\n## Secção\n",
            'docs/README.md',
        );

        $this->assertStringContainsString('id="titulo"', $result['html']);
        $this->assertStringContainsString('id="seccao"', $result['html']);
        $this->assertCount(2, $result['headings']);
    }

    public function test_markdown_uses_mermaid_detecta_bloco(): void
    {
        $renderer = new DocumentationMarkdownRenderer;

        $this->assertTrue($renderer->markdownUsesMermaid("```mermaid\ngraph TD\n```"));
        $this->assertFalse($renderer->markdownUsesMermaid('# Título'));
    }
}
