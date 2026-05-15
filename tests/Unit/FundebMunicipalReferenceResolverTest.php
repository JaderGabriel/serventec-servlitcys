<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMunicipalReferenceResolverTest extends TestCase
{
    #[Test]
    public function usa_vaaf_de_config_por_ibge_e_ano(): void
    {
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [
                '2910800' => [
                    '2024' => ['vaaf' => 5200.50, 'vaat' => 4800.0],
                ],
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
        ]);

        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $ref = FundebMunicipalReferenceResolver::resolve($city, $filters);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_CONFIG_IBGE, $ref['fonte']);
        $this->assertEqualsWithDelta(5200.50, $ref['vaaf'], 0.01);
        $this->assertEqualsWithDelta(4800.0, $ref['vaat'], 0.01);
    }

    #[Test]
    public function usa_ano_anterior_quando_vigente_nao_tem_config(): void
    {
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [
                '2910800' => [
                    '2023' => ['vaaf' => 4900.0],
                ],
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
        ]);

        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $ref = FundebMunicipalReferenceResolver::resolve($city, $filters);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_CONFIG_IBGE, $ref['fonte']);
        $this->assertEqualsWithDelta(4900.0, $ref['vaaf'], 0.01);
        $this->assertSame(2023, $ref['ano']);
        $this->assertStringContainsString('2024', $ref['fonte_label']);
    }

    #[Test]
    public function fallback_global_sem_ibge(): void
    {
        config([
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
            'ieducar.fundeb.vaaf_por_ibge' => [],
        ]);

        $ref = FundebMunicipalReferenceResolver::resolve(null, null);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_CONFIG_GLOBAL, $ref['fonte']);
        $this->assertEqualsWithDelta(4500.0, $ref['vaaf'], 0.01);
    }
}
