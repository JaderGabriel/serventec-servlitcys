<?php

namespace Tests\Unit;

use App\Support\Horizonte\FndeVaatInabilitadosCsvParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FndeVaatInabilitadosCsvParserTest extends TestCase
{
    #[Test]
    public function parses_inabilitados_from_fnde_csv_layout(): void
    {
        $csv = <<<'CSV'
UF;Ente Federado;Código IBGE;Verificação § 4º do art. 13 da Lei nº 14.113/20;Pendência identificada
GO;Heitoraí;5209606;Inobservância do art. 38 da Lei nº 14.113/20.;Não transmitiu ao SIOPE os dados do ano de 2024.
BA;Salvador;2927408;Habilitado para o cálculo do VAAT.;
AP;Cutias;1600212;Inobservância do art. 163-A da Constituição Federal.;Enviou a MSC de encerramento com a COTA-PARTE de ICMS zerada ou negativa.
CSV;

        $parsed = FndeVaatInabilitadosCsvParser::parse($csv, 2026, 'https://exemplo.gov.br/vaat');

        $this->assertArrayHasKey('5209606', $parsed);
        $this->assertSame('GO', $parsed['5209606']['uf']);
        $this->assertStringContainsString('SIOPE', $parsed['5209606']['detail']);

        $this->assertArrayHasKey('1600212', $parsed);
        $this->assertStringContainsString('MSC', $parsed['1600212']['detail']);

        $this->assertArrayNotHasKey('2927408', $parsed);
    }
}
