<?php

namespace Tests\Feature;

use App\Enums\NotificationPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AppMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_requer_autenticacao(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
    }

    #[Test]
    public function utilizador_ve_notificacoes_e_marca_como_lida(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Municipal,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->notify(new AppMessageNotification([
            'title' => 'Teste',
            'body' => 'Corpo',
            'icon' => 'info',
            'kind' => 'test',
            'priority' => NotificationPriority::Normal->value,
        ]));

        $user->notify(new AppMessageNotification([
            'title' => 'Crítico',
            'body' => 'Atenção',
            'icon' => 'error',
            'priority' => NotificationPriority::Critical->value,
        ]));

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('critical_unread_count', 1)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.is_critical', true);

        $this->actingAs($user)
            ->getJson(route('notifications.index', ['critical' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $id = $user->unreadNotifications->first()->id;

        $this->actingAs($user)
            ->postJson(route('notifications.read', ['id' => $id]))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('critical_unread_count', 0);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function marcar_todas_como_lidas(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->notify(new AppMessageNotification(['title' => 'A', 'body' => '1']));
        $user->notify(new AppMessageNotification(['title' => 'B', 'body' => '2']));

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    #[Test]
    public function nao_pode_marcar_notificacao_de_outro_utilizador(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $owner->notify(new AppMessageNotification(['title' => 'Privada', 'body' => 'x']));
        $id = $owner->unreadNotifications->first()->id;

        $this->actingAs($other)
            ->postJson(route('notifications.read', ['id' => $id]))
            ->assertNotFound();
    }
}
