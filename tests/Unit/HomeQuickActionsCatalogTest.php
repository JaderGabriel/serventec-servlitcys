<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Dashboard\HomeQuickActionsCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HomeQuickActionsCatalogTest extends TestCase
{
    private function baseStats(): array
    {
        return [
            'cities' => 10,
            'cities_active' => 5,
            'cities_ready' => 3,
            'cities_this_month' => 1,
            'users' => 4,
            'users_active' => 3,
        ];
    }

    private function baseOps(): array
    {
        return [
            'sync_pending' => 2,
            'sync_failed_24h' => 0,
            'pdf_pending' => 1,
            'pgsql' => 2,
            'mysql' => 3,
        ];
    }

    #[Test]
    public function sections_prioritize_operational_destinations_without_analytics_tabs(): void
    {
        $sections = HomeQuickActionsCatalog::sections($this->baseStats(), $this->baseOps(), null);

        $this->assertNull(collect($sections)->firstWhere('id', 'consultoria'));

        $allIds = collect($sections)
            ->flatMap(fn (array $s): array => collect($s['actions'])->pluck('id')->all())
            ->all();

        $this->assertContains('sync_queue', $allIds);
        $this->assertContains('rx', $allIds);
        $this->assertContains('public_data', $allIds);
        $this->assertContains('connections', $allIds);
        $this->assertNotContains('discrepancies', $allIds);
        $this->assertNotContains('analytics', $allIds);
        $this->assertNotContains('fundeb', $allIds);
        $this->assertNotContains('finance_realtime', $allIds);
    }

    #[Test]
    public function sync_queue_shows_combined_badge(): void
    {
        $sections = HomeQuickActionsCatalog::sections($this->baseStats(), $this->baseOps(), null);

        $operacao = collect($sections)->firstWhere('id', 'operacao');
        $queue = collect($operacao['actions'] ?? [])->firstWhere('id', 'sync_queue');
        $this->assertSame('3', $queue['badge'] ?? null);
    }

    #[Test]
    public function horizonte_appears_when_user_can_view(): void
    {
        $admin = new User(['role' => UserRole::Admin, 'is_active' => true]);

        $sections = HomeQuickActionsCatalog::sections(
            $this->baseStats(),
            $this->baseOps(),
            $admin,
        );

        $visao = collect($sections)->firstWhere('id', 'visao');
        $this->assertNotNull(collect($visao['actions'])->firstWhere('id', 'horizonte'));

        $municipal = new User(['role' => UserRole::Municipal, 'is_active' => true]);
        $sectionsMunicipal = HomeQuickActionsCatalog::sections(
            $this->baseStats(),
            $this->baseOps(),
            $municipal,
        );
        $visaoMunicipal = collect($sectionsMunicipal)->firstWhere('id', 'visao');
        $this->assertNull(collect($visaoMunicipal['actions'])->firstWhere('id', 'horizonte'));
    }

    #[Test]
    public function users_section_only_when_can_manage_users(): void
    {
        $sections = HomeQuickActionsCatalog::sections($this->baseStats(), $this->baseOps(), null);
        $this->assertNull(collect($sections)->firstWhere('id', 'gestao'));

        $admin = new User(['role' => UserRole::Admin, 'is_active' => true]);
        $withAdmin = HomeQuickActionsCatalog::sections($this->baseStats(), $this->baseOps(), $admin);
        $gestao = collect($withAdmin)->firstWhere('id', 'gestao');
        $this->assertNotNull(collect($gestao['actions'])->firstWhere('id', 'users'));
    }
}
