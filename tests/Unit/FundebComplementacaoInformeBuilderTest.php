<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\FundebComplementacaoInformeBuilder;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebComplementacaoInformeBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FundebMunicipalReferenceResolver::clearCache();
    }

    /**
     * Cenário: VAAF abaixo do VAAT, discrepâncias com perda e pilar inclusão em warning.
     * Esperado: quatro blocos (vaaf, vaat, vaar, outras) com status coerente para consultoria FUNDEB.
     * Impacto: gestor municipal vê alerta antes de abrir o Simec.
     */
    #[Test]
    public function gera_quatro_blocos_com_vaat_e_vaar(): void
    {
        config([
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
            'ieducar.fundeb.complementacao_vaar_pct_base' => 10,
        ]);

        $fundebReference = [
            'vaaf' => 5000.0,
            'vaat' => 5500.0,
            'complementacao_vaar' => null,
            'fonte' => FundebMunicipalReferenceResolver::FONTE_CONFIG_IBGE,
            'fonte_label' => 'Config IBGE (teste)',
            'ano' => 2024,
            'ibge' => '2910800',
            'notas' => null,
        ];

        $city = new City(['name' => 'Feira de Santana', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $discrepancies = [
            'summary' => ['perda_estimada_anual' => 12000.0, 'ganho_potencial_anual' => 8000.0],
            'funding_pillars' => [
                [
                    'id' => 'vaar-inclusao',
                    'municipio_resumo' => ['status' => 'warning', 'texto' => 'Cadastro parcial de NEE.'],
                ],
            ],
        ];

        $inclusion = ['recurso_prova' => ['sem_nee' => 3, 'com_recurso' => 10]];
        $projection = [
            'totais' => ['fundeb_base_anual' => 500000.0],
        ];

        $informe = FundebComplementacaoInformeBuilder::build(
            $city,
            $filters,
            100,
            $discrepancies,
            $inclusion,
            $projection,
            $fundebReference,
        );

        $this->assertTrue($informe['available']);
        $this->assertCount(4, $informe['blocos']);
        $ids = array_column($informe['blocos'], 'id');
        $this->assertSame(['vaaf', 'vaat', 'vaar', 'outras'], $ids);

        $vaaf = $informe['blocos'][0];
        $this->assertSame('vaaf', $vaaf['id']);
        $this->assertContains($vaaf['status'], ['success', 'warning']);

        $vaat = $informe['blocos'][1];
        $this->assertSame('vaat', $vaat['id']);
        $this->assertSame('warning', $vaat['status']);

        $vaar = $informe['blocos'][2];
        $this->assertSame('vaar', $vaar['id']);
        $this->assertSame('warning', $vaar['status']);
        $this->assertNotEmpty($vaar['paragrafos']);
    }

    /**
     * Cenário: município sem matrículas no filtro e sem VAAF municipal importado.
     * Esperado: informe indisponível e bloco VAAF em warning (referência global).
     */
    #[Test]
    public function disponivel_com_referencia_global_sem_matriculas(): void
    {
        config([
            'ieducar.discrepancies.vaa_referencia_anual' => 4500,
            'ieducar.fundeb.vaaf_por_ibge' => [],
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        \App\Support\Ieducar\FundebMunicipalReferenceResolver::clearCache();
        $city = new City(['name' => 'Teste', 'ibge_municipio' => '9999999']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $informe = FundebComplementacaoInformeBuilder::build($city, $filters, 0);

        $this->assertFalse($informe['available']);
        $vaaf = $informe['blocos'][0];
        $this->assertSame('warning', $vaaf['status']);
    }
}
