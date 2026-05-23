<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fórmula indicativa: ocorrências × VAAF × peso por tipo de discrepância.
 */
final class DiscrepanciesFundingImpactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FundebMunicipalReferenceResolver::clearCache();
    }

    /**
     * Cenário: 10 matrículas sem raça, VAAF global 5000, peso 1.5 para sem_raca.
     * Esperado: perda = ganho = 10 × 7500 = 75000 (modelo simétrico de recuperação).
     */
    #[Test]
    public function estimate_aplica_peso_por_check_e_vaaf_config(): void
    {
        config([
            'ieducar.discrepancies.vaa_referencia_anual' => 5000.0,
            'ieducar.discrepancies.peso_por_check' => ['sem_raca' => 1.5],
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $result = DiscrepanciesFundingImpact::estimate('sem_raca', 10, $city, $filters);

        $this->assertEqualsWithDelta(75_000.0, $result['perda_anual'], 0.01);
        $this->assertEqualsWithDelta(75_000.0, $result['ganho_potencial_anual'], 0.01);
        $this->assertEqualsWithDelta(7500.0, $result['valor_unitario'], 0.01);
        $this->assertStringContainsString('aluno/ano', $result['formula']);
        $this->assertStringContainsString('5.000', $result['formula']);
    }

    /**
     * Cenário: check desconhecido sem entrada em peso_por_check.
     * Esperado: peso 1.0 (default) — não inflacionar impacto por typo de ID.
     */
    #[Test]
    public function peso_padrao_um_para_check_sem_config(): void
    {
        config(['ieducar.discrepancies.peso_por_check' => []]);

        $this->assertSame(1.0, DiscrepanciesFundingImpact::pesoParaCheck('check_inexistente'));
    }

    /**
     * Cenário: zero ocorrências (escola limpa no filtro).
     * Esperado: perda e ganho zerados — evita ruído em gráficos financeiros.
     */
    #[Test]
    public function estimate_com_zero_ocorrencias_retorna_zeros(): void
    {
        config(['ieducar.discrepancies.vaa_referencia_anual' => 4500.0]);

        $result = DiscrepanciesFundingImpact::estimate('sem_raca', 0);

        $this->assertSame(0.0, $result['perda_anual']);
        $this->assertSame(0.0, $result['ganho_potencial_anual']);
    }

    /**
     * Cenário: ocorrências negativas vindas de bug upstream.
     * Esperado: clamp em 0 — proteção defensiva na camada de domínio.
     */
    #[Test]
    public function estimate_nao_aceita_ocorrencias_negativas(): void
    {
        config(['ieducar.discrepancies.vaa_referencia_anual' => 4500.0]);

        $result = DiscrepanciesFundingImpact::estimate('sem_raca', -5);

        $this->assertSame(0.0, $result['perda_anual']);
    }

    /**
     * Cenário: formatação BRL usada em tabelas, CSV e modais.
     */
    #[Test]
    public function format_brl_usa_locale_pt(): void
    {
        $this->assertSame('R$ 5.559,73', DiscrepanciesFundingImpact::formatBrl(5559.73));
    }

    /**
     * Cenário: VAAF por IBGE em config tem prioridade sobre referência global.
     */
    #[Test]
    public function vaa_referencia_usa_config_por_ibge(): void
    {
        config([
            'ieducar.fundeb.vaaf_por_ibge' => ['2910800' => ['2024' => ['vaaf' => 6200.0]]],
            'ieducar.discrepancies.vaa_referencia_anual' => 4500.0,
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        $city = new City(['ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $this->assertEqualsWithDelta(6200.0, DiscrepanciesFundingImpact::vaaReferencia($city, $filters), 0.01);
    }

    /**
     * Cenário: metodologia de resumo exposta no Diagnóstico — deve citar passos e VAAF.
     */
    #[Test]
    public function metodologia_resumo_inclui_passos_e_aviso(): void
    {
        config(['ieducar.discrepancies.vaa_referencia_anual' => 5000.0, 'ieducar.discrepancies.aviso_financeiro' => 'Indicativo.']);

        $meta = DiscrepanciesFundingImpact::metodologiaResumo();

        $this->assertNotEmpty($meta['titulo']);
        $this->assertGreaterThanOrEqual(3, count($meta['passos']));
        $this->assertSame('Indicativo.', $meta['aviso']);
    }

    /**
     * Cenário: garantir que fonte placeholder não é tratada como municipal no resolver
     * (integração leve com DiscrepanciesFundingImpact::resolveReference).
     */
    #[Test]
    public function resolve_reference_nao_usa_apenas_placeholder_na_config(): void
    {
        config([
            'ieducar.fundeb.vaaf_por_ibge' => [],
            'ieducar.discrepancies.vaa_referencia_anual' => 5559.73,
            'ieducar.fundeb.open_data.national_floor.enabled' => true,
            'ieducar.fundeb.open_data.national_floor.vaaf_by_year' => [2024 => 5559.73],
        ]);

        $ref = DiscrepanciesFundingImpact::resolveReference(
            new City(['ibge_municipio' => '2910800']),
            new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null),
        );

        $this->assertFalse(FundebReferenceSource::isPlaceholder($ref['fonte'] ?? ''));
    }
}
