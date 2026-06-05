<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Dashboard\AnalyticsTabCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catálogo de abas do painel — cenário C: Resumo (Diagnóstico) como entrada transversal.
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
        $this->assertContains('comparativo', $keys);
        $this->assertContains('cadunico_previsao', $keys);
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
     * Cenário: tab inválida com filtros prontos — abrir em Diagnóstico (Resumo).
     */
    #[Test]
    public function resolve_initial_tab_com_ano_usa_diagnostico(): void
    {
        $user = new User(['role' => UserRole::Municipal]);

        $tab = AnalyticsTabCatalog::resolveInitialTab('tab_invalida', $user, true);

        $this->assertSame('municipality_health', $tab);
    }

    /**
     * Cenário: ano não aplicado — visão geral (cadastro); com ano — Diagnóstico.
     */
    #[Test]
    public function resolve_initial_tab_sem_ano_usa_overview_com_ano_diagnostico(): void
    {
        $admin = new User(['role' => UserRole::Admin]);

        $this->assertSame('municipality_health', AnalyticsTabCatalog::resolveInitialTab('x', $admin, true));

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
     * Cenário: grupos de navegação — Resumo primeiro; Diagnóstico fora de Finanças.
     */
    #[Test]
    public function groups_resumo_primeiro_e_financas_sem_diagnostico(): void
    {
        $groups = AnalyticsTabCatalog::groups();

        $this->assertCount(5, $groups);
        $this->assertSame('resumo', $groups[0]['id']);
        $this->assertSame(['municipality_health'], $groups[0]['tabs']);
        $this->assertSame('cadastro', $groups[1]['id']);
        $this->assertContains('overview', $groups[1]['tabs']);
        $this->assertSame('pedagogico', $groups[2]['id']);
        $this->assertSame('censo', $groups[3]['id']);
        $this->assertContains('work_done', $groups[3]['tabs']);
        $this->assertSame('consultoria', $groups[4]['id']);
        $this->assertContains('fundeb', $groups[4]['tabs']);
        $this->assertNotContains('municipality_health', $groups[4]['tabs']);
        $this->assertSame(
            'discrepancies',
            $groups[4]['tabs'][0] ?? null,
        );
        $this->assertSame(
            'fundeb',
            $groups[4]['tabs'][1] ?? null,
        );
    }

    /**
     * Cenário: payload Alpine para menu em dois níveis (área → sub-aba).
     */
    #[Test]
    public function navigation_payload_agrupa_abas_e_mapeia_grupo(): void
    {
        $payload = AnalyticsTabCatalog::navigationPayload();

        $this->assertCount(5, $payload['groups']);
        $this->assertSame('resumo', $payload['groups'][0]['id']);
        $this->assertSame('resumo', $payload['tabToGroup']['municipality_health']);
        $this->assertSame('censo', $payload['tabToGroup']['work_done']);
        $this->assertSame('consultoria', $payload['tabToGroup']['fundeb']);
        $this->assertSame('cadastro', $payload['tabToGroup']['overview']);
        $this->assertArrayHasKey('municipality_health', $payload['tabHints']);
        $this->assertNotEmpty($payload['groups'][0]['short']);
        $this->assertNotEmpty($payload['groups'][0]['tone']);
    }

    #[Test]
    public function is_resumo_group_tab_identifica_diagnostico(): void
    {
        $this->assertTrue(AnalyticsTabCatalog::isResumoGroupTab('municipality_health'));
        $this->assertFalse(AnalyticsTabCatalog::isResumoGroupTab('fundeb'));
    }
}
