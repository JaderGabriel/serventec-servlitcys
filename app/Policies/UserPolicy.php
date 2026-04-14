<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Listagem e registo de atividade (apenas administradores).
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    /**
     * Cadastro de novos utilizadores no painel (apenas administradores).
     */
    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    /**
     * Ver e editar outro utilizador (administradores).
     */
    public function view(User $user, User $model): bool
    {
        return $user->is_admin === true;
    }

    /**
     * Atualizar dados, perfil, senha ou estado de outro utilizador.
     */
    public function update(User $user, User $model): bool
    {
        return $user->is_admin === true;
    }
}
