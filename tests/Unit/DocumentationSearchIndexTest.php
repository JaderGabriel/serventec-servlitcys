<?php

namespace Tests\Unit;

use App\Support\Admin\DocumentationCatalog;
use App\Support\Admin\DocumentationSearchIndex;
use Tests\TestCase;

class DocumentationSearchIndexTest extends TestCase
{
    public function test_flat_entries_includes_powerbi_in_catalog(): void
    {
        $paths = array_column(DocumentationCatalog::flatEntriesForUser(null), 'path');

        $this->assertContains('docs/POWERBI.md', $paths);
    }

    public function test_search_returns_powerbi_document(): void
    {
        $results = app(DocumentationSearchIndex::class)->search(null, 'power bi', 10, 'documentation');

        $paths = array_column($results, 'path');
        $this->assertContains('docs/POWERBI.md', $paths);
    }

    public function test_search_respects_minimum_query_length(): void
    {
        $results = app(DocumentationSearchIndex::class)->search(null, 'p', 10, 'documentation');

        $this->assertSame([], $results);
    }

    public function test_search_result_includes_reader_url(): void
    {
        $results = app(DocumentationSearchIndex::class)->search(null, 'fundeb vaaf', 5, 'documentation');

        $this->assertNotEmpty($results);
        $first = $results[0];
        $this->assertArrayHasKey('url', $first);
        $this->assertStringContainsString('doc=docs%2F', $first['url']);
    }
}
