<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionDashboardQueries;
use Tests\TestCase;

final class InclusionNeeQueryAlignmentTest extends TestCase
{
    public function test_incluir_turma_aee_default_is_true(): void
    {
        $this->assertTrue((bool) config('ieducar.inclusion.nee_incluir_turma_aee', true));
    }

    public function test_incluir_turma_aee_respects_env_config(): void
    {
        config(['ieducar.inclusion.nee_incluir_turma_aee' => false]);
        $this->assertFalse(InclusionDashboardQueries::incluirTurmaAeeNoRecorteNee());
    }
}
