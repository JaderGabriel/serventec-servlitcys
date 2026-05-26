<?php

namespace App\Policies;

use App\Models\AdminSyncTask;
use App\Models\User;

class AdminSyncTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewSyncQueue();
    }

    public function view(User $user, AdminSyncTask $task): bool
    {
        if (! $user->canViewSyncQueue()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $task->queued_by_id === (int) $user->id;
    }

    public function download(User $user, AdminSyncTask $task): bool
    {
        return $this->view($user, $task);
    }

    public function resume(User $user, AdminSyncTask $task): bool
    {
        return $user->isAdmin() && $task->isResumable();
    }
}
