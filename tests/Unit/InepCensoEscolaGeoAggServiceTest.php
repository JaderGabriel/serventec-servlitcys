<?php

namespace Tests\Unit;

use App\Services\Inep\InepCensoEscolaGeoAggService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InepCensoEscolaGeoAggServiceTest extends TestCase
{
    #[Test]
    public function converte_strings_latin1_para_utf8_valido(): void
    {
        $service = new InepCensoEscolaGeoAggService();
        $method = new \ReflectionMethod($service, 'toUtf8');
        $method->setAccessible(true);

        $latin1 = mb_convert_encoding('Rondônia', 'ISO-8859-1', 'UTF-8');
        $utf8 = $method->invoke($service, $latin1);

        $this->assertSame('Rondônia', $utf8);
        $this->assertTrue(mb_check_encoding($utf8, 'UTF-8'));
    }
}
