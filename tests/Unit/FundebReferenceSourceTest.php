<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebReferenceSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Classificação de fontes gravadas em fundeb_municipio_references.
 *
 * Garante que o resolver municipal não trate piso nacional importado como VAAF oficial.
 */
final class FundebReferenceSourceTest extends TestCase
{
    /**
     * Cenário: importação antiga gravou referencia_nacional_config quando CKAN falhou.
     * Esperado: isPlaceholder=true para não alimentar cálculos de perda/ganho como se fosse municipal.
     * Impacto: evita subestimar ou mascarar divergência real face à prévia federal.
     */
    #[Test]
    public function referencia_nacional_config_e_placeholder(): void
    {
        $this->assertTrue(FundebReferenceSource::isPlaceholder(FundebReferenceSource::FONTE_NACIONAL));
        $this->assertFalse(FundebReferenceSource::isMunicipalOfficial(FundebReferenceSource::FONTE_NACIONAL));
    }

    /**
     * Cenário: VAAF estimado a partir do CSV FNDE + matrículas i-Educar.
     * Esperado: fonte municipal oficial (não placeholder).
     */
    #[Test]
    public function fnde_receita_ieducar_e_fonte_municipal_oficial(): void
    {
        $this->assertFalse(FundebReferenceSource::isPlaceholder(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR));
        $this->assertTrue(FundebReferenceSource::isMunicipalOfficial(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR));
    }

    /**
     * Cenário: CKAN ou JSON remoto devolveu linha com VAAF explícito.
     */
    #[Test]
    public function api_ckan_e_fonte_municipal_oficial(): void
    {
        $this->assertFalse(FundebReferenceSource::isPlaceholder(FundebReferenceSource::FONTE_API_CKAN));
        $this->assertTrue(FundebReferenceSource::isMunicipalOfficial('api_ckan_fnde'));
    }

    /**
     * Cenário: fonte vazia ou só espaços (registo incompleto na BD).
     * Esperado: não é placeholder nem oficial — tratado noutras camadas como ausência de dado.
     */
    #[Test]
    public function fonte_vazia_nao_e_classificada_como_placeholder(): void
    {
        $this->assertFalse(FundebReferenceSource::isPlaceholder(null));
        $this->assertFalse(FundebReferenceSource::isPlaceholder(''));
        $this->assertFalse(FundebReferenceSource::isMunicipalOfficial(''));
    }

    /**
     * Cenário: variantes históricas na coluna fonte (benchmark, referencia_nacional sem sufixo).
     */
    #[Test]
    public function variantes_antigas_na_lista_placeholder(): void
    {
        $this->assertTrue(FundebReferenceSource::isPlaceholder('benchmark_db_only'));
        $this->assertTrue(FundebReferenceSource::isPlaceholder('referencia_nacional'));
    }
}
