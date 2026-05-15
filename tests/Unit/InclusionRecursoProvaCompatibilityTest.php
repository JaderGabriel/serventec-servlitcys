<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionRecursoProvaQueries;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class InclusionRecursoProvaCompatibilityTest extends TestCase
{
    public function test_passes_when_recurso_matches_deficiencia_keyword(): void
    {
        $method = new ReflectionMethod(InclusionRecursoProvaQueries::class, 'passesCompatibilityRules');
        $method->setAccessible(true);

        $ok = $method->invoke(null, ['Surdo — intérprete'], ['Deficiência auditiva'], [
            ['recurso' => ['surdo'], 'deficiencia' => ['auditiva']],
        ]);

        $this->assertTrue($ok);
    }

    public function test_fails_when_recurso_requires_missing_deficiencia(): void
    {
        $method = new ReflectionMethod(InclusionRecursoProvaQueries::class, 'passesCompatibilityRules');
        $method->setAccessible(true);

        $ok = $method->invoke(null, ['Ledor'], ['Deficiência intelectual'], [
            ['recurso' => ['ledor'], 'deficiencia' => ['visual', 'baixa visão']],
        ]);

        $this->assertFalse($ok);
    }
}
