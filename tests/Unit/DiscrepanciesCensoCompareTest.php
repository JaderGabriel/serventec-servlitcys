<?php

namespace Tests\Unit;

use App\Support\Ieducar\DiscrepanciesQueries;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscrepanciesCensoCompareTest extends TestCase
{
    #[Test]
    public function dispara_quando_ieducar_acima_do_censo(): void
    {
        $row = DiscrepanciesQueries::buildCensoMatriculaDiffRow(150, 100, 5.0, 10);

        $this->assertNotNull($row);
        $this->assertSame(50, $row['total']);
        $this->assertSame('above_censo', $row['meta']['direction']);
    }

    #[Test]
    public function dispara_quando_ieducar_abaixo_do_censo(): void
    {
        $row = DiscrepanciesQueries::buildCensoMatriculaDiffRow(150, 200, 5.0, 10);

        $this->assertNotNull($row);
        $this->assertSame(50, $row['total']);
        $this->assertSame('below_censo', $row['meta']['direction']);
    }

    #[Test]
    public function nao_dispara_dentro_da_tolerancia(): void
    {
        $this->assertNull(DiscrepanciesQueries::buildCensoMatriculaDiffRow(105, 100, 10.0, 20));
    }
}
