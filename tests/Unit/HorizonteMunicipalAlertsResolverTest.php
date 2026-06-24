<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteMunicipalAlertsResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMunicipalAlertsResolverTest extends TestCase
{
    private HorizonteMunicipalAlertsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['horizonte.municipal_alerts.enabled' => true]);
        $this->resolver = new HorizonteMunicipalAlertsResolver;
    }

    #[Test]
    public function resolves_found_with_detail_url(): void
    {
        $result = $this->resolver->resolve([
            'items' => [[
                'kind' => 'vaat_inabilitado',
                'severity' => 'danger',
                'title' => 'Inabilitado VAAT',
                'detail' => 'SIOPE',
                'detail_url' => 'https://exemplo.gov.br/detalhe',
            ]],
        ], ['synced_at' => now()->toIso8601String(), 'sources' => ['fnde']]);

        $this->assertSame('found', $result['status']);
        $this->assertSame('https://exemplo.gov.br/detalhe', $result['detail_url']);
    }

    #[Test]
    public function resolves_clear_when_synced_and_no_items(): void
    {
        $result = $this->resolver->resolve(null, [
            'synced_at' => now()->toIso8601String(),
            'sources' => ['fnde'],
        ]);

        $this->assertSame('clear', $result['status']);
    }

    #[Test]
    public function resolves_unavailable_without_sync_meta(): void
    {
        $result = $this->resolver->resolve(null, null);

        $this->assertSame('unavailable', $result['status']);
    }
}
