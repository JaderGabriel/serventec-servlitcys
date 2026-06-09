<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebVaafProfileBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebDockMeterTest extends TestCase
{
    #[Test]
    public function build_dock_meter_sem_ano_retorna_vazio(): void
    {
        $builder = app(FundebVaafProfileBuilder::class);
        $city = new \App\Models\City(['name' => 'Test', 'uf' => 'SP', 'ibge_municipio' => '3550308']);
        $filters = new \App\Support\Dashboard\IeducarFilterState(null, null, null, null);

        $meter = $builder->buildDockMeter($city, $filters);

        $this->assertFalse($meter['available']);
        $this->assertSame([], $meter['points']);
    }
}
