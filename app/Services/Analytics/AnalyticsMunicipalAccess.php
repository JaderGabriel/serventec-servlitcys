<?php

namespace App\Services\Analytics;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class AnalyticsMunicipalAccess
{
    /**
     * Utilizador municipal sem city_id: redireciona para o município de casa.
     */
    public function municipalHomeRedirect(AnalyticsFilterRequest $request): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isMunicipal() || $request->filled('city_id')) {
            return null;
        }

        $homeParams = $user->homeRouteParameters();
        if ($homeParams === []) {
            return null;
        }

        return redirect()->route('dashboard.analytics', array_merge(
            $request->query(),
            $homeParams,
        ));
    }
}
