<?php

namespace Tests\Unit;

use App\Support\Rx\RxSemaphore;
use Tests\TestCase;

final class RxSemaphoreTest extends TestCase
{
    public function test_verde_com_ano_imediato_zerado_indica_referencia_e_saltos(): void
    {
        $sem = RxSemaphore::fromRow([
            'ok' => true,
            'meta_encontrou_referencia' => true,
            'meta_matriculas_alvo' => 525,
            'meta_turmas_alvo' => 20,
            'progresso_cadastro_pct' => 100.0,
            'registros_restantes' => 0,
            'meta_ano_imediato_zerado' => true,
            'meta_saltos' => 1,
            'meta_referencia_ano' => 2024,
            'meta_acrescimo_pct' => 5.0,
            'anterior_ano' => 2025,
        ]);

        $this->assertSame('green', $sem['status']);
        $this->assertStringContainsString('2024', $sem['label']);
        $this->assertStringContainsString('2025', $sem['title']);
        $this->assertStringContainsString('salto', strtolower($sem['title']));
    }

    public function test_amarelo_com_ano_imediato_zerado_menciona_referencia(): void
    {
        $sem = RxSemaphore::fromRow([
            'ok' => true,
            'meta_encontrou_referencia' => true,
            'meta_matriculas_alvo' => 525,
            'meta_turmas_alvo' => 20,
            'progresso_cadastro_pct' => 80.0,
            'registros_restantes' => 50,
            'meta_ano_imediato_zerado' => true,
            'meta_saltos' => 1,
            'meta_referencia_ano' => 2024,
            'anterior_ano' => 2025,
        ]);

        $this->assertSame('yellow', $sem['status']);
        $this->assertStringContainsString('2024', $sem['title']);
        $this->assertStringContainsString('2025', $sem['title']);
    }
}
