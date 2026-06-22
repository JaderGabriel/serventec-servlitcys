<?php

namespace Tests\Unit;

use App\Models\MunicipalTransferSnapshot;
use App\Support\Horizonte\HorizonteTransferBreakdown;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteTransferBreakdownTest extends TestCase
{
    #[Test]
    public function classifica_fundeb_e_educacao_com_percentuais(): void
    {
        $this->assertTrue(HorizonteTransferBreakdown::isFundebProgram('fundeb', 'FUNDEB'));
        $this->assertTrue(HorizonteTransferBreakdown::isFundebProgram('outro', 'Repasse FUNDEB municipal'));

        $pnae = new MunicipalTransferSnapshot([
            'programa_id' => 'pnae',
            'programa_label' => 'PNAE',
            'fonte' => 'tesouro_csv',
            'valor' => 100,
        ]);
        $this->assertTrue(HorizonteTransferBreakdown::isEducationProgram($pnae));
        $this->assertFalse(HorizonteTransferBreakdown::isFundebProgram('pnae', 'PNAE'));
    }
}
