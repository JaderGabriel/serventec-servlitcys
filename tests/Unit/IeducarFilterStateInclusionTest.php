<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\InclusionMatriculaScope;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarFilterStateInclusionTest extends TestCase
{
    #[Test]
    public function from_request_reads_inclusion_filter_flags(): void
    {
        $request = Request::create('/', 'GET', [
            'ano_letivo' => '2024',
            'inclusion_somente_nee' => '1',
        ]);

        $filters = IeducarFilterState::fromRequest($request);

        $this->assertTrue($filters->inclusionSomenteNee());
        $this->assertFalse($filters->inclusionSomenteInconsistencias());
    }

    #[Test]
    public function to_query_params_includes_active_inclusion_filters(): void
    {
        $filters = new IeducarFilterState(
            ano_letivo: '2024',
            escola_id: null,
            curso_id: null,
            turno_id: null,
            inclusion_somente_inconsistencias: true,
        );

        $this->assertSame(['ano_letivo' => '2024', 'inclusion_somente_inconsistencias' => '1'], $filters->toQueryParams());
        $this->assertTrue(InclusionMatriculaScope::isActive($filters));
    }
}
