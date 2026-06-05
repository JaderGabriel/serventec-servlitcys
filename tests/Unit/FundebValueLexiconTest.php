<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebValueLexicon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebValueLexiconTest extends TestCase
{
    #[Test]
    public function classifica_exercicio_corrente_como_em_formacao(): void
    {
        $cy = (int) date('Y');
        $this->assertSame(
            FundebValueLexicon::PHASE_IN_PROGRESS,
            FundebValueLexicon::exercisePhase($cy, $cy),
        );
    }

    #[Test]
    public function classifica_proximo_ano_como_projecao(): void
    {
        $cy = (int) date('Y');
        $this->assertSame(
            FundebValueLexicon::PHASE_PROJECTION,
            FundebValueLexicon::exercisePhase($cy + 1, $cy),
        );
    }
}
