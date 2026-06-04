<?php

namespace Tests\Unit;

use App\Services\Cadunico\CadunicoFinanceScenarioBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoFinanceScenarioBuilderTest extends TestCase
{
    #[Test]
    public function gera_cenarios_quando_ha_lacuna_e_vaaf(): void
    {
        $result = CadunicoFinanceScenarioBuilder::build(
            100,
            5000.0,
            2000,
            1800,
            [
                'nee_matriculas' => 50,
                'alunos_nee' => 40,
                'matriculas_aee_sem_cadastro' => 10,
                'alunos_aee_sem_cadastro' => 8,
            ],
        );

        $this->assertTrue($result['available']);
        $this->assertNotEmpty($result['itens']);
        $this->assertNotNull($result['total_cenarios_label'] ?? null);
    }

    #[Test]
    public function indisponivel_sem_lacuna(): void
    {
        $result = CadunicoFinanceScenarioBuilder::build(0, 5000.0, 100, 100);

        $this->assertFalse($result['available']);
    }
}
