<?php

namespace App\Support\Performance;

use Illuminate\Http\Request;

/**
 * Rotas de autenticação onde o bootstrap pesado (SMTP, etc.) pode ser omitido com segurança.
 */
final class AuthRouteRegistry
{
    /** @var list<string> */
    private const ROUTE_NAMES = [
        'login',
        'logout',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'password.confirm',
        'password.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public static function matches(Request $request): bool
    {
        if ($request->routeIs(self::ROUTE_NAMES)) {
            return true;
        }

        $path = trim($request->path(), '/');

        return in_array($path, ['login', 'logout', 'forgot-password', 'reset-password'], true)
            || str_starts_with($path, 'reset-password/')
            || str_starts_with($path, 'verify-email');
    }
}
