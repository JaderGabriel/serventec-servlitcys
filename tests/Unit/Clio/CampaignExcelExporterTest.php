<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Export\CampaignExcelExporter;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignExcelExporterTest extends TestCase
{
    #[Test]
    public function strip_pii_hint_redacts_eleven_digit_sequences(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $method = new ReflectionMethod(CampaignExcelExporter::class, 'stripPiiHint');
        $method->setAccessible(true);

        $this->assertSame(
            'Aluno [redacted] sem turma',
            $method->invoke($exporter, 'Aluno 12345678901 sem turma'),
        );
    }
}
