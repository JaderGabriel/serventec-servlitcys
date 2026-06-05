<?php

namespace Tests\Unit;

use App\Support\Admin\ImportHubThemeCatalog;
use App\Support\Admin\PublicDataImportCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportHubThemeCatalogTest extends TestCase
{
    #[Test]
    public function agrupa_fontes_do_hub_por_dominio(): void
    {
        $sections = ImportHubThemeCatalog::sectionsForSources(PublicDataImportCatalog::sources());

        $this->assertNotEmpty($sections);
        $domains = array_map(static fn (array $s): string => (string) $s['theme']['domain'], $sections);
        $this->assertContains('fundeb', $domains);
        $this->assertContains('funding', $domains);

        $fundeb = collect($sections)->firstWhere(static fn (array $s): bool => $s['theme']['domain'] === 'fundeb');
        $this->assertNotNull($fundeb);
        $this->assertSame('banknotes', $fundeb['theme']['icon']);
        $this->assertSame('amber', $fundeb['theme']['accent']);
        $this->assertSame('hub-fundeb', $fundeb['theme']['hub_anchor']);
    }

    #[Test]
    public function tema_fundeb_tem_rota_admin(): void
    {
        $theme = ImportHubThemeCatalog::themeForDomainValue('fundeb');

        $this->assertSame('admin.ieducar-compatibility.index', $theme['admin_route'] ?? null);
    }

    #[Test]
    public function tema_funding_tem_rota_hub_e_accent_emerald(): void
    {
        $theme = ImportHubThemeCatalog::themeForDomainValue('funding');

        $this->assertSame('admin.public-data.index', $theme['admin_route'] ?? null);
        $this->assertSame('emerald', $theme['accent']);
        $this->assertSame('banknotes', $theme['icon']);
    }
}
