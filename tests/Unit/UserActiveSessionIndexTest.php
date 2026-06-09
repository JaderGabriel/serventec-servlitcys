<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Auth\UserActiveSessionIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserActiveSessionIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lista_varias_sessoes_do_mesmo_usuario(): void
    {
        config(['session.driver' => 'redis', 'session.registry_mirror' => true]);

        $user = User::factory()->admin()->create();

        foreach (['device-a', 'device-b'] as $suffix) {
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => $user->id,
                'ip_address' => $suffix === 'device-a' ? '203.0.113.10' : '198.51.100.20',
                'user_agent' => 'Mozilla/5.0 Test/'.$suffix,
                'payload' => base64_encode(''),
                'last_activity' => time(),
            ]);
        }

        $request = Request::create('/users/sessoes', 'GET');
        Auth::login($user);

        $paginator = app(UserActiveSessionIndex::class)->paginate($request, 40);

        $this->assertSame(2, $paginator->total());
        $this->assertSame(2, $paginator->getCollection()->where('user_id', $user->id)->count());
    }
}
