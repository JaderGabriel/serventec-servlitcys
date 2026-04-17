<?php

namespace App\Http\Middleware;

use App\Models\City;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Associa pedidos HTTP ao contexto de instituição (cidade) para o Laravel Pulse.
 * Os cartões personalizados agregam por cidade; o total global inclui todo o tráfego autenticado na app.
 */
class RecordPulseInstitutionContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pulse.enabled', true)) {
            return $next($request);
        }

        $pulsePath = trim((string) config('pulse.path', 'pulse'), '/');
        $reqPath = trim($request->path(), '/');
        if ($pulsePath !== '' && (str_starts_with($reqPath, $pulsePath) || $reqPath === $pulsePath)) {
            return $next($request);
        }

        Pulse::record('trafego_app', 'total', null)->count();

        $cityId = $this->resolveCityId($request);
        if ($cityId !== null && $cityId > 0) {
            Pulse::record('instituicao_request', 'cid:'.$cityId, null)->count();
        }

        $this->recordSyncAdminEndpoints($request);

        return Pulse::ignore(fn () => $next($request));
    }

    private function recordSyncAdminEndpoints(Request $request): void
    {
        if ($request->is('admin/geo-sync', 'admin/geo-sync/*')) {
            Pulse::record('sync_admin_endpoint', 'geo-sync', null)->count();

            return;
        }

        if ($request->is('admin/pedagogical-sync', 'admin/pedagogical-sync/*')) {
            Pulse::record('sync_admin_endpoint', 'pedagogical-sync', null)->count();
        }
    }

    private function resolveCityId(Request $request): ?int
    {
        if ($request->filled('city_id')) {
            $id = (int) $request->query('city_id');

            return $id > 0 ? $id : null;
        }

        $city = $request->route('city');
        if ($city instanceof City) {
            return (int) $city->getKey();
        }

        return null;
    }
}
