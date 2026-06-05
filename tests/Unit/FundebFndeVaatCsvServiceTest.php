<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebFndeVaatCsvService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebFndeVaatCsvServiceTest extends TestCase
{
    #[Test]
    public function parseia_vaat_municipal_antes_da_complementacao(): void
    {
        $csv = <<<'CSV'
;;;;;;;;;;;;
UF;Ente Federado;Código IBGE;VAAT anterior;VAAT com a Complementação;Complementação VAAT;IEI
BA;ITAMARI;2915700; 6.830,12 ; 10.193,74 ; 7.499.081,52 ;50%
CSV;

        $service = new FundebFndeVaatCsvService();
        $index = $service->parseCsvBody($csv, 'https://example.test/vaat.csv', 2026);

        $this->assertArrayHasKey('2915700', $index);
        $this->assertEqualsWithDelta(6830.12, $index['2915700']['vaat'], 0.01);
        $this->assertEqualsWithDelta(6830.12, $index['2915700']['vaat_antes'], 0.01);
        $this->assertEqualsWithDelta(10_193.74, $index['2915700']['vaat_com_compl'], 0.01);
        $this->assertEqualsWithDelta(7_499_081.52, $index['2915700']['vaat_complementacao'], 0.01);
        $this->assertSame('50%', $index['2915700']['iei_pct']);
    }
}
