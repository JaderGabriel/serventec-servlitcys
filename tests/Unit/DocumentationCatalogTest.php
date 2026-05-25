<?php

namespace Tests\Unit;

use App\Support\Admin\DocumentationCatalog;
use Tests\TestCase;

class DocumentationCatalogTest extends TestCase
{
    public function test_resolve_readable_path_accepts_any_md_under_docs(): void
    {
        $this->assertSame(
            'docs/CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md',
            DocumentationCatalog::resolveReadablePath('docs/CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md')
        );
    }

    public function test_resolve_readable_path_accepts_bare_filename_in_docs(): void
    {
        $this->assertSame(
            'docs/VARIAVEIS_AMBIENTE.md',
            DocumentationCatalog::resolveReadablePath('VARIAVEIS_AMBIENTE.md')
        );
    }

    public function test_resolve_readable_path_rejects_traversal(): void
    {
        $this->assertNull(DocumentationCatalog::resolveReadablePath('docs/../../.env'));
    }

    public function test_reader_url_uses_resolved_path(): void
    {
        $url = DocumentationCatalog::readerUrl('ENTREGAS_ESCALONADAS_MAIO_2026.md');

        $this->assertStringContainsString('doc=docs%2FENTREGAS_ESCALONADAS_MAIO_2026.md', $url);
    }
}
