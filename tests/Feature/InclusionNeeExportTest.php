<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InclusionNeeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_queue_inclusion_export(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.analytics.inclusion.export.queue'), [
            'format' => 'csv',
            'city_id' => $city->id,
            'ano_letivo' => '2024',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_queue_inclusion_export(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $city = City::factory()->create();

        $response = $this->actingAs($admin)->post(route('dashboard.analytics.inclusion.export.queue'), [
            'format' => 'xlsx',
            'city_id' => $city->id,
            'ano_letivo' => '2024',
            'inclusion_scope' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('admin_sync_queued');
        $this->assertDatabaseHas('admin_sync_tasks', [
            'domain' => 'ieducar',
            'task_key' => 'inclusion_nee_export',
            'city_id' => $city->id,
        ]);
    }
}
