<?php

namespace Tests\Unit;

use App\Services\Ieducar\FilterOptionsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilterOptionsServiceYearsTest extends TestCase
{
    #[Test]
    public function has_numeric_school_years_detects_placeholders_only(): void
    {
        $this->assertFalse(FilterOptionsService::hasNumericSchoolYears([
            '' => '— Selecione o ano letivo —',
            'all' => 'Todos os anos',
        ]));
    }

    #[Test]
    public function has_numeric_school_years_detects_year_keys(): void
    {
        $this->assertTrue(FilterOptionsService::hasNumericSchoolYears([
            '' => '— Selecione o ano letivo —',
            'all' => 'Todos os anos',
            '2024' => '2024',
            '2023' => '2023',
        ]));
    }

    #[Test]
    public function max_numeric_school_year_returns_latest(): void
    {
        $this->assertSame(2025, FilterOptionsService::maxNumericSchoolYearFromOptions([
            '' => '— Selecione o ano letivo —',
            'all' => 'Todos os anos',
            '2023' => '2023',
            '2025' => '2025',
            '2024' => '2024',
        ]));
    }

    #[Test]
    public function max_numeric_school_year_returns_null_without_years(): void
    {
        $this->assertNull(FilterOptionsService::maxNumericSchoolYearFromOptions([
            '' => '— Selecione o ano letivo —',
            'all' => 'Todos os anos',
        ]));
    }
}
