<?php

namespace Tests\Unit;

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
            ['observed' => 25000.0, 'rows' => []],
        );

        $this->assertNotNull($pack);
        $this->assertSame($currentYear, $pack['ano']);
        $this->assertSame(25000.0, $pack['observed']);
        $this->assertSame(100000.0, $pack['expected']);
        $this->assertSame('portaria_receita', $pack['expected_source']);
        $this->assertSame(25.0, $pack['pct_done']);
    }
}
