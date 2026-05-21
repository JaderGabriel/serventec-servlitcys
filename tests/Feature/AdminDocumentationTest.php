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
            ->assertSee(__('Ler no GitHub'), false);
    }

    public function test_disallowed_document_path_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.documentation.show', ['doc' => 'docs/../../.env']))
            ->assertNotFound();
    }

    public function test_non_admin_cannot_access_documentation(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('admin.documentation.index'))
            ->assertForbidden();
    }
}
