<?php

namespace Tests\Unit;

use App\Support\Analytics\FinanceComparativoInformeBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FinanceComparativoInformeBuilderTest extends TestCase
{
    #[Test]
    public function build_gera_blocos_quando_relatorio_disponivel(): void
    {
        $informe = FinanceComparativoInformeBuilder::build([
            'available' => true,
            'base_year' => 2025,
            'prev_year' => 2024,
            'next_year' => 2026,
            'variacoes' => [
                [
                    'label' => 'Matrículas',
                    'kind' => 'count',
                    'base_fmt' => '1.000',
                    'delta_label' => '+5%',
                    'leitura' => 'Avanço',
                    'direction' => 'up',
                ],
                [
                    'label' => 'Recursos',
                    'kind' => 'money',
                    'base_fmt' => 'R$ 1,00',
                    'delta_label' => '—',
                    'leitura' => 'Estável',
                    'direction' => 'flat',
                ],
            ],
            'base_year_detail' => [
                'previsao_base_label' => 'R$ 10,00',
                'vaaf_label' => 'R$ 5,00',
                'matriculas_fmt' => '100',
            ],
            'next_year_projection' => [
                'available' => true,
                'year' => 2026,
                'previsao_label' => 'R$ 11,00',
                'delta_label' => '+10%',
                'note' => 'Nota',
                'tone' => 'emerald',
            ],
            'alerts' => [
                ['title' => 'Teste', 'message' => 'Msg', 'tone' => 'warning'],
            ],
        ]);

        $this->assertTrue($informe['available']);
        $this->assertNotEmpty($informe['blocos']);
        $ids = array_column($informe['blocos'], 'id');
        $this->assertContains('evolucao_cadastro', $ids);
        $this->assertContains('impacto_financeiro', $ids);
    }

    #[Test]
    public function build_vazio_quando_indisponivel(): void
    {
        $informe = FinanceComparativoInformeBuilder::build(['available' => false]);

        $this->assertFalse($informe['available']);
        $this->assertSame([], $informe['blocos']);
    }
}
