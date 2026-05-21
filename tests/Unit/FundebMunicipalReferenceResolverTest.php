<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMunicipalReferenceResolverTest extends TestCase
{
    #[Test]
    public function usa_vaaf_de_config_por_ibge_e_ano(): void
    {
        FundebMunicipalReferenceResolver::clearCache();
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [
                '2910800' => [
                    '2024' => ['vaaf' => 5200.50, 'vaat' => 4800.0],
                ],
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
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
        FundebMunicipalReferenceResolver::clearCache();
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [
                '2910800' => [
                    '2023' => ['vaaf' => 4900.0],
                ],
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
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
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        FundebMunicipalReferenceResolver::clearCache();
        $ref = FundebMunicipalReferenceResolver::resolve(null, null);

        $this->assertSame(FundebMunicipalReferenceResolver::FONTE_CONFIG_GLOBAL, $ref['fonte']);
        $this->assertEqualsWithDelta(4500.0, $ref['vaaf'], 0.01);
    }

    #[Test]
    public function expoe_previa_federal_e_municipal_quando_ambos_existem(): void
    {
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [
                '2910800' => [
                    '2024' => ['vaaf' => 6000.0],
                ],
            ],
            'ieducar.fundeb.open_data.national_floor.enabled' => true,
            'ieducar.fundeb.open_data.national_floor.vaaf_by_year' => [
                2024 => 5000.0,
            ],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
        ]);

        FundebMunicipalReferenceResolver::clearCache();
        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $ref = FundebMunicipalReferenceResolver::resolve($city, $filters);

        $this->assertEqualsWithDelta(6000.0, $ref['vaaf'], 0.01);
        $this->assertIsArray($ref['municipal']);
        $this->assertIsArray($ref['previa']);
        $this->assertEqualsWithDelta(6000.0, $ref['municipal']['vaaf'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $ref['previa']['vaaf'], 0.01);
        $this->assertIsArray($ref['divergencia']);
        $this->assertGreaterThan(0, $ref['divergencia']['pct']);
    }

    #[Test]
    public function classifica_fontes_placeholder_vs_municipal_oficial(): void
    {
        $this->assertTrue(FundebReferenceSource::isPlaceholder(FundebReferenceSource::FONTE_NACIONAL));
        $this->assertTrue(FundebReferenceSource::isPlaceholder('referencia_nacional'));
        $this->assertFalse(FundebReferenceSource::isPlaceholder(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR));
        $this->assertTrue(FundebReferenceSource::isMunicipalOfficial(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR));
    }
}
