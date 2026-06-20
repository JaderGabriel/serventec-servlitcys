<?php

namespace Tests\Unit;

use App\Support\Brazil\BrazilUfNames;
use PHPUnit\Framework\TestCase;

final class BrazilUfNamesTest extends TestCase
{
    public function test_name_and_label_for_known_uf(): void
    {
        $this->assertSame('Bahia', BrazilUfNames::name('BA'));
        $this->assertSame('BA — Bahia', BrazilUfNames::label('ba'));
    }

    public function test_all_contains_twenty_seven_ufs(): void
    {
        $this->assertCount(27, BrazilUfNames::all());
        $this->assertArrayHasKey('SP', BrazilUfNames::all());
    }
}
