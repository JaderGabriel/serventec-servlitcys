<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFeedPhaseOptions;
use Tests\TestCase;

final class HorizonteFeedPhaseOptionsTest extends TestCase
{
    public function test_converte_fases_seleccionadas_em_skip_options(): void
    {
        $skips = HorizonteFeedPhaseOptions::skipOptionsFromSelectedPhases(['fundeb_receita', 'censo_matriculas']);

        $this->assertFalse($skips['skip_fundeb']);
        $this->assertFalse($skips['skip_censo']);
        $this->assertTrue($skips['skip_saeb']);
        $this->assertTrue($skips['skip_siconfi']);
    }

    public function test_fila_ordenada_respeita_catalogo(): void
    {
        $queue = HorizonteFeedPhaseOptions::orderedQueueFromSelectedPhases([
            'saeb_planilhas',
            'fundeb_receita',
            'censo_matriculas',
        ]);

        $this->assertSame(['fundeb_receita', 'censo_matriculas', 'saeb_planilhas'], $queue);
    }
}
