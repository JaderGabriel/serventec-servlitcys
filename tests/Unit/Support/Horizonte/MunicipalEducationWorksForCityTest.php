<?php

namespace Tests\Unit\Support\Horizonte;

use App\Support\Horizonte\MunicipalEducationWorksForCity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalEducationWorksForCityTest extends TestCase
{
    #[Test]
    public function empty_ibge_returns_empty_payload(): void
    {
        $payload = MunicipalEducationWorksForCity::payloadForIbge(null);

        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['markers']);
        $this->assertSame([], $payload['without_geo']);
        $this->assertNotSame('', $payload['simec_url']);
    }

    #[Test]
    public function invalid_ibge_returns_empty_payload(): void
    {
        $payload = MunicipalEducationWorksForCity::payloadForIbge('abc');

        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['markers']);
    }
}
