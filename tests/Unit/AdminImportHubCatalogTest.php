<?php

namespace Tests\Unit;

use App\Support\Admin\AdminImportHubCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminImportHubCatalogTest extends TestCase
{
    #[Test]
    public function navegacao_inclui_aba_repasses_com_fragmento(): void
    {
        $items = AdminImportHubCatalog::navItems();
        $keys = array_column($items, 'key');

        $this->assertContains('repasses', $keys);
        $this->assertContains('hub', $keys);
        $this->assertSame('Consultoria', collect($items)->firstWhere('key', 'hub')['label'] ?? '');

        $repasses = collect($items)->firstWhere('key', 'repasses');
        $this->assertNotNull($repasses);
        $href = AdminImportHubCatalog::navHref($repasses);
        $this->assertStringContainsString('hub=repasses', $href);
        $this->assertStringEndsWith('#source-repasses_tesouro', $href);
    }

    #[Test]
    public function resolve_hub_active_valida_chave(): void
    {
        $this->assertSame('repasses', AdminImportHubCatalog::resolveHubActive('repasses'));
        $this->assertSame('hub', AdminImportHubCatalog::resolveHubActive('invalido'));
        $this->assertSame('hub', AdminImportHubCatalog::resolveHubActive(null));
    }
}
