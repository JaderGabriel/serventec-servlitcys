<?php

namespace Tests\Unit;

use App\Support\Cpf;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validação de CPF em formulários de utilizadores (dígitos verificadores Receita Federal).
 */
final class CpfTest extends TestCase
{
    /**
     * Cenário: entrada com máscara do front-end.
     */
    #[Test]
    public function normalize_remove_pontuacao(): void
    {
        $this->assertSame('12345678909', Cpf::normalizeDigits('123.456.789-09'));
    }

    /**
     * Cenário: CPF válido conhecido (algoritmo módulo 11).
     */
    #[Test]
    public function is_valid_aceita_cpf_correto(): void
    {
        $this->assertTrue(Cpf::isValidDigits('52998224725'));
    }

    /**
     * Cenário: sequência repetida (111...) — inválido por regra da RF.
     */
    #[Test]
    public function is_valid_rejeita_digitos_iguais(): void
    {
        $this->assertFalse(Cpf::isValidDigits('11111111111'));
    }

    /**
     * Cenário: tamanho incorreto após normalização.
     */
    #[Test]
    public function is_valid_rejeita_tamanho_errado(): void
    {
        $this->assertFalse(Cpf::isValidDigits('1234567890'));
    }

    /**
     * Cenário: exibição em listagens admin.
     */
    #[Test]
    public function format_masked_aplica_mascara_brasileira(): void
    {
        $this->assertSame('529.982.247-25', Cpf::formatMasked('52998224725'));
    }

    /**
     * Cenário: dígito verificador errado no último caractere.
     */
    #[Test]
    public function is_valid_rejeita_digito_verificador_incorreto(): void
    {
        $this->assertFalse(Cpf::isValidDigits('52998224700'));
    }
}
