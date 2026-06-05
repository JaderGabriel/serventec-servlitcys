<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebFndeVaatCsvService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebFndeVaatCsvServiceTest extends TestCase
{
    #[Test]
    public function parseia_vaat_por_aluno_do_csv_portaria_6(): void
    {
        $csv = <<<'CSV'
;;;;;;;;;;;;
UF;Ente Federado;Código IBGE;VAAT anterior;VAAT com a Complementação;Complementação VAAT;IEI
BA;ITAMARI;2915700; 9.500,00 ; 10.193,74 ; 1.200.000,00 ;50%
CSV;

        $service = new FundebFndeVaatCsvService();
        $index = $service->parseCsvBody($csv, 'https://example.test/vaat.csv', 2026);

        $this->assertArrayHasKey('2915700', $index);
        $this->assertEqualsWithDelta(10_193.74, $index['2915700']['vaat'], 0.01);
    }
}
