<?php

namespace Tests\Unit;

use App\Support\Dashboard\HomeQuickActionsCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HomeQuickActionsCatalogTest extends TestCase
{
    #[Test]
    public function sections_include_consultoria_and_queue_badge(): void
    {
        $sections = HomeQuickActionsCatalog::sections(
            ['cities' => 10, 'cities_active' => 5, 'cities_ready' => 3, 'cities_this_month' => 1, 'users' => 4, 'users_active' => 3],
            ['sync_pending' => 2, 'sync_failed_24h' => 0, 'pdf_pending' => 1, 'pgsql' => 2, 'mysql' => 3],
            null,
        );

        $this->assertCount(3, $sections);
        $consultoria = collect($sections)->firstWhere('id', 'consultoria');
        $this->assertNotNull($consultoria);
        $this->assertTrue(
            collect($consultoria['actions'])->contains(fn (array $a): bool => $a['id'] === 'discrepancies' && $a['featured'] === true),
        );

        $operacao = collect($sections)->firstWhere('id', 'operacao');
        $queue = collect($operacao['actions'] ?? [])->firstWhere('id', 'sync_queue');
        $this->assertSame('3', $queue['badge'] ?? null);
    }

    #[Test]
    public function finance_realtime_link_follows_config(): void
    {
        config(['ieducar.finance_realtime.enabled' => false]);
        $off = HomeQuickActionsCatalog::sections(
            ['cities' => 0, 'cities_active' => 0, 'cities_ready' => 0, 'cities_this_month' => 0, 'users' => 0, 'users_active' => 0],
            ['sync_pending' => 0, 'sync_failed_24h' => 0, 'pdf_pending' => 0, 'pgsql' => 0, 'mysql' => 0],
            null,
        );
        $consultoriaOff = collect($off)->firstWhere('id', 'consultoria');
        $this->assertNull(collect($consultoriaOff['actions'])->firstWhere('id', 'finance_realtime'));

        config(['ieducar.finance_realtime.enabled' => true]);
        $on = HomeQuickActionsCatalog::sections(
            ['cities' => 0, 'cities_active' => 0, 'cities_ready' => 0, 'cities_this_month' => 0, 'users' => 0, 'users_active' => 0],
            ['sync_pending' => 0, 'sync_failed_24h' => 0, 'pdf_pending' => 0, 'pgsql' => 0, 'mysql' => 0],
            null,
        );
        $consultoriaOn = collect($on)->firstWhere('id', 'consultoria');
        $this->assertNotNull(collect($consultoriaOn['actions'])->firstWhere('id', 'finance_realtime'));
    }
}
