<?php

namespace Tests\Unit;

use App\Support\Ieducar\MatriculaVolumeCounts;
use Tests\TestCase;

final class MatriculaVolumeCountsTest extends TestCase
{
    public function test_fundeb_base_usa_minimo_entre_matriculas_e_alunos(): void
    {
        $base = MatriculaVolumeCounts::fundebCalculationBase([
            'matriculas' => 178,
            'alunos' => 165,
            'alunos_available' => true,
        ]);

        $this->assertSame(165, $base);
    }

    public function test_fundeb_base_cai_para_matriculas_sem_coluna_aluno(): void
    {
        $base = MatriculaVolumeCounts::fundebCalculationBase([
            'matriculas' => 120,
            'alunos' => null,
            'alunos_available' => false,
        ]);

        $this->assertSame(120, $base);
    }

    public function test_presentation_inclui_hint_quando_matriculas_maior_que_alunos(): void
    {
        $presentation = MatriculaVolumeCounts::presentation([
            'matriculas' => 200,
            'alunos' => 178,
            'alunos_available' => true,
        ]);

        $this->assertSame(200, $presentation['matriculas']);
        $this->assertSame(178, $presentation['alunos']);
        $this->assertNotNull($presentation['hint']);
    }
}
