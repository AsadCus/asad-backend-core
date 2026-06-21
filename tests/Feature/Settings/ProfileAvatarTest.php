<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->post('/api/settings/profile/avatar', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonStructure(['status', 'avatar_url']);

        $user->refresh();
        $this->assertNotNull($user->photo_profile);
        Storage::disk('public')->assertExists($user->photo_profile);
        $this->assertNotNull($user->avatar_url);
    }

    public function test_uploading_a_new_avatar_replaces_the_previous_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->post('/api/settings/profile/avatar', [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $oldPath = $user->refresh()->photo_profile;

        $this->post('/api/settings/profile/avatar', [
            'photo' => UploadedFile::fake()->image('second.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $newPath = $user->refresh()->photo_profile;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_user_can_remove_their_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->post('/api/settings/profile/avatar', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $user->refresh()->photo_profile;

        $this->deleteJson('/api/settings/profile/avatar')
            ->assertOk()
            ->assertJson(['avatar_url' => null]);

        $this->assertNull($user->refresh()->photo_profile);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->post('/api/settings/profile/avatar', [
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->assertNull($user->refresh()->photo_profile);
    }
}
