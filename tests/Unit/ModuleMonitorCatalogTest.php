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
            'geo',
            ModuleMonitorCatalog::moduleIdForPulseKey('sync:geo:pipeline|cid:2')
        );
    }
}
