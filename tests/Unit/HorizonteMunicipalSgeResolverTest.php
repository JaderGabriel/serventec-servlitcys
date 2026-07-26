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
                'valor' => 100000.0,
            ]],
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('market', $result['status']);
        $this->assertSame('Proesc', $result['system']);
        $this->assertSame('33324175000103', $result['cnpj']);
        $this->assertSame('PROESC LTDA', $result['company']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('municipal', $result['evidence_level']);
    }

    #[Test]
    public function national_top_vendors_do_not_become_municipal_incumbent(): void
    {
        $result = $this->resolver->resolve('2910800', [
            'consultoria_active' => false,
        ], null, [
            'samples' => [],
            'top_vendors' => [
                ['label' => 'Totvs', 'count' => 90, 'cnpj' => '53113791000122'],
            ],
            'vendor_matched' => 90,
        ]);

        $this->assertFalse($result['found']);
        $this->assertSame('not_found', $result['status']);
        $this->assertNull($result['system']);
        $this->assertSame([], $result['candidates']);
    }

    #[Test]
    public function multiple_municipal_vendors_yield_candidates_without_single_incumbent(): void
    {
        $result = $this->resolver->resolve('2915700', null, null, [
            'samples' => [
                [
                    'vendor_matched' => true,
                    'vendor_label' => 'Proesc',
                    'fornecedor_nome' => 'PROESC LTDA',
                    'fornecedor_cnpj' => '33324175000103',
                    'valor' => 50000.0,
                ],
                [
                    'itens_software' => true,
                    'vendor_label' => 'Portabilis',
                    'fornecedor_nome' => 'PORTABILIS TECNOLOGIA',
                    'fornecedor_cnpj' => '12345678000199',
                    'valor' => 80000.0,
                ],
            ],
        ]);

        $this->assertFalse($result['found']);
        $this->assertSame('market_candidates', $result['status']);
        $this->assertNull($result['system']);
        $this->assertCount(2, $result['candidates']);
        $this->assertSame('municipal_ambiguous', $result['evidence_level']);
    }

    #[Test]
    public function weak_software_flag_without_company_or_cnpj_is_ignored(): void
    {
        $result = $this->resolver->resolve('2915700', null, null, [
            'samples' => [[
                'itens_software' => true,
                'objeto' => 'Aquisição de software genérico',
            ]],
        ]);

        $this->assertFalse($result['found']);
        $this->assertSame('not_found', $result['status']);
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
