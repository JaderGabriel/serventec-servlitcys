<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorização para hubs admin de dados públicos (importações, sync, Horizonte).
 */
class PublicDataAdminPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isAdmin();
    }

    public function sync(User $user): bool
    {
        return $user->isAdmin();
    }
}
