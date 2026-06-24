<?php

namespace App\Support\SyncQueue;

use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SyncQueueUserScope
{
    /**
     * @param  Builder<AdminSyncTask>  $query
     * @return Builder<AdminSyncTask>
     */
    public static function applyToTasks(Builder $query, User $user): Builder
    {
        return $query->visibleToUser($user);
    }

    /**
     * @param  Builder<AnalyticsReportExport>  $query
     * @return Builder<AnalyticsReportExport>
     */
    public static function applyToPdfExports(Builder $query, User $user): Builder
    {
        return $query->visibleToUser($user);
    }

    public static function routePrefix(?User $user = null): string
    {
        $user ??= auth()->user();

        return ($user !== null && $user->isAdmin())
            ? 'admin.sync-queue'
            : 'sync-queue';
    }

    public static function route(string $action, mixed $parameters = [], ?User $user = null): string
    {
        return route(self::routePrefix($user).'.'.$action, $parameters);
    }
}
