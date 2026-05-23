<?php

namespace Tests\Unit;

use App\Support\Performance\AuthRouteRegistry;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthRouteRegistryTest extends TestCase
{
    #[Test]
    public function reconhece_rota_login_por_path(): void
    {
        $request = Request::create('/login', 'GET');

        $this->assertTrue(AuthRouteRegistry::matches($request));
    }

    #[Test]
    public function nao_reconhece_dashboard(): void
    {
        $request = Request::create('/dashboard', 'GET');

        $this->assertFalse(AuthRouteRegistry::matches($request));
    }
}
