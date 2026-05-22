<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsReportMunicipalityOutlineSvg;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsReportMunicipalityOutlineSvgTest extends TestCase
{
    #[Test]
    public function it_renders_municipality_outline_without_school_markers(): void
    {
        $result = AnalyticsReportMunicipalityOutlineSvg::render(
            'Salvador',
            'BA',
            -12.97,
            -38.51,
            400,
            200,
            '#0f766e',
        );

        $this->assertNotNull($result);
        $this->assertArrayHasKey('data_uri', $result);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $result['data_uri']);
        $svg = base64_decode(substr($result['data_uri'], strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('Salvador', $svg);
        $this->assertStringContainsString('sem unidades escolares', $svg);
        $this->assertStringContainsString('<path', $svg);
    }
}
