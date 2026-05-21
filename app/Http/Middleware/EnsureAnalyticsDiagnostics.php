<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rota de diagnóstico do painel analítico — apenas dev/debug explícito.
 */
class EnsureAnalyticsDiagnostics
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('analytics.diagnostics_route_enabled', false)
            || app()->environment(['local', 'development']);

        if (! $allowed) {
            abort(403, __(
                'Diagnóstico analítico desactivado. No .env defina ANALYTICS_DIAGNOSTICS_FORCE=true (ou ANALYTICS_DIAGNOSTICS_ROUTE=true), execute php artisan config:clear e tente de novo. Em produção remova a variável após o diagnóstico.'
            ));
        }

        $expectedToken = (string) config('analytics.diagnostics_token', '');
        if ($expectedToken !== '') {
            $given = (string) $request->query('token', $request->header('X-Analytics-Diagnostics-Token', ''));
            if (! hash_equals($expectedToken, $given)) {
                abort(403, __('Token de diagnóstico inválido.'));
            }
        }

        return $next($request);
    }
}
