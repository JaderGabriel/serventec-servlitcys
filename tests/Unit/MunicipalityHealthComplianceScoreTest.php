<?php

namespace Tests\Unit;

use App\Repositories\Ieducar\MunicipalityHealthRepository;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class MunicipalityHealthComplianceScoreTest extends TestCase
{
    #[Test]
    public function indice_penaliza_pendencias_mesmo_com_pct_zero(): void
    {
        $repo = app(MunicipalityHealthRepository::class);
        $method = new ReflectionMethod(MunicipalityHealthRepository::class, 'computeComplianceScore');
        $method->setAccessible(true);

        $dimensions = [
            [
                'availability' => 'available',
                'has_issue' => true,
                'status' => 'danger',
                'severity' => 'danger',
                'pct_rede' => 0,
                'occurrences_total' => 120,
                'perda_estimada_anual' => 50_000.0,
            ],
            [
                'availability' => 'available',
                'has_issue' => true,
                'status' => 'warning',
                'severity' => 'warning',
                'pct_rede' => 2.5,
                'occurrences_total' => 30,
                'perda_estimada_anual' => 10_000.0,
            ],
        ];

        $score = $method->invoke($repo, $dimensions, []);

        $this->assertLessThan(80, $score);
        $this->assertGreaterThan(0, $score);
    }
}
