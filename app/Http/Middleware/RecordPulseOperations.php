<?php

namespace App\Http\Middleware;

use App\Support\Performance\AuthRouteRegistry;
use App\Support\Pulse\PulseOperationRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regista duração de pedidos HTTP por rota (métricas app_operation no Pulse).
 */
class RecordPulseOperations
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PulseOperationRecorder::enabled() || ! config('pulse_diagnostics.http_routes_enabled', true)) {
            return $next($request);
        }

        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $t0 = microtime(true);
        $response = $next($request);
        $ms = (microtime(true) - $t0) * 1000;

        $route = $request->route();
        $name = $route?->getName();
        if (! is_string($name) || $name === '') {
            $name = $request->path();
        }

        $cityId = $request->filled('city_id') ? (int) $request->query('city_id') : null;
        if (($cityId === null || $cityId <= 0) && $request->route('city')) {
            $city = $request->route('city');
            if (is_object($city) && method_exists($city, 'getKey')) {
                $cityId = (int) $city->getKey();
            }
        }

        $key = 'http:route:'.$name;
        if ($cityId !== null && $cityId > 0) {
            $key .= '|cid:'.$cityId;
        }

        $tab = $request->query('tab');
        if (is_string($tab) && $tab !== '') {
            $key .= '|tab:'.$tab;
        }

        PulseOperationRecorder::record($key, $ms);

        if ($response->getStatusCode() >= 500) {
            PulseOperationRecorder::recordFailure($key);
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->user() === null) {
            return true;
        }

        if (AuthRouteRegistry::matches($request)) {
            return true;
        }

        $path = trim($request->path(), '/');
        $pulsePath = trim((string) config('pulse.path', 'pulse'), '/');
        if ($pulsePath !== '' && (str_starts_with($path, $pulsePath) || $path === $pulsePath)) {
            return true;
        }

        return $request->is('livewire/*', 'up');
    }
}
