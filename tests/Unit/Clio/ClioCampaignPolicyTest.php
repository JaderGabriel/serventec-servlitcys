<?php

namespace Tests\Unit\Clio;

use App\Enums\UserRole;
use App\Models\Clio\ClioCampaign;
use App\Models\User;
use App\Policies\Clio\ClioCampaignPolicy;
use App\Policies\PlatformFeaturePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Policies Clio sem BD — usuários em memória.
 */
final class ClioCampaignPolicyTest extends TestCase
{
    private ClioCampaignPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        config(['clio.enabled' => true]);
        $this->policy = new ClioCampaignPolicy;
    }

    private function user(UserRole $role, bool $active = true): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Teste',
            'email' => 't@example.com',
            'role' => $role,
            'is_active' => $active,
        ]);

        return $user;
    }

    private function campaign(): ClioCampaign
    {
        return new ClioCampaign(['uuid' => '00000000-0000-4000-8000-000000000001']);
    }

    #[Test]
    public function admin_e_usuario_podem_ver_e_exportar(): void
    {
        $campaign = $this->campaign();

        foreach ([UserRole::Admin, UserRole::User] as $role) {
            $user = $this->user($role);
            $this->assertTrue($this->policy->viewAny($user));
            $this->assertTrue($this->policy->view($user, $campaign));
            $this->assertTrue($this->policy->export($user, $campaign));
        }
    }

    #[Test]
    public function municipal_nao_ve_clio(): void
    {
        $user = $this->user(UserRole::Municipal);
        $campaign = $this->campaign();

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $campaign));
        $this->assertFalse($this->policy->export($user, $campaign));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->upload($user, $campaign));
    }

    #[Test]
    public function so_admin_muta(): void
    {
        $admin = $this->user(UserRole::Admin);
        $user = $this->user(UserRole::User);
        $campaign = $this->campaign();

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->upload($admin, $campaign));
        $this->assertTrue($this->policy->analyze($admin, $campaign));
        $this->assertTrue($this->policy->createCatalogCity($admin));
        $this->assertTrue($this->policy->linkConsultancy($admin, $campaign));

        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->upload($user, $campaign));
        $this->assertFalse($this->policy->analyze($user, $campaign));
        $this->assertFalse($this->policy->createCatalogCity($user));
        $this->assertFalse($this->policy->linkConsultancy($user, $campaign));
    }

    #[Test]
    public function flag_desligada_bloqueia_todos(): void
    {
        config(['clio.enabled' => false]);
        $admin = $this->user(UserRole::Admin);

        $this->assertFalse(app(PlatformFeaturePolicy::class)->viewClio($admin));
        $this->assertFalse($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->create($admin));
    }

    #[Test]
    public function usuario_inactivo_nao_acede(): void
    {
        $admin = $this->user(UserRole::Admin, active: false);

        $this->assertFalse($this->policy->viewAny($admin));
    }
}
