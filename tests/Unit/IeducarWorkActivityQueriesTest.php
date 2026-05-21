<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use Tests\TestCase;

class IeducarWorkActivityQueriesTest extends TestCase
{
    public function test_year_closure_insight_marca_consolidado_sem_cadastro_recente(): void
    {
        $insight = IeducarWorkActivityQueries::yearClosureInsight(
            new IeducarFilterState('2025', null, null, null),
            [
                'summary' => [
                    'total_escolas' => 10,
                    'exportadas' => 6,
                    'fechadas' => 4,
                    'pendentes' => 0,
                ],
            ],
            ['day' => 0, 'week' => 0, 'fortnight' => 0],
            null,
        );

        $this->assertNotNull($insight);
        $this->assertTrue($insight['consolidated']);
    }
}
