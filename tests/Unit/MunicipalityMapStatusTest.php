<?php

namespace Tests\Unit;

use App\Support\Dashboard\MunicipalityMapStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalityMapStatusTest extends TestCase
{
    #[Test]
    public function legend_items_incluem_quatro_estados_e_contagens(): void
    {
        $items = MunicipalityMapStatus::legendItems([
            'ready' => 2,
            'incomplete' => 1,
            'inactive' => 3,
        ]);

        $this->assertCount(4, $items);
        $ready = collect($items)->firstWhere('status', 'ready');
        $this->assertSame(2, $ready['count']);
        $this->assertSame(MunicipalityMapStatus::COLORS['ready'], $ready['color']);
        $this->assertSame('#64748b', MunicipalityMapStatus::colorFor('inactive_setup'));
    }
}
