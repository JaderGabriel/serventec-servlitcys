<?php

namespace App\Policies;

use App\Models\User;

/**
 * Capacidades transversais da plataforma (Horizonte, PDF, importações, fila).
 * Fonte única de verdade — {@see User::canViewHorizonte()} e afins delegam aqui.
 */
final class PlatformFeaturePolicy
{
    public function importOrConfigure(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function viewDocumentation(User $user): bool
    {
        return $user->is_active;
    }

    public function viewSyncQueue(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isUsuário() || $user->isMunicipal());
    }

    public function exportInclusionNee(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isUsuário() || $user->isMunicipal());
    }

    public function viewAdminDashboard(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function viewHorizonte(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isUsuário());
    }

    public function exportAnalyticsPdf(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isUsuário());
    }
}
