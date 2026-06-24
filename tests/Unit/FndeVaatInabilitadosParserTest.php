<?php

namespace Tests\Unit;

use App\Support\Horizonte\FndeVaatInabilitadosParser;
use App\Support\Horizonte\HorizonteMunicipalAlertsResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FndeVaatInabilitadosParserTest extends TestCase
{
    #[Test]
    public function parses_fnde_inabilitados_lines_with_ibge_and_reason(): void
    {
        $text = <<<'TXT'
AL Batalha 2700706 Inobservância do art. 38 da Lei nº 14.113/20. Não transmitiu ao SIOPE os dados do ano de 2024.
AP Porto Grande 1600535 Inobservância do art. 163-A da CF e do art. 38 da Lei 14.113/20. Não enviou a MSC de encerramento de 2024. Não transmitiu ao SIOPE os dados do ano de 2024.
TXT;

        $parsed = FndeVaatInabilitadosParser::parse($text, 2026, 'https://exemplo.gov.br/vaat');

        $this->assertArrayHasKey('2700706', $parsed);
        $this->assertSame('AL', $parsed['2700706']['uf']);
        $this->assertStringContainsString('SIOPE', $parsed['2700706']['detail']);
        $this->assertSame('vaat_inabilitado', $parsed['2700706']['items'][0]['kind']);

        $this->assertArrayHasKey('1600535', $parsed);
        $this->assertStringContainsString('MSC', $parsed['1600535']['detail']);
    }
}
