<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebReferenceDisplay;
use App\Support\Fundeb\FundebReferenceSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebReferenceDisplayTest extends TestCase
{
    #[Test]
    public function classifica_fontes_para_legenda_da_matriz(): void
    {
        $national = FundebReferenceDisplay::forFonte(FundebReferenceSource::FONTE_NACIONAL, true);
        $this->assertSame(FundebReferenceDisplay::KIND_NATIONAL, $national['kind']);

        $preview = FundebReferenceDisplay::forFonte(FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR, true);
        $this->assertSame(FundebReferenceDisplay::KIND_PREVIEW, $preview['kind']);

        $consolidated = FundebReferenceDisplay::forFonte('api_ckan_fnde', true);
        $this->assertSame(FundebReferenceDisplay::KIND_CONSOLIDATED, $consolidated['kind']);

        $empty = FundebReferenceDisplay::forFonte(null, false);
        $this->assertSame(FundebReferenceDisplay::KIND_EMPTY, $empty['kind']);
    }
}
