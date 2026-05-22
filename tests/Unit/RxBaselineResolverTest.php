<?php

namespace Tests\Unit;

use App\Support\Rx\RxBaselineResolver;
use PHPUnit\Framework\TestCase;

final class RxBaselineResolverTest extends TestCase
{
    public function test_multiplier_zero_saltos(): void
    {
        $this->assertSame(1.0, RxBaselineResolver::multiplierForSaltos(0, 5.0));
    }

    public function test_multiplier_two_saltos_five_percent(): void
    {
        $this->assertEqualsWithDelta(1.1025, RxBaselineResolver::multiplierForSaltos(2, 5.0), 0.0001);
    }
}
