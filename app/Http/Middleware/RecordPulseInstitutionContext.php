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

        return Pulse::ignore(function () use ($request, $next) {
            Pulse::record('trafego_app', 'total', null)->count();

            $cityId = $this->resolveCityId($request);
            if ($cityId !== null && $cityId > 0) {
                Pulse::record('instituicao_request', 'cid:'.$cityId, null)->count();
            }

            return $next($request);
        });
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
