<?php

namespace Tests\Unit;

use App\Support\Fundeb\FundebFndePortariaCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebFndePortariaCatalogTest extends TestCase
{
    #[Test]
    public function usa_publicacao_mais_recente_para_2026(): void
    {
        $pub = FundebFndePortariaCatalog::activePublication(2026);
        $this->assertNotNull($pub);
        $this->assertSame('6', $pub['numero'] ?? null);
        $this->assertStringContainsString('2-publicacao', FundebFndePortariaCatalog::receitaCsvUrl(2026) ?? '');
    }

    #[Test]
    public function expoe_pisos_nacionais_da_portaria_6(): void
    {
        $pisos = FundebFndePortariaCatalog::nationalFloors(2026);
        $this->assertEqualsWithDelta(5954.14, $pisos['vaaf_min'], 0.01);
        $this->assertEqualsWithDelta(10193.74, $pisos['vaat_min'], 0.01);
    }
}
