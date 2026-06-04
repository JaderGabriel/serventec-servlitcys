<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Funding\FundebTransferScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebTransferScopeTest extends TestCase
{
    #[Test]
    public function publicacao_stn_por_uf_nao_entra_no_total_municipal(): void
    {
        $ufRow = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB STN',
            'fonte' => 'tesouro_publicacao',
            'valor' => 1_000_000.0,
            'meta' => ['agregacao' => 'uf', 'uf' => 'BA'],
        ]);

        $municipal = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 120.0,
            'meta' => ['mensal' => ['1' => 120.0]],
        ]);

        $filtered = FundebTransferScope::municipalSnapshotsOnly([$ufRow, $municipal]);
        $this->assertCount(1, $filtered);
        $this->assertSame('tesouro_csv', $filtered[0]->fonte);

        $primary = FundebExtratoFontePriority::pickPrimaryFundebRows([$ufRow, $municipal]);
        $this->assertCount(1, $primary);
        $this->assertSame(120.0, $primary[0]->valor);
    }

    #[Test]
    public function slug_anual_inclui_municipio_ibge_e_ano(): void
    {
        $city = new City([
            'name' => 'Salvador',
            'uf' => 'BA',
            'ibge_municipio' => '2927408',
        ]);

        $this->assertSame('salvador-ba-2927408-2025', FundebTransferScope::cityYearSlug($city, 2025));
    }
}
