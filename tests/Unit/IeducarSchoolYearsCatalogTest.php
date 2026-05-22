<?php

namespace Tests\Unit;

use App\Support\Ieducar\IeducarWorkActivityQueries;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarSchoolYearsCatalogTest extends TestCase
{
    #[Test]
    public function andamento_1_is_open_and_2_is_closed(): void
    {
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado(1, 'andamento'));
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado('1', 'andamento'));
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado(2, 'andamento'));
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado(3, 'andamento'));
    }

    #[Test]
    public function ativo_1_is_open(): void
    {
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado(1, 'ativo'));
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado(0, 'ativo'));
    }

    #[Test]
    public function fechado_column_1_means_closed(): void
    {
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado(1, 'fechado'));
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado(0, 'fechado'));
    }

    #[Test]
    public function situacao_text_detects_closed_and_open(): void
    {
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado('fechado', 'situacao'));
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado('encerrado', 'situacao'));
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado('em andamento', 'situacao'));
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado('aberto', 'situacao'));
    }

    #[Test]
    public function bare_numeric_1_without_column_is_not_auto_closed(): void
    {
        $this->assertFalse(IeducarWorkActivityQueries::isAnoLetivoFechado(1, null));
        $this->assertTrue(IeducarWorkActivityQueries::isAnoLetivoFechado(2, null));
    }

    #[Test]
    public function ano_letivo_state_from_andamento_labels(): void
    {
        $open = IeducarWorkActivityQueries::anoLetivoStateFromValue(1, 'andamento');
        $this->assertFalse($open['fechado']);
        $this->assertSame('Em andamento', $open['label']);

        $closed = IeducarWorkActivityQueries::anoLetivoStateFromValue(2, 'andamento');
        $this->assertTrue($closed['fechado']);
        $this->assertSame('Finalizado', $closed['label']);
    }
}
