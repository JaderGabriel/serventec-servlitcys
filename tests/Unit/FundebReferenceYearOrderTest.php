<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebReferenceYearOrder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ordem de tentativa de anos ao resolver VAAF (vigente → anos anteriores).
 */
final class FundebReferenceYearOrderTest extends TestCase
{
    /**
     * Cenário: filtro do painel em 2024 — buscar primeiro 2024, depois 2023, etc.
     * Impacto: alinha com defasagem de publicação FNDE sem sobrescrever ano errado.
     */
    #[Test]
    public function candidate_years_comeca_no_ano_ancora(): void
    {
        $years = FundebReferenceYearOrder::candidateYears(2024, 3);

        $this->assertSame([2024, 2023, 2022, 2021], $years);
    }

    /**
     * Cenário: maxPastYears=0 — só o ano âncora (importação estrita por ano letivo).
     */
    #[Test]
    public function candidate_years_respeita_max_past_zero(): void
    {
        $years = FundebReferenceYearOrder::candidateYears(2024, 0);

        $this->assertSame([2024], $years);
    }

    /**
     * Cenário: ano muito antigo — não desce abaixo de MIN_YEAR (evita queries inválidas).
     */
    #[Test]
    public function candidate_years_para_no_min_year(): void
    {
        $years = FundebReferenceYearOrder::candidateYears(2002, 10);

        $this->assertContains(2002, $years);
        $this->assertContains(2000, $years);
        $this->assertNotContains(1999, $years);
    }
}
