<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManageTarget($user, $model);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageTarget($user, $model);
    }

    private function canManageTarget(User $actor, User $target): bool
    {
        if (! $actor->canManageUsers()) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        if ($actor->isUtilizador()) {
            return $target->role() === UserRole::User;
        }

        if ($actor->isMunicipal()) {
            if ($target->role() !== UserRole::Municipal) {
                return false;
            }

            $actorCities = $actor->cityIds();
            if ($actorCities === []) {
                return $target->id === $actor->id;
            }

            $targetCities = $target->cityIds();

            return $targetCities === [] || count(array_intersect($actorCities, $targetCities)) > 0;
        }

        return false;
    }
}
