<?php

namespace App\Policies\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\User;

class ClioCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewClio();
    }

    public function view(User $user, ClioCampaign $campaign): bool
    {
        return $user->canViewClio();
    }

    public function create(User $user): bool
    {
        return $user->canViewClio();
    }

    public function update(User $user, ClioCampaign $campaign): bool
    {
        return $user->canViewClio();
    }

    public function upload(User $user, ClioCampaign $campaign): bool
    {
        return $user->canViewClio();
    }

    public function analyze(User $user, ClioCampaign $campaign): bool
    {
        return $user->canViewClio();
    }

    public function createCatalogCity(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
