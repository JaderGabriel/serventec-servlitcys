<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserSessionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_sessao_actual_quando_user_id_estava_nulo(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode(''),
            'last_activity' => time(),
        ]);

        $this->withSession([])
            ->actingAs($admin)
            ->session(['_token' => 'test']);

        $this->app['session']->setId($sessionId);

        $response = $this->get(route('users.sessions.index'));

        $response->assertOk();
        $response->assertSee($admin->username, false);
        $response->assertSee(__('Esta sessão'), false);

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_nao_pode_encerrar_a_propria_sessao_actual(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode(''),
            'last_activity' => time(),
        ]);

        $this->withSession([])
            ->actingAs($admin);

        $this->app['session']->setId($sessionId);

        $this->delete(route('users.sessions.destroy', $sessionId))
            ->assertRedirect(route('users.sessions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }
}
