<?php

namespace Tests\Unit;

use App\Support\Dashboard\MunicipalityHealthSections;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalityHealthSectionsTest extends TestCase
{
    #[Test]
    public function deferred_sections_lista_tres_blocos(): void
    {
        $this->assertSame(
            ['fundeb', 'programas', 'tematico'],
            MunicipalityHealthSections::deferred(),
        );
    }

    #[Test]
    public function is_valid_rejeita_desconhecido(): void
    {
        $this->assertTrue(MunicipalityHealthSections::isValid('fundeb'));
        $this->assertFalse(MunicipalityHealthSections::isValid('mapa'));
    }

    #[Test]
    public function modo_estrategico_e_defeito_sem_progressivo(): void
    {
        config([
            'analytics.municipality_health_mode' => 'strategic',
            'analytics.municipality_health_progressive_sections' => false,
        ]);

        $this->assertSame(MunicipalityHealthSections::MODE_STRATEGIC, MunicipalityHealthSections::mode());
        $this->assertTrue(MunicipalityHealthSections::strategicEnabled());
        $this->assertFalse(MunicipalityHealthSections::progressiveEnabled());
    }

    #[Test]
    public function modo_progressivo_explicito_activa_ajax(): void
    {
        config(['analytics.municipality_health_mode' => 'progressive']);

        $this->assertTrue(MunicipalityHealthSections::progressiveEnabled());
        $this->assertFalse(MunicipalityHealthSections::strategicEnabled());
    }
}
