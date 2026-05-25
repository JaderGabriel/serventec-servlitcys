<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use Tests\TestCase;

final class FundebVaafParaCalculoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FundebMunicipalReferenceResolver::clearCache();
    }

    public function test_ordem_previa_antes_de_valor_configurado(): void
    {
        config([
            'ieducar.fundeb.open_data.national_floor.enabled' => true,
            'ieducar.fundeb.open_data.national_floor.vaaf_by_year' => [
                2024 => 5200.0,
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500.0,
        ]);

        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo(null, null);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_PREVIA_NACIONAL, $calc['origem']);
        $this->assertEqualsWithDelta(5200.0, $calc['vaaf'], 0.01);
    }

    public function test_valor_configurado_so_no_fim(): void
    {
        config([
            'ieducar.fundeb.open_data.national_floor.enabled' => true,
            'ieducar.fundeb.open_data.national_floor.vaaf_by_year' => [],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500.0,
        ]);

        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo(null, null);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_VALOR_CONFIGURADO, $calc['origem']);
        $this->assertEqualsWithDelta(4500.0, $calc['vaaf'], 0.01);
        $this->assertStringContainsString('Valor configurado', $calc['fonte_label']);
    }
}
