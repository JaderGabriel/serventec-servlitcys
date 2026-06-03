<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebImpactMethodology;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebImpactMethodologyTest extends TestCase
{
    #[Test]
    public function panel_inclui_ponderacoes_e_portarias(): void
    {
        $panel = FundebImpactMethodology::panel();

        $this->assertArrayHasKey('ponderacoes', $panel);
        $this->assertArrayHasKey('portarias', $panel);
        $this->assertNotEmpty($panel['ponderacoes']);
        $this->assertNotEmpty($panel['portarias']);
        $this->assertArrayHasKey('distribuicao_legal', $panel);
    }
}
