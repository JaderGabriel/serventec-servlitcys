<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteMunicipalSgeResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMunicipalSgeResolverTest extends TestCase
{
    private HorizonteMunicipalSgeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new HorizonteMunicipalSgeResolver;
    }

    #[Test]
    public function resolves_consultoria_active_from_catalog(): void
    {
        $result = $this->resolver->resolve('2910800', [
            'consultoria_active' => true,
            'db_driver' => 'pgsql',
            'ieducar_app_url' => 'https://ieducar.exemplo.gov.br',
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('consultoria_active', $result['status']);
        $this->assertSame('i-Educar', $result['system']);
        $this->assertSame('https://ieducar.exemplo.gov.br', $result['app_url']);
    }

    #[Test]
    public function resolves_catalog_pending_when_city_without_setup(): void
    {
        $result = $this->resolver->resolve('2910800', [
            'consultoria_active' => false,
            'has_data_setup' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('catalog_pending', $result['status']);
        $this->assertSame('i-Educar', $result['system']);
    }

    #[Test]
    public function resolves_external_registry_when_not_in_catalog(): void
    {
        $result = $this->resolver->resolve('3550308', null, [
            'system' => 'GDAE',
            'vendor' => 'SME-SP',
            'notes' => 'Portal municipal',
            'app_url' => 'https://portal.exemplo.sp.gov.br',
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('registry', $result['status']);
        $this->assertSame('GDAE', $result['system']);
        $this->assertStringContainsString('GDAE', $result['system_label']);
    }

    #[Test]
    public function returns_not_found_without_blocking_payload(): void
    {
        $result = $this->resolver->resolve('9999999', null, null);

        $this->assertFalse($result['found']);
        $this->assertSame('not_found', $result['status']);
        $this->assertNull($result['system']);
        $this->assertSame('none', $result['source']);
    }
}
