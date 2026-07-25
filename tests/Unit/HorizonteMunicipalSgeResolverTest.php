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
    public function resolves_consultoria_active_as_ieducar_serventec(): void
    {
        $result = $this->resolver->resolve('2910800', [
            'consultoria_active' => true,
            'db_driver' => 'pgsql',
            'ieducar_app_url' => 'https://ieducar.exemplo.gov.br',
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('consultoria_active', $result['status']);
        $this->assertSame('iEducar', $result['system']);
        $this->assertSame('Serventec', $result['company']);
        $this->assertSame('ieducar', $result['badge']);
        $this->assertSame('https://ieducar.exemplo.gov.br', $result['app_url']);
        $this->assertStringContainsString('Serventec', (string) $result['system_label']);
    }

    #[Test]
    public function catalog_without_consultoria_does_not_assume_ieducar(): void
    {
        $result = $this->resolver->resolve('2910800', [
            'consultoria_active' => false,
            'has_data_setup' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($result['found']);
        $this->assertSame('not_found', $result['status']);
        $this->assertNull($result['system']);
    }

    #[Test]
    public function resolves_external_registry_when_not_consultoria(): void
    {
        $result = $this->resolver->resolve('3550308', [
            'consultoria_active' => false,
        ], [
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
    public function resolves_from_procurement_sample_when_no_registry(): void
    {
        $result = $this->resolver->resolve('2915700', null, null, [
            'samples' => [[
                'itens_software' => true,
                'vendor_label' => 'Proesc',
                'fornecedor_nome' => 'PROESC LTDA',
                'fornecedor_cnpj' => '33324175000103',
            ]],
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('market', $result['status']);
        $this->assertSame('Proesc', $result['system']);
        $this->assertSame('33324175000103', $result['cnpj']);
        $this->assertSame('PROESC LTDA', $result['company']);
    }

    #[Test]
    public function manual_admin_registry_notes_competition_intelligence(): void
    {
        $result = $this->resolver->resolve('3550308', null, [
            'system' => 'Proesc',
            'source' => 'manual_admin',
        ]);

        $this->assertStringContainsString('concorrência', strtolower($result['detail']));
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
