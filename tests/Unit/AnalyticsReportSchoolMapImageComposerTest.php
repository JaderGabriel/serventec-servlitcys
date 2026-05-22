<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsReportSchoolMapImageComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsReportSchoolMapImageComposerTest extends TestCase
{
    #[Test]
    public function compose_retorna_png_quando_gd_disponivel(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Extensão GD não disponível.');

            return;
        }

        $markers = [
            [
                'lat' => -12.97,
                'lng' => -38.51,
                'label' => 'Escola A',
                'school' => ['matriculas' => 120, 'nome' => 'Escola A'],
            ],
            [
                'lat' => -12.98,
                'lng' => -38.52,
                'label' => 'Escola B',
                'school' => ['matriculas' => 45, 'nome' => 'Escola B'],
            ],
        ];

        $result = AnalyticsReportSchoolMapImageComposer::compose($markers, null, 400, 220);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('data:image/png;base64,', $result['data_uri']);
        $this->assertSame(2, $result['stats']['schools']);
        $this->assertSame(165, $result['stats']['matriculas_total']);
    }
}
