<?php

namespace Tests\Unit;

use App\Support\Console\ArtisanCommandsCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ArtisanCommandsCatalogTest extends TestCase
{
    #[Test]
    public function catalogo_tem_categorias_e_comandos_do_projeto(): void
    {
        $categories = ArtisanCommandsCatalog::categories();

        $this->assertNotEmpty($categories);
        $names = ArtisanCommandsCatalog::commandNames();

        $this->assertContains('fundeb:import-api', $names);
        $this->assertContains('ieducar:schema-probe', $names);
        $this->assertContains('app:sync-school-unit-geos', $names);
    }
}
