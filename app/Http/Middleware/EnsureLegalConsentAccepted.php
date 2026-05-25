<?php

namespace App\Http\Middleware;

use App\Support\Legal\LegalConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona utilizadores autenticados que ainda não aceitaram a versão vigente da PP/cookies.
 */
class EnsureLegalConsentAccepted
{
    /**
     * @var list<string>
     */
    private const EXEMPT_ROUTE_PREFIXES = [
        'legal.consent',
        'legal.consent.store',
        'legal.privacy',
        'logout',
        'verification.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! LegalConsentService::userNeedsConsent($user)) {
            return $next($request);
        }

        foreach (self::EXEMPT_ROUTE_PREFIXES as $prefix) {
            if ($request->routeIs($prefix)) {
                return $next($request);
            }
        }

        if ($request->routeIs('notifications.feed', 'notifications.read', 'notifications.read-all')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('É necessário aceitar a política de privacidade e cookies vigentes.'),
                'redirect' => route('legal.consent'),
            ], 403);
        }

        return redirect()->guest(route('legal.consent', [
            'intended' => $request->fullUrl(),
        ]));
    }
}
