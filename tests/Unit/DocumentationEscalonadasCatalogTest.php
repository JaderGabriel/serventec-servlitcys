<?php

namespace Tests\Unit;

use App\Support\Admin\DocumentationCatalog;
use App\Support\Admin\DocumentationEscalonadasCatalog;
use Tests\TestCase;

class DocumentationEscalonadasCatalogTest extends TestCase
{
    public function test_monthly_documents_include_june_and_may(): void
    {
        $months = DocumentationEscalonadasCatalog::monthlyDocuments();
        $ids = array_column($months, 'id');

        $this->assertSame(['202606', '202605'], $ids);
        $this->assertSame('docs/ENTREGAS_ESCALONADAS_JUNHO_2026.md', $months[0]['path']);
    }

    public function test_releases_for_june_2026_match_discovered_release_files(): void
    {
        $releases = DocumentationEscalonadasCatalog::releasesForMonth('202606');

        $this->assertGreaterThanOrEqual(18, count($releases));
        $this->assertSame('docs/RELEASE_20260601_ATLAS.md', $releases[0]['path']);
        $this->assertSame('3.5.0', $releases[0]['version']);
        $this->assertSame('20260601-Atlas', $releases[0]['tag']);

        $last = $releases[array_key_last($releases)];
        $this->assertSame('docs/RELEASE_20260611_HARMONIA.md', $last['path']);
    }

    public function test_menu_section_lists_index_and_monthly_submenu(): void
    {
        $section = DocumentationEscalonadasCatalog::menuSection();

        $this->assertSame(__('Entregas escalonadas'), $section['title']);
        $this->assertSame(DocumentationEscalonadasCatalog::indexPath(), $section['items'][0]['path']);
        $this->assertSame(__('Por mês'), $section['submenus'][0]['title']);
        $this->assertCount(2, $section['submenus'][0]['items']);
    }

    public function test_catalog_includes_entregas_escalonadas_section(): void
    {
        $titles = array_column(DocumentationCatalog::sections(), 'title');

        $this->assertContains(__('Entregas escalonadas'), $titles);
        $this->assertNotContains(
            'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md',
            array_column(
                collect(DocumentationCatalog::sections())->firstWhere('title', __('Arquivo'))['items'] ?? [],
                'path'
            )
        );
    }
}
