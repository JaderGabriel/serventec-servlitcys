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
        $this->assertContains('horizonte:fortnightly-feed', $names);
        $this->assertContains('saeb:import-planilhas-inep', $names);
        $this->assertContains('module-monitor:collect', $names);
    }

    #[Test]
    public function confirm_slugs_lista_comandos_destrutivos(): void
    {
        $slugs = ArtisanCommandsCatalog::confirmSlugs();

        $this->assertNotEmpty($slugs);
        $commands = array_column($slugs, 'command');
        $this->assertContains('app:flush-processing-queue', $commands);
        $this->assertContains('funding:rebuild-finance-realtime', $commands);
        $this->assertContains('cities:reencrypt-db-passwords', $commands);

        $flush = collect($slugs)->firstWhere('command', 'app:flush-processing-queue');
        $this->assertNotEmpty($flush['slug']);
        $this->assertStringContainsString('--confirm=', $flush['example']);
    }
}
