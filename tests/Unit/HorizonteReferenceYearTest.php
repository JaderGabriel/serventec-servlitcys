<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteReferenceYear;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteReferenceYearTest extends TestCase
{
    #[Test]
    public function empty_env_uses_previous_calendar_year_not_2000(): void
    {
        putenv('HORIZONTE_REFERENCE_YEAR=');

        $expected = (int) date('Y') - 1;

        $this->assertSame($expected, HorizonteReferenceYear::resolve());
        $this->assertNotSame(2000, HorizonteReferenceYear::resolve());
    }

    #[Test]
    public function explicit_env_year_is_respected(): void
    {
        putenv('HORIZONTE_REFERENCE_YEAR=2024');

        $this->assertSame(2024, HorizonteReferenceYear::resolve());

        putenv('HORIZONTE_REFERENCE_YEAR');
    }

    #[Test]
    public function invalid_env_falls_back_to_suggested_year(): void
    {
        putenv('HORIZONTE_REFERENCE_YEAR=0');

        $this->assertSame((int) date('Y') - 1, HorizonteReferenceYear::resolve());

        putenv('HORIZONTE_REFERENCE_YEAR');
    }
}
