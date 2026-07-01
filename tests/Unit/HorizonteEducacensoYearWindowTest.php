<?php

namespace Tests\Unit;

use App\Models\InepCensoMunicipioMatricula;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteEducacensoYearWindowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anchor_falls_back_to_reference_year_without_data(): void
    {
        config(['horizonte.reference_year' => 2023]);

        $this->assertSame(2023, HorizonteEducacensoYearWindow::anchorYear());
    }

    #[Test]
    public function anchor_uses_max_indexed_year(): void
    {
        foreach ([2021, 2024] as $year) {
            InepCensoMunicipioMatricula::query()->create([
                'ibge_municipio' => '2927408',
                'ano' => $year,
                'matriculas_total' => 1000,
            ]);
        }

        $this->assertSame(2024, HorizonteEducacensoYearWindow::anchorYear());
    }

    #[Test]
    public function years_returns_consecutive_window(): void
    {
        config([
            'horizonte.enrollment_series.years' => 5,
            'horizonte.reference_year' => 2024,
        ]);

        $this->assertSame([2020, 2021, 2022, 2023, 2024], HorizonteEducacensoYearWindow::years());
    }
}
