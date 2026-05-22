<?php

namespace Tests\Unit;

use App\Support\Rx\RxCadastroGap;
use PHPUnit\Framework\TestCase;

final class RxCadastroGapTest extends TestCase
{
    public function test_falta_turmas_e_matriculas_separadas(): void
    {
        $gap = RxCadastroGap::compute(
            metaTurmas: 10,
            metaMatriculas: 98,
            metaEnturmacoes: 98,
            turmasVigente: 0,
            matriculasVigente: 96,
            enturmacoesVigente: 96,
        );

        $this->assertSame(10, $gap['falta_turmas']);
        $this->assertSame(2, $gap['falta_matriculas']);
        $this->assertSame(12, $gap['registros_restantes']);
        $this->assertEqualsWithDelta(98.0, $gap['progresso_matriculas_pct'], 0.01);
        $this->assertEqualsWithDelta(0.0, $gap['progresso_turmas_pct'], 0.01);
        $this->assertEqualsWithDelta(0.0, $gap['progresso_cadastro_pct'], 0.01);
    }

    public function test_progresso_matriculas_quando_só_meta_de_matricula(): void
    {
        $gap = RxCadastroGap::compute(0, 100, 0, 0, 50, 0);

        $this->assertSame(50.0, $gap['progresso_cadastro_pct']);
        $this->assertNull($gap['progresso_turmas_pct']);
    }

    public function test_delta_sem_base_ano_anterior(): void
    {
        $d = RxCadastroGap::matriculasDelta(96, 0);

        $this->assertSame(96, $d['delta']);
        $this->assertNull($d['delta_pct']);
        $this->assertTrue($d['delta_sem_base']);
    }
}
