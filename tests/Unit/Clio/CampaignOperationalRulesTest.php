<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\CampaignOperationalRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignOperationalRulesTest extends TestCase
{
    #[Test]
    public function escola_municipal_em_atividade_e_apta(): void
    {
        $this->assertTrue(CampaignOperationalRules::isOperationallyEligible(
            'Em atividade',
            'Municipal',
            ['location' => 'Urbana'],
        ));
    }

    #[Test]
    public function escola_filantropica_com_parceria_municipal_e_apta(): void
    {
        $this->assertTrue(CampaignOperationalRules::isOperationallyEligible(
            'Em atividade',
            'Privada',
            [
                'location' => 'Rural',
                'private_category' => 'Filantrópica',
                'partnership_authority' => 'Municipal',
            ],
        ));
    }

    #[Test]
    public function filantropica_sem_parceria_municipal_nao_e_apta(): void
    {
        $this->assertFalse(CampaignOperationalRules::isSchoolApta('Privada', [
            'location' => 'Urbana',
            'private_category' => 'Filantrópica',
            'partnership_authority' => 'Estadual',
        ]));
    }

    #[Test]
    public function estadual_nao_e_apta(): void
    {
        $this->assertFalse(CampaignOperationalRules::isSchoolApta('Estadual', [
            'location' => 'Urbana',
        ]));
    }

    #[Test]
    public function municipal_e_filantropica_nao_sao_and(): void
    {
        // Dependência municipal não exige categoria filantrópica.
        $this->assertTrue(CampaignOperationalRules::isSchoolApta('Municipal', [
            'location' => 'Urbana',
            'private_category' => '',
        ]));
    }

    #[Test]
    public function parcial_abaixo_de_35_e_integral_a_partir_de_35(): void
    {
        $this->assertTrue(CampaignOperationalRules::isPartialHours(34.0));
        $this->assertFalse(CampaignOperationalRules::isIntegralHours(34.0));
        $this->assertTrue(CampaignOperationalRules::isIntegralHours(35.0));
        $this->assertTrue(CampaignOperationalRules::isIntegralHours(20.0, 'Integral'));
    }

    #[Test]
    public function eja_alerta_abaixo_de_20_e_ac_piso_15(): void
    {
        $this->assertTrue(CampaignOperationalRules::isEjaLowHours(19.0));
        $this->assertFalse(CampaignOperationalRules::isEjaLowHours(20.0));
        $this->assertTrue(CampaignOperationalRules::isAcEligibleForIntegralProxy(15.0));
        $this->assertTrue(CampaignOperationalRules::isAcBelowFloor(14.0));
        $this->assertFalse(CampaignOperationalRules::isAcBelowFloor(15.0));
    }

    #[Test]
    public function pnate_exclui_urbano_urbano_quando_ha_residencia(): void
    {
        $this->assertSame(
            'excluido_urbano_urbano',
            CampaignOperationalRules::classifyPnate(true, 'Urbana', 'Urbana', true),
        );
        $this->assertSame(
            'elegivel',
            CampaignOperationalRules::classifyPnate(true, 'Urbana', 'Rural', true),
        );
        $this->assertSame(
            'elegivel',
            CampaignOperationalRules::classifyPnate(true, 'Urbana', null, false),
        );
        $this->assertSame(
            'sem_transporte',
            CampaignOperationalRules::classifyPnate(false, 'Rural', 'Rural', true),
        );
    }
}
