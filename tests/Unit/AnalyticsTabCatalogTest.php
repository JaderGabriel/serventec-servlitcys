<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Dashboard\AnalyticsTabCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catálogo de abas do painel — ordem de consultoria e tab inicial por perfil.
 */
final class AnalyticsTabCatalogTest extends TestCase
{
    /**
     * Cenário: todas as abas de consultoria e cadastro estão registadas.
     * Impacto: evita 404 em lazy-load AJAX (dashboard.analytics.tab).
     */
    #[Test]
    public function tab_keys_inclui_diagnostico_e_discrepancias(): void
    {
        $keys = AnalyticsTabCatalog::tabKeys();

        $this->assertContains('municipality_health', $keys);
        $this->assertContains('discrepancies', $keys);
        $this->assertContains('fundeb', $keys);
        $this->assertContains('overview', $keys);
    }

    /**
     * Cenário: URL com ?tab=valor_invalido.
     * Esperado: isValidTab=false — controller usa fallback seguro.
     */
    #[Test]
    public function is_valid_tab_rejeita_chave_desconhecida(): void
    {
        $this->assertFalse(AnalyticsTabCatalog::isValidTab('nao_existe'));
        $this->assertTrue(AnalyticsTabCatalog::isValidTab('discrepancies'));
    }

    /**
     * Cenário: tab inválida com filtros prontos — abrir em Visão geral (primeira aba de cadastro).
     */
    #[Test]
    public function resolve_initial_tab_com_ano_usa_overview(): void
    {
        $user = new User(['role' => UserRole::Municipal]);

        $tab = AnalyticsTabCatalog::resolveInitialTab('tab_invalida', $user, true);

        $this->assertSame('overview', $tab);
    }

    /**
     * Cenário: admin ou ano não aplicado — visão geral primeiro (cadastro).
     */
    #[Test]
    public function resolve_initial_tab_sem_ano_ou_admin_usa_overview(): void
    {
        $admin = new User(['role' => UserRole::Admin]);

        $this->assertSame('overview', AnalyticsTabCatalog::resolveInitialTab('x', $admin, true));

        $municipal = new User(['role' => UserRole::Municipal]);
        $this->assertSame('overview', AnalyticsTabCatalog::resolveInitialTab('x', $municipal, false));
    }

    /**
     * Cenário: tab explícita válida na query string deve ser respeitada.
     */
    #[Test]
    public function resolve_initial_tab_respeita_tab_valido(): void
    {
        $user = new User(['role' => UserRole::Municipal]);

        $this->assertSame('fundeb', AnalyticsTabCatalog::resolveInitialTab('fundeb', $user, true));
    }

    /**
     * Cenário: grupos de navegação — cadastro antes de pedagógico e finanças.
     */
    #[Test]
    public function groups_cadastro_lista_primeiro(): void
    {
        $groups = AnalyticsTabCatalog::groups();

        $this->assertSame('cadastro', $groups[0]['id']);
        $this->assertContains('overview', $groups[0]['tabs']);
        $this->assertSame('pedagogico', $groups[1]['id']);
        $this->assertSame('consultoria', $groups[2]['id']);
        $this->assertContains('fundeb', $groups[2]['tabs']);
    }

    /**
     * Cenário: payload Alpine para menu em dois níveis (área → sub-aba).
     */
    #[Test]
    public function navigation_payload_agrupa_abas_e_mapeia_grupo(): void
    {
        $payload = AnalyticsTabCatalog::navigationPayload();

        $this->assertCount(3, $payload['groups']);
        $this->assertSame('cadastro', $payload['groups'][0]['id']);
        $this->assertSame('consultoria', $payload['tabToGroup']['fundeb']);
        $this->assertSame('cadastro', $payload['tabToGroup']['overview']);
        $this->assertArrayHasKey('municipality_health', $payload['tabHints']);
        $this->assertNotEmpty($payload['groups'][0]['short']);
        $this->assertNotEmpty($payload['groups'][0]['tone']);
    }
}
