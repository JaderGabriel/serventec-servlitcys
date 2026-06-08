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

    public function test_outros_documentos_mostra_release_producao_e_submenu(): void
    {
        config([
            'documentation.product' => [
                'version' => '4.4.0',
                'release_tag' => '20260607a-Ananke',
                'revision_date' => '2026-06-07',
                'in_production' => true,
                'production_label' => 'Em produção',
            ],
        ]);

        $production = DocumentationCatalog::productionReleasePath();
        $this->assertSame('docs/RELEASE_20260607a_ANANKE.md', $production);

        $layout = DocumentationCatalog::releaseOutrosLayout(4);
        $this->assertCount(4, $layout['featured']);
        $this->assertSame($production, $layout['featured'][0]['path']);
        $this->assertStringContainsString('Em produção', (string) ($layout['featured'][0]['hint'] ?? ''));
        $this->assertNotEmpty($layout['submenu']);

        $featuredPaths = array_column($layout['featured'], 'path');
        $submenuPaths = array_column($layout['submenu'], 'path');
        $this->assertEmpty(array_intersect($featuredPaths, $submenuPaths));

        $submenuSortKeys = array_map(
            static fn (string $path): string => (string) preg_replace('/^.*RELEASE_(\d{8}[a-z]?)_.*$/i', '$1', $path),
            $submenuPaths,
        );
        $sorted = $submenuSortKeys;
        rsort($sorted, SORT_STRING);
        $this->assertSame($sorted, $submenuSortKeys);

        $sections = DocumentationCatalog::sections();
        $outros = collect($sections)->firstWhere('title', __('Outros documentos'));
        $this->assertNotNull($outros);
        $this->assertArrayHasKey('submenus', $outros);
        $this->assertSame(__('Demais releases'), $outros['submenus'][0]['title'] ?? null);
    }

    public function test_releases_mesmo_dia_ordenam_por_sufixo(): void
    {
        $entries = DocumentationCatalog::discoverReleaseEntries();
        $sortKeys = array_column($entries, 'sort_key');

        $june7 = array_values(array_filter($sortKeys, static fn (string $key): bool => str_starts_with($key, '20260607')));
        if (count($june7) >= 2) {
            $sorted = $june7;
            rsort($sorted, SORT_STRING);
            $this->assertSame($sorted, $june7);
        }

        $this->assertContains('20260607', $sortKeys);
        $this->assertContains('20260607a', $sortKeys);
    }
}
