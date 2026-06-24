<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSaebLookupYears;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteSaebLookupYearsTest extends TestCase
{
    #[Test]
    public function includes_configured_planilha_years_beyond_reference_window(): void
    {
        $years = HorizonteSaebLookupYears::forReferenceYear(2025);

        $this->assertContains(2023, $years);
        $this->assertContains(2021, $years);
        $this->assertContains(2025, $years);
    }
}
