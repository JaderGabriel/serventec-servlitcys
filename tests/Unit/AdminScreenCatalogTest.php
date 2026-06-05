<?php

namespace Tests\Unit;

use App\Support\Admin\AdminScreenCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminScreenCatalogTest extends TestCase
{
    #[Test]
    public function grupo_municipios_tem_cidades_conexoes_e_vaaf(): void
    {
        $keys = array_column(AdminScreenCatalog::navItems(AdminScreenCatalog::GROUP_MUNICIPALITIES), 'key');

        $this->assertContains('cities', $keys);
        $this->assertContains('connections', $keys);
        $this->assertContains('fundeb', $keys);
    }

    #[Test]
    public function grupo_administracao_tem_documentos_e_consentimentos(): void
    {
        $keys = array_column(AdminScreenCatalog::navItems(AdminScreenCatalog::GROUP_ADMINISTRATION), 'key');

        $this->assertContains('legal-documents', $keys);
        $this->assertContains('legal-consents', $keys);
    }

    #[Test]
    public function accent_rose_para_legal_consents(): void
    {
        $this->assertSame('rose', AdminScreenCatalog::shellAccentForScreen(
            AdminScreenCatalog::GROUP_ADMINISTRATION,
            'legal-consents',
        ));
    }
}
