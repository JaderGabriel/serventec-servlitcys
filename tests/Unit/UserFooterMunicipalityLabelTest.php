<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFooterMunicipalityLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_no_footer_municipality_label(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertNull($admin->footerMunicipalityLabel());
    }

    public function test_municipal_user_sees_linked_cities_in_footer_label(): void
    {
        $city = City::factory()->create(['name' => 'Ceres', 'uf' => 'GO', 'is_active' => true]);
        $user = User::factory()->municipal()->create();
        $user->cities()->attach($city->id);

        $label = $user->footerMunicipalityLabel();

        $this->assertNotNull($label);
        $this->assertStringContainsString('Ceres', $label);
        $this->assertStringContainsString('GO', $label);
    }

    public function test_municipal_without_cities_shows_placeholder(): void
    {
        $user = User::factory()->municipal()->create();

        $this->assertSame(__('Nenhum município vinculado'), $user->footerMunicipalityLabel());
    }
}
