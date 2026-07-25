<?php

namespace Tests\Unit;

use App\Support\Horizonte\PortalProcurementConfig;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PortalProcurementConfigTest extends TestCase
{
    #[Test]
    public function orgaos_siafi_ignora_codigo_vazio(): void
    {
        config([
            'horizonte.transparency.procurement.orgaos_siafi' => [
                ['codigo' => '26298', 'sigla' => 'FNDE', 'nome' => 'FNDE'],
                ['codigo' => '', 'sigla' => 'X', 'nome' => 'Ignorado'],
                ['codigo' => '26.000', 'sigla' => 'MEC', 'nome' => 'MEC'],
            ],
        ]);

        $orgaos = PortalProcurementConfig::orgaosSiafi();

        $this->assertCount(2, $orgaos);
        $this->assertSame('26298', $orgaos[0]['codigo']);
        $this->assertSame('26000', $orgaos[1]['codigo']);
    }

    #[Test]
    public function software_vendors_parse_cnpj_label(): void
    {
        config([
            'horizonte.transparency.procurement.software_vendors_raw' => '12.345.678/0001-99|Alpha,98765432000111|Beta,abc|bad',
        ]);

        $map = PortalProcurementConfig::softwareVendors();

        $this->assertSame([
            '12345678000199' => 'Alpha',
            '98765432000111' => 'Beta',
        ], $map);
    }
}
