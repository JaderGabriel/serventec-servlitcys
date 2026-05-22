<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebMatrixCellPresentation;
use App\Support\Fundeb\FundebReferenceSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebMatrixCellPresentationTest extends TestCase
{
    #[Test]
    public function classifica_fontes_para_legenda_da_matriz(): void
    {
        $national = FundebMatrixCellPresentation::forFonte(FundebReferenceSource::FONTE_NACIONAL, true);
        $this->assertSame(FundebMatrixCellPresentation::KIND_NATIONAL, $national['kind']);

        $preview = FundebMatrixCellPresentation::forFonte(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR, true);
        $this->assertSame(FundebMatrixCellPresentation::KIND_PREVIEW, $preview['kind']);

        $consolidated = FundebMatrixCellPresentation::forFonte('api_ckan_fnde', true);
        $this->assertSame(FundebMatrixCellPresentation::KIND_CONSOLIDATED, $consolidated['kind']);

        $empty = FundebMatrixCellPresentation::forFonte(null, false);
        $this->assertSame(FundebMatrixCellPresentation::KIND_EMPTY, $empty['kind']);
    }
}
