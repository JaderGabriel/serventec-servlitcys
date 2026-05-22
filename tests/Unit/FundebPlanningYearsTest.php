<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebOpenDataImportService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebPlanningYearsTest extends TestCase
{
    #[Test]
    public function years_for_planning_inclui_corrente_e_proximo(): void
    {
        config([
            'ieducar.fundeb.open_data.planning_years_ahead' => 1,
            'ieducar.fundeb.open_data.planning_include_suggested_import_year' => false,
        ]);

        $years = FundebOpenDataImportService::yearsForPlanningProfile();
        $current = (int) date('Y');

        $this->assertContains($current, $years);
        $this->assertContains($current + 1, $years);
    }
}
