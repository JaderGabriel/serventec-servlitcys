<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_documentation_index_redirects_to_default_reader(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.index'))
            ->assertRedirect(route('admin.documentation.show', ['doc' => 'docs/README.md']));
    }

    public function test_admin_can_render_markdown_document(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/README.md']))
            ->assertOk()
            ->assertSee(__('Índice da documentação'), false)
            ->assertSee(__('Ler no GitHub'), false)
            ->assertSee('v'.config('documentation.product.version'), false);
    }

    public function test_admin_can_render_hub_documentacao_com_mermaid(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/HUB_DOCUMENTACAO.md']))
            ->assertOk()
            ->assertSee(__('Hub de documentação'), false)
            ->assertSee('class="mermaid"', false)
            ->assertSee('mermaid@11/dist/mermaid.esm.min.mjs', false);
    }

    public function test_admin_can_render_version_history_document(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/HISTORICO_VERSOES.md']))
            ->assertOk()
            ->assertSee('2c8cf44', false)
            ->assertSee('#135', false);
    }

    public function test_disallowed_document_path_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/../../.env']))
            ->assertNotFound();
    }

    public function test_admin_can_open_deliveries_doc_not_only_in_old_whitelist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md']))
            ->assertOk()
            ->assertSee(__('Entregas escalonadas'), false);
    }

    public function test_admin_can_open_release_note_linked_from_history(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/RELEASE_20260525_APOLLO.md']))
            ->assertOk();
    }

    public function test_readme_internal_links_point_to_documentation_reader(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/README.md']));

        $response->assertOk();
        $response->assertSee(
            route('admin.documentation.show', ['doc' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md']),
            false
        );
        $response->assertSee(
            route('admin.documentation.show', ['doc' => 'docs/HISTORICO_VERSOES.md']),
            false
        );
    }

    public function test_non_admin_cannot_access_admin_documentation_route(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('admin.documentation.index'))
            ->assertForbidden();
    }

    public function test_utilizador_can_access_public_documentation_route(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('documentation.index'))
            ->assertRedirect(route('documentation.show', ['doc' => 'docs/README.md']));
    }
}
