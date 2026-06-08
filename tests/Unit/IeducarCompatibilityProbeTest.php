<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarCompatibilityProbeTest extends TestCase
{
    #[Test]
    public function filters_for_discrepancies_usa_ano_vigente_quando_probe_em_todos(): void
    {
        $filters = new IeducarFilterState(ano_letivo: 'all', escola_id: null, curso_id: null, turno_id: null);

        $resolved = IeducarCompatibilityProbe::filtersForDiscrepancies($filters);

        $this->assertSame((string) IeducarCompatibilityProbe::vigenteSchoolYear(), $resolved->ano_letivo);
    }

    #[Test]
    public function filters_for_discrepancies_respeita_ano_letivo_explicito(): void
    {
        $filters = new IeducarFilterState(ano_letivo: '2023', escola_id: null, curso_id: null, turno_id: null);

        $resolved = IeducarCompatibilityProbe::filtersForDiscrepancies($filters);

        $this->assertSame('2023', $resolved->ano_letivo);
    }
}
