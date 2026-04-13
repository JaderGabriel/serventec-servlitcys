<?php

namespace App\Policies;

use App\Models\City;
use App\Models\User;

class CityPolicy
{
    /**
     * Painel de análise educacional: só cidades ativas com credenciais de banco válidas.
     */
    public function viewAnalytics(User $user, City $city): bool
    {
        return $city->is_active && $city->hasDataSetup();
    }

    /**
     * Listagem e gestão de cadastro de cidades (CRUD) — apenas administradores.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, City $city): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, City $city): bool
    {
        return $user->is_admin === true;
    }
}
