<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Fundeb\FundebIbgeMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Correspondência de códigos IBGE entre i-Educar, CKAN FNDE e CSV da Portaria.
 */
final class FundebIbgeMatcherTest extends TestCase
{
    /**
     * Cenário: município com IBGE mascarado ou com pontuação (formulário).
     * Esperado: 7 dígitos numéricos para join com dados abertos.
     */
    #[Test]
    public function normalize_remove_mascara_e_retorna_sete_digitos(): void
    {
        $this->assertSame('2910800', FundebIbgeMatcher::normalize('29.108.00'));
        $this->assertSame('3550308', FundebIbgeMatcher::normalize('3550308'));
    }

    /**
     * Cenário: código com prefixo UF embutido (>7 dígitos) em exportações legadas.
     * Esperado: truncar aos primeiros 7 (padrão IBGE município).
     */
    #[Test]
    public function normalize_trunca_mais_de_sete_digitos(): void
    {
        $this->assertSame('2910800', FundebIbgeMatcher::normalize('2910800123'));
    }

    /**
     * Cenário: 6 dígitos (subcódigo antigo sem UF) — não é município completo.
     */
    #[Test]
    public function normalize_rejeita_seis_digitos(): void
    {
        $this->assertNull(FundebIbgeMatcher::normalize('910800'));
    }

    /**
     * Cenário: cidade cadastrada no painel com ibge_municipio válido.
     * Esperado: candidatos incluem 7 dígitos e opcionalmente 5 dígitos (sem UF) para CKAN heterogéneo.
     */
    #[Test]
    public function candidates_for_city_inclui_variacao_seis_digitos(): void
    {
        $city = new City(['ibge_municipio' => '2910800']);
        $candidates = FundebIbgeMatcher::candidatesForCity($city);

        $this->assertContains('2910800', $candidates);
        $this->assertContains('10800', $candidates);
    }

    /**
     * Cenário: registo CKAN com chave codigo_ibge em maiúsculas/minúsculas mistas.
     */
    #[Test]
    public function extract_from_record_usa_chaves_configuradas(): void
    {
        config([
            'ieducar.fundeb.open_data.fields.ibge' => ['Codigo_IBGE', 'ano'],
        ]);

        $ibge = FundebIbgeMatcher::extractFromRecord([
            'Codigo_IBGE' => '2911403',
            'ano' => 2024,
        ]);

        $this->assertSame('2911403', $ibge);
    }

    /**
     * Cenário: registo com 7 dígitos iguais ao município alvo.
     */
    #[Test]
    public function record_matches_ibge_com_sete_digitos_iguais(): void
    {
        $this->assertTrue(FundebIbgeMatcher::recordMatchesIbge(['codigo_ibge' => '2910800'], '2910800'));
        $this->assertFalse(FundebIbgeMatcher::recordMatchesIbge(['codigo_ibge' => '2911403'], '2910800'));
    }

    /**
     * Cenário: CSV com subcódigo de 6 dígitos (UF + município sem o primeiro dígito da UF duplicado).
     * Esperado: 2910800 casa com 910800 (substr alvo, 1, 6)).
     */
    #[Test]
    public function record_matches_ibge_sufixo_seis_digitos_quando_alinhado(): void
    {
        $this->assertTrue(FundebIbgeMatcher::recordMatchesIbge(['codigo_ibge' => '910800'], '2910800'));
    }
}
