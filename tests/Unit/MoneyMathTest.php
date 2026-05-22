<?php

namespace Tests\Unit;

use App\Support\Finance\MoneyMath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MoneyMathTest extends TestCase
{
    #[Test]
    public function multiply_vaaf_rounds_to_two_decimals(): void
    {
        $this->assertSame(450000.0, MoneyMath::multiplyVaaf(100, 4500.0));
        $this->assertSame(1234.57, MoneyMath::multiplyVaaf(3, 411.5234));
    }

    #[Test]
    public function impact_from_occurrences_uses_vaaf_and_weight(): void
    {
        $this->assertSame(15750.0, MoneyMath::impactFromOccurrences(10, 4500.0, 0.35));
    }

    #[Test]
    public function format_brl_uses_brazilian_locale_pattern(): void
    {
        $this->assertSame('R$ 1.234,56', MoneyMath::formatBrl(1234.56));
    }
}
