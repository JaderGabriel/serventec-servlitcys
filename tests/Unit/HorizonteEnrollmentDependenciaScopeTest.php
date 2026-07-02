<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteEnrollmentDependenciaScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteEnrollmentDependenciaScopeTest extends TestCase
{
    #[Test]
    public function normaliza_variacoes_de_dependencia(): void
    {
        $this->assertSame(HorizonteEnrollmentDependenciaScope::TOTAL, HorizonteEnrollmentDependenciaScope::normalize(null));
        $this->assertSame(HorizonteEnrollmentDependenciaScope::MUNICIPAL, HorizonteEnrollmentDependenciaScope::normalize('rede_municipal'));
        $this->assertSame(HorizonteEnrollmentDependenciaScope::NAO_MUNICIPAL, HorizonteEnrollmentDependenciaScope::normalize('nao-municipal'));
    }

    #[Test]
    public function resolve_colunas_por_âmbito(): void
    {
        $this->assertSame('matriculas_total', HorizonteEnrollmentDependenciaScope::column('matriculas_total', HorizonteEnrollmentDependenciaScope::TOTAL));
        $this->assertSame('matriculas_municipal', HorizonteEnrollmentDependenciaScope::column('matriculas_total', HorizonteEnrollmentDependenciaScope::MUNICIPAL));
        $this->assertSame('matriculas_regular_nao_municipal', HorizonteEnrollmentDependenciaScope::column('matriculas_regular', HorizonteEnrollmentDependenciaScope::NAO_MUNICIPAL));
        $this->assertSame('matriculas_infantil_municipal', HorizonteEnrollmentDependenciaScope::column('matriculas_infantil', HorizonteEnrollmentDependenciaScope::MUNICIPAL));
    }
}
