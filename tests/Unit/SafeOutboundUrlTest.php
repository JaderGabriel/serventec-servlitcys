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

    #[Test]
    public function bloqueia_quando_dns_nao_resolve(): void
    {
        // TLD .invalid é reservado (RFC 2606) e não resolve em DNS públicos.
        $this->assertFalse(
            SafeOutboundUrl::isAllowedHttpUrl('https://host-inexistente-servlitcys.invalid/arquivo.csv')
        );
    }
}
