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
     * Cenário: utilizador municipal com filtros aplicados — abrir em Diagnóstico (fluxo consultoria).
     */
    #[Test]
    public function resolve_initial_tab_municipal_com_ano_vai_para_diagnostico(): void
    {
        $user = new User(['role' => UserRole::Municipal]);

        $tab = AnalyticsTabCatalog::resolveInitialTab('tab_invalida', $user, true);

        $this->assertSame('municipality_health', $tab);
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
     * Cenário: grupos de navegação — consultoria antes de cadastro no menu.
     */
    #[Test]
    public function groups_consultoria_lista_financas_primeiro(): void
    {
        $groups = AnalyticsTabCatalog::groups();

        $this->assertSame('consultoria', $groups[0]['id']);
        $this->assertContains('discrepancies', $groups[0]['tabs']);
    }
}
