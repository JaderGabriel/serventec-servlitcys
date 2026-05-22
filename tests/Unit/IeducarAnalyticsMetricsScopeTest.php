<?php

namespace Tests\Unit;

use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarAnalyticsMetricsScopeTest extends TestCase
{
    #[Test]
    public function normalize_distorcao_kpi_calcula_percentagem(): void
    {
        $kpi = IeducarAnalyticsMetricsScope::normalizeDistorcaoKpi([
            'com' => 25,
            'sem' => 75,
            'total' => 100,
            'fonte' => 'automatico',
            'metodo' => 'inep_pessoa_matricula_31mar',
            'cobertura_pct' => 80.0,
        ]);

        $this->assertNotNull($kpi);
        $this->assertSame(25.0, $kpi['pct']);
        $this->assertSame('inep_pessoa_matricula_31mar', $kpi['metodo']);
    }

    #[Test]
    public function forget_limpa_scope_ligado(): void
    {
        IeducarAnalyticsMetricsScope::forget();
        $this->assertNull(IeducarAnalyticsMetricsScope::resolve());
    }
}
