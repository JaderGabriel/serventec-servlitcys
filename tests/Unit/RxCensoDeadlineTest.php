<?php

namespace Tests\Unit;

use App\Support\Rx\RxCensoCalendar;
use App\Support\Rx\RxCensoDeadline;
use App\Support\Rx\RxEducacensoToolkit;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RxCensoDeadlineTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function fase_stage1_coleta_usa_janela_oficial_2026(): void
    {
        config(['rx.censo_calendar' => config('rx.censo_calendar')]);

        Carbon::setTestNow('2026-06-09 12:00:00');

        $deadline = RxCensoDeadline::forYear(2026);

        $this->assertSame('stage1_collect', $deadline['phase']);
        $this->assertSame(2026, $deadline['ano']);
        $this->assertGreaterThan(0, $deadline['days_remaining']);
        $this->assertSame('31/07/2026', $deadline['collect_end_label']);
        $this->assertStringContainsString('coleta', strtolower((string) $deadline['countdown_label']));
    }

    #[Test]
    public function fase_retificacao_apos_dou(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $deadline = RxCensoDeadline::forYear(2026);

        $this->assertSame('stage1_rectification', $deadline['phase']);
        $this->assertGreaterThan(0, $deadline['days_remaining']);
    }

    #[Test]
    public function calcula_urgencia_com_fallback_legado(): void
    {
        config([
            'rx.censo_calendar' => [],
            'rx.censo_deadlines' => [
                2099 => ['collect_end' => '2099-12-31', 'validate_end' => '2100-01-15'],
            ],
        ]);

        $deadline = RxCensoDeadline::forYear(2099);

        $this->assertSame(2099, $deadline['ano']);
        $this->assertSame('ok', $deadline['urgency']);
        $this->assertGreaterThan(0, $deadline['days_remaining']);
    }

    #[Test]
    public function toolkit_2026_inclui_calendario_e_dados_da_primeira_etapa(): void
    {
        $toolkit = RxEducacensoToolkit::forYear(2026);

        $this->assertSame(2026, $toolkit['ano']);
        $this->assertNotEmpty($toolkit['calendar']);
        $this->assertNotEmpty($toolkit['stage1_required']);
        $this->assertNotEmpty($toolkit['rectification']['items'] ?? []);
        $this->assertNotNull($toolkit['stage2_preview']);
    }

    #[Test]
    public function calendario_2026_tem_data_de_referencia_27_maio(): void
    {
        $calendar = RxCensoCalendar::forYear(2026);

        $this->assertIsArray($calendar);
        $this->assertSame('2026-05-27', $calendar['reference_date']);
        $this->assertSame('2026-07-31', $calendar['stage1']['collect_end']);
    }
}
