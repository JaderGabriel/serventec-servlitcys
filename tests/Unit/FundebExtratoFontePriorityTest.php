<?php

namespace Tests\Unit;

use App\Models\MunicipalTransferSnapshot;
use App\Support\Funding\FundebExtratoFontePriority;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebExtratoFontePriorityTest extends TestCase
{
    #[Test]
    public function prefere_tesouro_csv_sobre_espelho_sisweb(): void
    {
        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 1000,
        ]);
        $sisweb = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'sisweb_ckan',
            'valor' => 2000,
        ]);

        $picked = FundebExtratoFontePriority::pickPrimaryFundebRows([$sisweb, $csv]);

        $this->assertCount(1, $picked);
        $this->assertSame('tesouro_csv', $picked[0]->fonte);
    }
}
