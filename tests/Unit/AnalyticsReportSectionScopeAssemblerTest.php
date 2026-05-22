<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsReportSectionScopeAssembler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportSectionScopeAssemblerTest extends TestCase
{
    #[Test]
    public function classe_carrega_sem_erro_de_sintaxe(): void
    {
        $this->assertTrue(class_exists(AnalyticsReportSectionScopeAssembler::class));
        $this->assertNotNull(app(AnalyticsReportSectionScopeAssembler::class));
    }
}
