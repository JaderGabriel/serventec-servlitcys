<?php

namespace Tests\Unit;

use App\Support\Ieducar\DiscrepanciesCsvRowsBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscrepanciesCsvRowsBuilderTest extends TestCase
{
    #[Test]
    public function exporta_linhas_por_escola_e_agregado_sem_detalhe(): void
    {
        $rows = DiscrepanciesCsvRowsBuilder::fromSnapshot([
            'checks' => [
                [
                    'id' => 'sem_raca',
                    'title' => 'Sem raça',
                    'correction' => 'Preencher raça',
                    'total' => 5,
                    'perda_estimada_anual' => 1000.0,
                    'ganho_potencial_anual' => 1000.0,
                    'school_rows' => [
                        ['escola_id' => '10', 'escola' => 'Escola A', 'total' => 3],
                        ['escola_id' => '20', 'escola' => 'Escola B', 'total' => 2],
                    ],
                ],
                [
                    'id' => 'rede_ociosidade',
                    'title' => 'Ociosidade',
                    'total' => 120,
                    'perda_estimada_anual' => 500.0,
                    'ganho_potencial_anual' => 500.0,
                    'school_rows' => [],
                ],
            ],
        ]);

        $this->assertCount(3, $rows);
        $this->assertSame('sem_raca', $rows[0]['check_id']);
        $this->assertSame(3, $rows[0]['total']);
        $this->assertFalse($rows[0]['agregado']);
        $this->assertTrue($rows[2]['agregado']);
        $this->assertSame(120, $rows[2]['total']);
    }
}
