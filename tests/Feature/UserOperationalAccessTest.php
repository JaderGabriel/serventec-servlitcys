<?php

namespace Tests\Feature;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOperationalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilizador_can_open_documentation_reader(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('documentation.show', ['doc' => 'docs/README.md']))
            ->assertOk()
            ->assertSee(__('Índice da documentação'), false);
    }

    public function test_utilizador_cannot_open_admin_only_documentation(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('documentation.show', ['doc' => 'docs/VARIAVEIS_AMBIENTE.md']))
            ->assertNotFound();
    }

    public function test_utilizador_can_open_sync_queue_index(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('sync-queue.index'))
            ->assertOk();
    }

    public function test_utilizador_cannot_view_other_users_sync_task(): void
    {
        $owner = User::factory()->utilizador()->create();
        $other = User::factory()->utilizador()->create();
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Ieducar->value,
            'task_key' => 'inclusion_nee_export',
            'label' => 'Exportação NEE teste',
            'status' => AdminSyncTaskStatus::Pending->value,
            'queued_by_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('sync-queue.show', $task))
            ->assertForbidden();
    }

    public function test_non_admin_still_cannot_access_admin_documentation_route(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('admin.documentation.index'))
            ->assertForbidden();
    }
}
