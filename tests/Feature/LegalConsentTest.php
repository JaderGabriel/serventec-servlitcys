<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LegalConsentLog;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegalConsentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function utilizador_sem_aceite_e_redirecionado_para_consentimento(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('legal.consent'));
    }

    #[Test]
    public function aceite_grava_versao_e_permite_acesso(): void
    {
        config([
            'legal.privacy_version' => '2026-05-25',
            'legal.cookies_version' => '2026-05-25',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::User,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('legal.consent.store'), [
                'accept_privacy' => '1',
                'accept_cookies' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('2026-05-25', $user->privacy_policy_version_accepted);
        $this->assertSame('2026-05-25', $user->cookies_consent_version);
        $this->assertSame(1, LegalConsentLog::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    #[Test]
    public function admin_ve_relatorio_consentimentos(): void
    {
        $admin = User::factory()->admin()->create([
            'email_verified_at' => now(),
            'privacy_policy_version_accepted' => '2026-05-25',
            'cookies_consent_version' => '2026-05-25',
            'privacy_policy_accepted_at' => now(),
            'cookies_consent_accepted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.legal-consents.index'))
            ->assertOk()
            ->assertSee(__('Consentimentos legais (LGPD)'), false);
    }

    #[Test]
    public function admin_publica_nova_pp_e_forca_reconsentimento(): void
    {
        $admin = User::factory()->admin()->create([
            'email_verified_at' => now(),
            'privacy_policy_version_accepted' => '2026-05-25',
            'cookies_consent_version' => '2026-05-25',
            'privacy_policy_accepted_at' => now(),
            'cookies_consent_accepted_at' => now(),
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'privacy_policy_version_accepted' => '2026-05-25',
            'cookies_consent_version' => '2026-05-25',
            'privacy_policy_accepted_at' => now(),
            'cookies_consent_accepted_at' => now(),
        ]);

        $body = "## Teste\n\nConteúdo mínimo da política para teste automatizado com mais de vinte caracteres.";

        $this->actingAs($admin)
            ->post(route('admin.legal-documents.publish', ['type' => 'privacy']), [
                'title' => 'PP Teste',
                'body_markdown' => $body,
                'version' => '2026-05-26',
                'force_reconsent' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('legal_document_versions', [
            'document_type' => LegalDocumentVersion::TYPE_PRIVACY,
            'version' => '2026-05-26',
            'is_current' => true,
        ]);

        $this->assertSame('2026-05-26', \App\Support\Legal\LegalConsentService::currentPrivacyVersion());

        $user->refresh();
        $admin->refresh();
        $this->assertNull($user->privacy_policy_version_accepted);
        $this->assertNull($admin->privacy_policy_version_accepted);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('legal.consent'));
    }

    #[Test]
    public function admin_revoga_aceite_de_utilizador(): void
    {
        config([
            'legal.privacy_version' => '2026-05-25',
            'legal.cookies_version' => '2026-05-25',
        ]);

        $admin = User::factory()->admin()->create([
            'email_verified_at' => now(),
            'privacy_policy_version_accepted' => '2026-05-25',
            'cookies_consent_version' => '2026-05-25',
            'privacy_policy_accepted_at' => now(),
            'cookies_consent_accepted_at' => now(),
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'privacy_policy_version_accepted' => '2026-05-25',
            'cookies_consent_version' => '2026-05-25',
            'privacy_policy_accepted_at' => now(),
            'cookies_consent_accepted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-consents.revoke-user', $user), [
                'revoke_privacy' => '1',
                'revoke_cookies' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->privacy_policy_version_accepted);
        $this->assertNull($user->cookies_consent_version);
        $this->assertSame(1, LegalConsentLog::query()->where('user_id', $user->id)->where('consent_type', LegalConsentLog::TYPE_REVOKED_BOTH)->count());
    }
}
