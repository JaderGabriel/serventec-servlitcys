<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.photo.update'), [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    public function test_user_can_remove_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = UploadedFile::fake()->image('avatar.png')->store('avatars', 'public');
        $user->forceFill(['profile_photo_path' => $path])->save();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.photo.update'), [
                'remove_photo' => '1',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNull($user->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
