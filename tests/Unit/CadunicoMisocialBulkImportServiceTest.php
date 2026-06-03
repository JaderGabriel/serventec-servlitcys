<?php

namespace Tests\Unit;

use App\Services\Cadunico\CadunicoMisocialBulkImportService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoMisocialBulkImportServiceTest extends TestCase
{
    #[Test]
    public function years_in_range_gera_lista_ordenada(): void
    {
        $this->assertSame([2020, 2021, 2022], CadunicoMisocialBulkImportService::yearsInRange(2020, 2022));
        $this->assertSame([2020, 2021, 2022], CadunicoMisocialBulkImportService::yearsInRange(2022, 2020));
    }

    #[Test]
    public function parse_years_option_aceita_csv(): void
    {
        $years = CadunicoMisocialBulkImportService::parseYearsOption('2020, 2022 ,2024', null, null);

        $this->assertSame([2020, 2022, 2024], $years);
    }

    #[Test]
    public function parse_years_option_usa_from_to(): void
    {
        $years = CadunicoMisocialBulkImportService::parseYearsOption(null, 2021, 2023);

        $this->assertSame([2021, 2022, 2023], $years);
    }
}
