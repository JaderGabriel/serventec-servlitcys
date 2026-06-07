<?php

namespace Tests\Unit;

use App\Support\Rx\RxCadastroPulse;
use PHPUnit\Framework\TestCase;

final class RxCadastroPulseTest extends TestCase
{
    public function test_merge_series_agrega_buckets_de_duas_horas(): void
    {
        $anchor = now()->startOfHour();
        $start = $anchor->copy()->subHours(4);
        $k0 = $start->format('Y-m-d H:00:00');
        $k1 = $start->copy()->addHour()->format('Y-m-d H:00:00');
        $k2 = $start->copy()->addHours(2)->format('Y-m-d H:00:00');
        $k3 = $start->copy()->addHours(3)->format('Y-m-d H:00:00');

        $series = RxCadastroPulse::mergeSeries(
            [$k0 => 2, $k1 => 3, $k2 => 1, $k3 => 4],
            [$k0 => 1, $k2 => 2],
            seriesHours: 4,
            bucketHours: 2,
        );

        $this->assertCount(2, $series);
        $this->assertSame(5, $series[0]['matriculas']);
        $this->assertSame(1, $series[0]['turmas']);
        $this->assertSame(6, $series[0]['total']);
        $this->assertSame(5, $series[1]['matriculas']);
        $this->assertSame(2, $series[1]['turmas']);
        $this->assertSame(7, $series[1]['total']);
    }

    public function test_empty_tem_janelas_24_48_72(): void
    {
        $empty = RxCadastroPulse::empty();

        $this->assertFalse($empty['available']);
        $this->assertCount(3, $empty['windows']);
        $this->assertSame([24, 48, 72], array_column($empty['windows'], 'hours'));
    }
}
