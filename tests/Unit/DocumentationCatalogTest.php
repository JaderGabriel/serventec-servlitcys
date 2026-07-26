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

    public function test_catalog_sections_have_visual_identity(): void
    {
        $sections = DocumentationCatalog::sections();

        foreach ($sections as $section) {
            $this->assertArrayHasKey('key', $section);
            $this->assertArrayHasKey('icon', $section);
            $this->assertArrayHasKey('tone', $section);
            $this->assertArrayHasKey('analogy', $section);
            $this->assertNotSame('', $section['icon']);
            $this->assertNotSame('', $section['tone']);
        }

        $architecture = collect($sections)->firstWhere('key', 'architecture');
        $this->assertNotNull($architecture);
        $paths = array_column($architecture['items'] ?? [], 'path');
        $this->assertContains('docs/ANALISE_PADROES_LARAVEL.md', $paths);
    }

    public function test_catalog_sections_follow_logical_order(): void
    {
        $sections = DocumentationCatalog::sections();
        $titles = array_column($sections, 'title');

        $this->assertSame(__('1 · Entrada'), $titles[0]);
        $this->assertContains(__('5 · Horizonte'), $titles);
        $this->assertContains(__('Entregas escalonadas'), $titles);
        $this->assertContains(__('Arquivo'), $titles);

        $horizonte = collect($sections)->firstWhere('key', 'horizonte');
        $this->assertNotNull($horizonte);
        $horizontePaths = array_column($horizonte['items'] ?? [], 'path');
        $this->assertContains('docs/HORIZONTE.md', $horizontePaths);
        $this->assertContains('docs/modulos/MODULO_HORIZONTE.md', $horizontePaths);
        $this->assertContains('docs/ROADMAP_HORIZONTE.md', $horizontePaths);
        $this->assertSame('docs/modulos/MODULO_HORIZONTE.md', $horizontePaths[0] ?? null);
        $this->assertSame('docs/ROADMAP_HORIZONTE.md', $horizontePaths[1] ?? null);

        $entry = collect($sections)->firstWhere('key', 'entry');
        $this->assertNotNull($entry);
        $entryPaths = array_column($entry['items'] ?? [], 'path');
        $this->assertContains('docs/ROADMAP_INDICE.md', $entryPaths);
        $this->assertNotContains('docs/ROADMAP_CANTEIRO.md', $entryPaths);
    }

    public function test_integrations_section_lists_n8n_orchestration_doc(): void
    {
        $integrations = collect(DocumentationCatalog::sections())->firstWhere('key', 'integrations');
        $this->assertNotNull($integrations);
        $paths = array_column($integrations['items'] ?? [], 'path');
        $this->assertContains('docs/ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md', $paths);
        $this->assertContains(
            'docs/ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md',
            DocumentationCatalog::adminOnlyPaths(),
        );
        $this->assertSame(
            'docs/ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md',
            DocumentationCatalog::resolveReadablePath('ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md'),
        );
    }

    public function test_legacy_roadmap_aliases_resolve_to_canonical_paths(): void
    {
        $this->assertSame(
            'docs/ROADMAP_CANTEIRO.md',
            DocumentationCatalog::resolveReadablePath('docs/ROADMAP_OBRAS_EDUCACAO.md'),
        );
        $this->assertSame(
            'docs/ROADMAP_EDUCACENSO.md',
            DocumentationCatalog::resolveReadablePath('docs/ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md'),
        );
        $this->assertSame(
            'docs/ROADMAP_BASES_FINANCEIRAS.md',
            DocumentationCatalog::resolveReadablePath('docs/ROADMAP_BASES_CALCULOS_FINANCEIROS.md'),
        );
        $this->assertSame(
            'docs/ROADMAP_INCLUSAO.md',
            DocumentationCatalog::resolveReadablePath('docs/DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md'),
        );
    }

    public function test_module_sections_place_roadmap_after_landing(): void
    {
        $expected = [
            'analytics' => 'docs/ROADMAP_ANALYTICS.md',
            'horizonte' => 'docs/ROADMAP_HORIZONTE.md',
            'cadunico' => 'docs/ROADMAP_CADUNICO.md',
            'pedagogia' => 'docs/ROADMAP_PEDAGOGIA_SAEB.md',
            'rx' => 'docs/ROADMAP_RX_CENSO.md',
            'clio' => 'docs/ROADMAP_CLIO.md',
            'funding' => 'docs/ROADMAP_FUNDEB.md',
        ];

        $sections = collect(DocumentationCatalog::sections())->keyBy('key');

        foreach ($expected as $key => $roadmapPath) {
            $section = $sections->get($key);
            $this->assertNotNull($section, "Missing section {$key}");
            $paths = array_column($section['items'] ?? [], 'path');
            $this->assertGreaterThanOrEqual(2, count($paths), "Section {$key} needs landing + roadmap");
            $this->assertStringContainsString('modulos/MODULO_', (string) $paths[0]);
            $this->assertSame($roadmapPath, $paths[1]);
        }
    }

    public function test_flat_entries_includes_module_roadmaps(): void
    {
        $paths = array_column(DocumentationCatalog::flatEntriesForUser(null), 'path');

        foreach ([
            'docs/ROADMAP_INDICE.md',
            'docs/ROADMAP_ANALYTICS.md',
            'docs/ROADMAP_HORIZONTE.md',
            'docs/ROADMAP_CANTEIRO.md',
            'docs/ROADMAP_CLIO.md',
            'docs/ROADMAP_POWERBI.md',
        ] as $path) {
            $this->assertContains($path, $paths);
        }
    }

    public function test_flat_entries_includes_powerbi_once(): void
    {
        $paths = array_column(DocumentationCatalog::flatEntriesForUser(null), 'path');
        $powerBi = array_filter($paths, static fn (string $p): bool => $p === 'docs/POWERBI.md');

        $this->assertCount(1, $powerBi);
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

    public function test_flat_entries_includes_powerbi_document(): void
    {
        $paths = array_column(DocumentationCatalog::flatEntriesForUser(null), 'path');

        $this->assertContains('docs/POWERBI.md', $paths);
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
