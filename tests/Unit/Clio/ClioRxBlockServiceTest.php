<?php

namespace Tests\Unit\Clio;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Clio\Rx\ClioRxBlockService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioRxBlockServiceTest extends TestCase
{
    #[Test]
    public function municipal_recebe_null(): void
    {
        config(['clio.enabled' => true]);
        $user = new User;
        $user->forceFill([
            'role' => UserRole::Municipal,
            'is_active' => true,
        ]);

        $this->assertNull(app(ClioRxBlockService::class)->forUser($user));
    }

    #[Test]
    public function flag_desligada_recebe_null(): void
    {
        config(['clio.enabled' => false]);
        $user = new User;
        $user->forceFill([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->assertNull(app(ClioRxBlockService::class)->forUser($user));
    }
}
