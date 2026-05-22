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
}
