<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModuleMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_module_monitor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.module-monitor.index'))
            ->assertOk()
            ->assertSee(__('Monitor de módulos'), false)
            ->assertSee(__('Saúde global'), false)
            ->assertSee(__('Abrir módulo'), false)
            ->assertSee(__('Falhas e lentidões'), false);
    }

    public function test_admin_can_filter_module_monitor_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.module-monitor.index', ['status' => 'healthy']))
            ->assertOk()
            ->assertSee(__('Limpar filtro'), false);
    }

    public function test_utilizador_cannot_open_module_monitor(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('admin.module-monitor.index'))
            ->assertForbidden();
    }
}
