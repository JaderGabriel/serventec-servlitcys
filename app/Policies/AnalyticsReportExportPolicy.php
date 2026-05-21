<?php

namespace App\Policies;

use App\Models\AnalyticsReportExport;
use App\Models\User;

class AnalyticsReportExportPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->canExportAnalyticsPdf();
    }

    public function download(User $user, AnalyticsReportExport $export): bool
    {
        if (! $user->canExportAnalyticsPdf()) {
            return false;
        }

        if ($user->id !== $export->user_id && ! $user->isAdmin()) {
            return false;
        }

        $city = $export->city;
        if ($city === null) {
            return false;
        }

        return $user->hasCityAccess($city);
    }
}
