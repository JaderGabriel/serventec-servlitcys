<?php

namespace Tests\Unit;

use App\Models\InepCensoMunicipioMatricula;
use App\Services\Cadunico\CadunicoCensoAjuste;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoCensoAjusteTest extends TestCase
{
    #[Test]
    public function desconta_matriculas_nao_municipais_do_gap(): void
    {
        $censo = new InepCensoMunicipioMatricula([
            'matriculas_total' => 10000,
            'matriculas_nao_municipal' => 4000,
        ]);

        $result = CadunicoCensoAjuste::apply(8000, 6000, 2000, $censo);

        $this->assertTrue($result['aplicado']);
        $this->assertSame(0, $result['gap_ajustado']);
        $this->assertSame(4000, $result['nao_municipal_estimado']);
        $this->assertSame('censo_dependencia', $result['metodo']);
    }

    #[Test]
    public function usa_proxy_total_menos_municipal_quando_dependencia_ausente(): void
    {
        $censo = new InepCensoMunicipioMatricula([
            'matriculas_total' => 9000,
        ]);

        $result = CadunicoCensoAjuste::apply(8000, 6000, 5000, $censo);

        $this->assertTrue($result['aplicado']);
        $this->assertSame(2000, $result['gap_ajustado']);
        $this->assertSame(3000, $result['nao_municipal_estimado']);
        $this->assertSame('censo_proxy_total_menos_municipal', $result['metodo']);
    }
}
