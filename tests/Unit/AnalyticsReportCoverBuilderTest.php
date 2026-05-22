<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsReportCoverBuilder;
use App\Services\Analytics\AnalyticsReportCoverMapResolver;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class AnalyticsReportCoverBuilderTest extends TestCase
{
    #[Test]
    public function municipality_line_usa_formato_municipio_uf(): void
    {
        $builder = new AnalyticsReportCoverBuilder(new AnalyticsReportCoverMapResolver());

        $line = (new ReflectionMethod($builder, 'municipalityLine'))
            ->invoke($builder, 'Itamari', 'BA', 'Bahia');

        $this->assertSame('Itamari - BA', $line);
        $this->assertStringNotContainsString('—', $line);
    }

    #[Test]
    public function municipality_subtitle_agrega_estado_e_regiao_sem_repetir_uf(): void
    {
        $builder = new AnalyticsReportCoverBuilder(new AnalyticsReportCoverMapResolver());

        $subtitle = (new ReflectionMethod($builder, 'municipalitySubtitle'))
            ->invoke($builder, 'Bahia', 'Nordeste');

        $this->assertSame('Bahia · Nordeste', $subtitle);
    }
}
