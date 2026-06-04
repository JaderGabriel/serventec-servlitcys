<?php

namespace Tests\Unit;

use App\Support\Http\SafeOutboundUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SafeOutboundUrlTest extends TestCase
{
    #[Test]
    public function bloqueia_localhost_e_ips_privados(): void
    {
        $this->assertFalse(SafeOutboundUrl::isAllowedHttpUrl('http://127.0.0.1/data.csv'));
        $this->assertFalse(SafeOutboundUrl::isAllowedHttpUrl('http://localhost/data.csv'));
        $this->assertFalse(SafeOutboundUrl::isAllowedHttpUrl('http://192.168.1.10/x.csv'));
        $this->assertFalse(SafeOutboundUrl::isAllowedHttpUrl('file:///etc/passwd'));
    }

    #[Test]
    public function aceita_url_publica_https(): void
    {
        $this->assertTrue(SafeOutboundUrl::isAllowedHttpUrl('https://dados.gov.br/dataset/file.csv'));
    }
}
