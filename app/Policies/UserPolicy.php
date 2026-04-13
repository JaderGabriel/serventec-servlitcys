<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Cadastro de novos utilizadores no painel (apenas administradores).
     */
    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }
}
