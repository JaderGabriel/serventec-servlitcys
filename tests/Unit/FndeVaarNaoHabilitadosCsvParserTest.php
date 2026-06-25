<?php

namespace Tests\Unit;

use App\Support\Horizonte\FndeVaarNaoHabilitadosCsvParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FndeVaarNaoHabilitadosCsvParserTest extends TestCase
{
    #[Test]
    public function parses_nao_habilitados_from_fnde_vaar_csv_layout(): void
    {
        $csv = <<<'CSV'
Lista de Entes Beneficiários/Não Beneficiários da Complementação da União-VAAR 2026;;;;;;;;;;;;

UF;Código IBGE;Entidade;Cond. I;Cond. II;Cond. III;Cond. IV;Cond. V;Habilitados?;Evoluiu Indicador de Atendimento?;Evoluiu Indicador de Aprendizagem?;Beneficiário?;Pendência Identificada
AC;1200013;ACRELANDIA; Sim ; Sim ; Não ; Sim ; Sim ; Não Habilitado ; Sim ; Não ; Não Beneficiário ; Não cumprimento do disposto no art. 14, § 1º, III da Lei nº 14113/2020 
AC;1200054;ASSIS BRASIL; Sim ; Sim ; Sim ; Sim ; Sim ; Habilitado ; Sim ; Sim ; Beneficiário ;
AP;1600212;CUTIAS; Sim ; Não ; Não ; Sim ; Sim ; Não Habilitado ; Sim ; Não ; Não Beneficiário ; Não cumprimento do disposto no art. 14, § 1º, II e III da Lei nº 14113/2020 
CSV;

        $parsed = FndeVaarNaoHabilitadosCsvParser::parse($csv, 2026, 'https://exemplo.gov.br/vaar');

        $this->assertArrayHasKey('1200013', $parsed);
        $this->assertSame('AC', $parsed['1200013']['uf']);
        $this->assertSame('vaar_nao_habilitado', $parsed['1200013']['items'][0]['kind']);
        $this->assertStringContainsString('art. 14', $parsed['1200013']['detail']);

        $this->assertArrayHasKey('1600212', $parsed);
        $this->assertSame('CUTIAS', $parsed['1600212']['name']);

        $this->assertArrayNotHasKey('1200054', $parsed);
    }
}
