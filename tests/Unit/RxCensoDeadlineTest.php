<?php

namespace Tests\Unit;

use App\Support\Rx\RxCensoDeadline;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RxCensoDeadlineTest extends TestCase
{
    #[Test]
    public function calcula_urgencia_e_dias_restantes(): void
    {
        config([
            'rx.censo_deadlines' => [
                2099 => ['collect_end' => '2099-12-31', 'validate_end' => '2100-01-15'],
            ],
        ]);

        $deadline = RxCensoDeadline::forYear(2099);

        $this->assertSame(2099, $deadline['ano']);
        $this->assertSame('ok', $deadline['urgency']);
        $this->assertGreaterThan(0, $deadline['days_remaining']);
        $this->assertStringContainsString('2099', $deadline['message']);
    }
}
