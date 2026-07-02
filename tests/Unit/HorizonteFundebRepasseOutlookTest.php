<?php

namespace Tests\Unit;

use App\Models\MunicipalTransferSnapshot;
use App\Support\Horizonte\HorizonteFundebRepasseOutlook;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class HorizonteFundebRepasseOutlookTest extends TestCase
{
    #[Test]
    public function nao_retorna_quando_ano_corrente_igual_referencia(): void
    {
        $year = HorizonteFundebRepasseOutlook::currentYear();

        $out = HorizonteFundebRepasseOutlook::byIbge($year, null, [], [], []);

        $this->assertSame([], $out);
    }

    #[Test]
    public function build_for_ibge_mescla_observado_e_previsao(): void
    {
        $currentYear = HorizonteFundebRepasseOutlook::currentYear();
        $method = new ReflectionMethod(HorizonteFundebRepasseOutlook::class, 'buildForIbge');

        $pack = $method->invoke(
            new HorizonteFundebRepasseOutlook,
            '2921500',
            $currentYear,
            [
                'complementacao_total' => 0.0,
                'receita_total' => 100000.0,
                'matriculas_base' => 20,
                'vaaf' => 5000.0,
                'ano' => $currentYear,
            ],
            null,
            null,
            ['observed' => 25000.0, 'rows' => [
                MunicipalTransferSnapshot::make([
                    'ibge_municipio' => '2921500',
                    'ano' => $currentYear,
                    'fonte' => 'tesouro_csv',
                    'programa_id' => 'fundeb',
                    'valor' => 25000.0,
                    'meta' => ['mensal' => [1 => 10000.0, 3 => 15000.0]],
                    'imported_at' => now()->setDate($currentYear, 4, 12),
                ]),
            ]],
        );

        $this->assertNotNull($pack);
        $this->assertSame($currentYear, $pack['ano']);
        $this->assertSame(25000.0, $pack['observed']);
        $this->assertSame(100000.0, $pack['expected']);
        $this->assertSame('portaria_receita', $pack['expected_source']);
        $this->assertSame(25.0, $pack['pct_done']);
        $this->assertSame(3, $pack['last_transfer_month']);
        $this->assertSame('mar/'.$currentYear, $pack['last_transfer_label']);
        $this->assertSame(100000.0, $pack['portaria_receita']);
        $this->assertSame(20, $pack['portaria_matriculas']);
        $this->assertNotEmpty($pack['outlook_detail']);
    }

    #[Test]
    public function matriculas_portaria_nao_usa_censo_como_fallback(): void
    {
        $currentYear = HorizonteFundebRepasseOutlook::currentYear();
        $method = new ReflectionMethod(HorizonteFundebRepasseOutlook::class, 'buildForIbge');

        $pack = $method->invoke(
            new HorizonteFundebRepasseOutlook,
            '2921500',
            $currentYear,
            [
                'complementacao_total' => 0.0,
                'receita_total' => 100000.0,
                'matriculas_base' => null,
                'matriculas_fonte' => null,
                'vaaf' => 5000.0,
                'ano' => $currentYear,
            ],
            null,
            ['matriculas_total' => 999, 'ano' => $currentYear - 1],
            ['observed' => 1000.0, 'rows' => []],
        );

        $this->assertNotNull($pack);
        $this->assertNull($pack['portaria_matriculas']);
        $this->assertSame(100000.0, $pack['expected']);
    }
}
