<?php

namespace App\Policies;

use App\Models\AnalyticsReportExport;
use App\Models\User;

class AnalyticsReportExportPolicy
{
    public function download(User $user, AnalyticsReportExport $export): bool
    {
        if ($user->id !== $export->user_id && ! $user->isAdmin()) {
            return false;
        }

        $city = $export->city;
        if ($city === null) {
            return false;
        }

        return app(CityPolicy::class)->viewAnalytics($user, $city);
    }
}
