<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Auth\DatabaseSessionUserSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatabaseSessionUserSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_preenche_user_id_na_linha_da_sessao_actual(): void
    {
        config(['session.registry_mirror' => true]);

        $user = User::factory()->admin()->create();
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => base64_encode(''),
            'last_activity' => time() - 60,
        ]);

        $session = app('session.store');
        $session->setId($sessionId);

        $request = Request::create('/users/sessoes', 'GET');
        $request->setLaravelSession($session);

        Auth::login($user);

        app(DatabaseSessionUserSync::class)->syncAuthenticated($request);

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function sync_cria_linhas_distintas_por_session_id(): void
    {
        config(['session.registry_mirror' => true]);

        $user = User::factory()->admin()->create();
        $sync = app(DatabaseSessionUserSync::class);

        foreach (['device-a', 'device-b'] as $label) {
            $sessionId = hash('sha256', $label);
            $session = app('session.store');
            $session->setId($sessionId);

            $request = Request::create('/', 'GET');
            $request->setLaravelSession($session);
            Auth::login($user);

            $sync->syncAuthenticated($request);
        }

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
