<?php

namespace Tests\Unit;

use App\Support\Funding\FinanceRealtimeYearEndOutlook;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FinanceRealtimeYearEndOutlookTest extends TestCase
{
    #[Test]
    public function gap_positivo_indica_falta_quando_projecao_abaixo_da_portaria(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            1_200_000.0,
            180_000.0,
            (int) date('Y'),
            ['months_with_transfers' => 3, 'monthly' => 100_000.0],
        );

        $this->assertSame('risk', $outlook['outlook']);
        $this->assertSame('shortfall', $outlook['gap_sign']);
        $this->assertGreaterThan(0, $outlook['gap_until_december']);
    }

    #[Test]
    public function gap_negativo_indica_sobra_quando_projecao_acima_da_portaria(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            1_000_000.0,
            550_000.0,
            (int) date('Y'),
            ['months_with_transfers' => 6, 'monthly' => 83_333.33],
        );

        $this->assertSame('surplus', $outlook['outlook']);
        $this->assertSame('surplus', $outlook['gap_sign']);
        $this->assertLessThan(0, $outlook['gap_until_december']);
    }

    #[Test]
    public function classifica_risco_quando_projecao_fica_abaixo_da_margem(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            1_200_000.0,
            180_000.0,
            (int) date('Y'),
            ['months_with_transfers' => 3, 'monthly' => 100_000.0],
        );

        $this->assertSame('risk', $outlook['outlook']);
        $this->assertSame(1_200_000.0, $outlook['need_until_december']);
        $this->assertSame(1_020_000.0, $outlook['balance_to_repass']);
        $this->assertLessThan(1_200_000.0 * 0.98, $outlook['projected_repass_until_december']);
    }

    #[Test]
    public function classifica_sobras_quando_projecao_supera_necessidade(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            1_000_000.0,
            550_000.0,
            (int) date('Y'),
            ['months_with_transfers' => 6, 'monthly' => 83_333.33],
        );

        $this->assertSame('surplus', $outlook['outlook']);
        $this->assertGreaterThan(1_020_000.0, $outlook['projected_repass_until_december']);
    }

    #[Test]
    public function classifica_proximo_quando_dentro_de_dois_por_cento(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            1_000_000.0,
            330_000.0,
            (int) date('Y'),
            ['months_with_transfers' => 4, 'monthly' => 83_333.33],
        );

        $this->assertSame('close', $outlook['outlook']);
        $this->assertGreaterThanOrEqual(980_000.0, $outlook['projected_repass_until_december']);
        $this->assertLessThanOrEqual(1_020_000.0, $outlook['projected_repass_until_december']);
    }

    #[Test]
    public function ano_encerrado_usa_repassado_como_projecao(): void
    {
        $outlook = FinanceRealtimeYearEndOutlook::build(
            900_000.0,
            850_000.0,
            (int) date('Y') - 1,
            ['months_with_transfers' => 12, 'monthly' => 75_000.0],
        );

        $this->assertSame(850_000.0, $outlook['projected_repass_until_december']);
    }
}
