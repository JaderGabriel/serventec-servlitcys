<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\User;
use App\Support\Dashboard\AnalyticsExportCatalog;
use App\Support\Dashboard\IeducarFilterState;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsExportCatalogTest extends TestCase
{
    #[Test]
    public function menu_agrupa_exportacoes_por_area(): void
    {
        $user = new User(['role' => 'admin', 'is_active' => true]);
        $city = new City(['id' => 7, 'name' => 'Testópolis', 'uf' => 'SP']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $groups = AnalyticsExportCatalog::menu($user, $city, $filters, true);
        $ids = array_column($groups, 'id');

        $this->assertContains('discrepancies', $ids);
        $this->assertContains('comparativo', $ids);
        $this->assertContains('cadunico', $ids);
        $this->assertContains('inclusion', $ids);
        $this->assertContains('report', $ids);
    }
}
