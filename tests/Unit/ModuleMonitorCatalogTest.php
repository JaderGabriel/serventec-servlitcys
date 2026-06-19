<?php

namespace Tests\Unit;

use App\Enums\AdminSyncDomain;
use App\Support\Admin\ModuleMonitorCatalog;
use Tests\TestCase;

class ModuleMonitorCatalogTest extends TestCase
{
    public function test_modules_include_core_areas(): void
    {
        $ids = array_column(ModuleMonitorCatalog::modules(), 'id');

        $this->assertContains('analytics', $ids);
        $this->assertContains('educacenso', $ids);
        $this->assertContains('geo', $ids);
        $this->assertContains('queue', $ids);
    }

    public function test_sync_domain_maps_to_module(): void
    {
        $ids = ModuleMonitorCatalog::moduleIdsForSyncDomain(AdminSyncDomain::Geo->value);

        $this->assertContains('geo', $ids);
    }

    public function test_pulse_key_resolves_module(): void
    {
        $this->assertSame(
            'analytics',
            ModuleMonitorCatalog::moduleIdForPulseKey('analytics:tab:inclusion|cid:1')
        );

        $this->assertSame(
            'educacenso',
            ModuleMonitorCatalog::moduleIdForPulseKey('educacenso:analysis|cid:1')
        );
    }

    public function test_admin_and_queue_urls(): void
    {
        $geo = ModuleMonitorCatalog::find('geo');
        $this->assertNotNull($geo);
        $this->assertStringContainsString('geo-sync', ModuleMonitorCatalog::adminUrl($geo) ?? '');
        $this->assertStringContainsString('fila-geo', ModuleMonitorCatalog::queueUrl($geo) ?? '');

        $educacenso = ModuleMonitorCatalog::find('educacenso');
        $this->assertNotNull($educacenso);
        $this->assertStringContainsString('tab=work_done', ModuleMonitorCatalog::adminUrl($educacenso) ?? '');
    }
}
