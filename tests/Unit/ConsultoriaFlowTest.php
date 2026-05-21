<?php

namespace Tests\Unit;

use App\Support\Dashboard\ConsultoriaFlow;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Numeração dinâmica de âncoras no Diagnóstico e Discrepâncias (secções vazias não aparecem).
 */
final class ConsultoriaFlowTest extends TestCase
{
    /**
     * Cenário: aba com VAAF e programas visíveis, mas sem bloco temático.
     * Esperado: passos 1..N contínuos; secção visible=false não consome número.
     */
    #[Test]
    public function numbered_steps_omite_secoes_invisiveis_e_renumera(): void
    {
        $steps = ConsultoriaFlow::numberedSteps([
            ['label' => 'Prioridades', 'anchor' => 'diag-prioridades', 'visible' => true],
            ['label' => 'VAAF', 'anchor' => 'diag-vaaf', 'visible' => true],
            ['label' => 'Temático', 'anchor' => 'diag-tematico', 'visible' => false],
            ['label' => 'Fontes', 'anchor' => 'diag-fontes', 'visible' => true],
        ]);

        $this->assertCount(3, $steps);
        $this->assertSame('1', $steps[0]['num']);
        $this->assertSame('2', $steps[1]['num']);
        $this->assertSame('3', $steps[2]['num']);
        $this->assertSame('diag-fontes', $steps[2]['anchor']);
    }

    /**
     * Cenário: blade precisa do número do passo para o título «3. Mapa de rotinas».
     */
    #[Test]
    public function step_num_retorna_numero_pela_ancora(): void
    {
        $steps = ConsultoriaFlow::numberedSteps([
            ['label' => 'A', 'anchor' => 'disc-a'],
            ['label' => 'B', 'anchor' => 'disc-b'],
        ]);

        $this->assertSame('2', ConsultoriaFlow::stepNum($steps, 'disc-b'));
        $this->assertNull(ConsultoriaFlow::stepNum($steps, 'inexistente'));
    }

    /**
     * Cenário: consultoria-section recebe :step via mapa anchor => num.
     */
    #[Test]
    public function step_map_indexa_ancoras(): void
    {
        $steps = ConsultoriaFlow::numberedSteps([
            ['label' => 'Prioridades', 'anchor' => 'disc-prioridades'],
        ]);

        $map = ConsultoriaFlow::stepMap($steps);

        $this->assertSame(['disc-prioridades' => '1'], $map);
    }
}
