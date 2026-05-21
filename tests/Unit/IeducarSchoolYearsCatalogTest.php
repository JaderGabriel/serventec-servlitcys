<?php

namespace Tests\Unit;

use App\Support\Ieducar\IeducarWorkActivityQueries;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarSchoolYearsCatalogTest extends TestCase
{
    #[Test]
    public function interpret_year_status_detects_closed_values(): void
    {
        $ref = new \ReflectionClass(IeducarWorkActivityQueries::class);
        $method = $ref->getMethod('interpretYearStatusValue');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 'fechado'));
        $this->assertTrue($method->invoke(null, 'encerrado'));
        $this->assertFalse($method->invoke(null, 'em andamento'));
        $this->assertFalse($method->invoke(null, 'aberto'));
    }
}
