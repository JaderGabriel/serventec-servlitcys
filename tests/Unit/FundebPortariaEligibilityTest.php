<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebPortariaEligibility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebPortariaEligibilityTest extends TestCase
{
    #[Test]
    public function badges_refletem_complementacoes_da_portaria(): void
    {
        $badges = FundebPortariaEligibility::badges([
            'complementacao_vaaf' => 1000.0,
            'complementacao_vaat' => null,
            'complementacao_vaar' => 500.0,
        ]);

        $this->assertTrue($badges['vaaf']);
        $this->assertFalse($badges['vaat']);
        $this->assertTrue($badges['vaar']);
    }
}
