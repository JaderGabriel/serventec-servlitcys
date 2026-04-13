<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona para o formulário de primeiro acesso até data de nascimento e CPF estarem preenchidos.
 */
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->needsProfileCompletion()) {
            return $next($request);
        }

        if ($request->routeIs('profile.first-access', 'profile.first-access.update', 'logout', 'verification.*')) {
            return $next($request);
        }

        return redirect()->route('profile.first-access');
    }
}
