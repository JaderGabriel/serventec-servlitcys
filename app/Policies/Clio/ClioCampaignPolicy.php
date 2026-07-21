<?php

namespace App\Policies\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\User;
use App\Policies\PlatformFeaturePolicy;

/**
 * Clio: Admin e Usuário (não Municipal) podem ver painel/relatórios.
 * Inserts e comandos sensíveis (campanha, upload, analisar, cruzar, ficha leve, vínculo i-Educar) — só Admin.
 */
class ClioCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PlatformFeaturePolicy::class)->viewClio($user);
    }

    public function view(User $user, ClioCampaign $campaign): bool
    {
        return app(PlatformFeaturePolicy::class)->viewClio($user);
    }

    public function export(User $user, ClioCampaign $campaign): bool
    {
        return app(PlatformFeaturePolicy::class)->viewClio($user);
    }

    public function create(User $user): bool
    {
        return $this->adminMutate($user);
    }

    public function update(User $user, ClioCampaign $campaign): bool
    {
        return $this->adminMutate($user);
    }

    public function delete(User $user, ClioCampaign $campaign): bool
    {
        return $this->adminMutate($user);
    }

    public function upload(User $user, ClioCampaign $campaign): bool
    {
        return $this->adminMutate($user);
    }

    public function analyze(User $user, ClioCampaign $campaign): bool
    {
        return $this->adminMutate($user);
    }

    public function createCatalogCity(User $user): bool
    {
        return $this->adminMutate($user);
    }

    public function linkConsultancy(User $user, ClioCampaign $campaign): bool
    {
        return $this->adminMutate($user);
    }

    private function adminMutate(User $user): bool
    {
        return $user->isAdmin() && app(PlatformFeaturePolicy::class)->viewClio($user);
    }
}
