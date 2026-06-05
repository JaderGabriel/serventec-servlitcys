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

    #[Test]
    public function pick_primary_per_program_evita_somar_fontes_do_mesmo_programa(): void
    {
        $csv = new MunicipalTransferSnapshot([
            'ano' => 2025,
            'programa_id' => 'fundeb',
            'fonte' => 'tesouro_csv',
            'valor' => 1000,
        ]);
        $sisweb = new MunicipalTransferSnapshot([
            'ano' => 2025,
            'programa_id' => 'fundeb',
            'fonte' => 'sisweb_ckan',
            'valor' => 2000,
        ]);
        $pnae = new MunicipalTransferSnapshot([
            'ano' => 2025,
            'programa_id' => 'pnae',
            'fonte' => 'tesouro',
            'valor' => 50,
        ]);

        $picked = FundebExtratoFontePriority::pickPrimaryPerProgram([$sisweb, $csv, $pnae]);

        $this->assertCount(2, $picked);
        $this->assertSame(1050.0, FundebExtratoFontePriority::sumDedupedValor([$sisweb, $csv, $pnae]));
    }

    #[Test]
    public function totals_by_year_deduplica_por_exercicio(): void
    {
        $rows = [
            new MunicipalTransferSnapshot(['ano' => 2024, 'programa_id' => 'fundeb', 'fonte' => 'tesouro_csv', 'valor' => 100]),
            new MunicipalTransferSnapshot(['ano' => 2024, 'programa_id' => 'fundeb', 'fonte' => 'sisweb_ckan', 'valor' => 999]),
            new MunicipalTransferSnapshot(['ano' => 2025, 'programa_id' => 'fundeb', 'fonte' => 'tesouro_csv', 'valor' => 200]),
        ];

        $this->assertSame([2024 => 100.0, 2025 => 200.0], FundebExtratoFontePriority::totalsByYearDeduped($rows));
    }
}
