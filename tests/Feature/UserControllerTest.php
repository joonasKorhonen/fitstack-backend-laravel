<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── profile ───────────────────────────────────────────────────────────────

    public function test_profile_returns_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->getJson('/api/users/profile');

        $response->assertOk()->assertJson([
            'id'        => $user->id,
            'username'  => $user->username,
            'email'     => $user->email,
            'avatarUrl' => null,
        ]);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/users/profile')->assertUnauthorized();
    }

    // ── updateProfile ─────────────────────────────────────────────────────────

    public function test_update_profile_updates_username(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['username' => 'newname']);

        $response->assertOk()->assertJsonFragment(['username' => 'newname']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'newname']);
    }

    public function test_update_profile_updates_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['email' => 'new@example.com']);

        $response->assertOk()->assertJsonFragment(['email' => 'new@example.com']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
    }

    public function test_update_profile_allows_keeping_own_username_and_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->patchJson('/api/users/profile', [
            'username' => $user->username,
            'email'    => $user->email,
        ]);

        $response->assertOk();
    }

    public function test_update_profile_rejects_duplicate_username(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        User::factory()->create(['username' => 'takenname']);

        $response = $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['username' => 'takenname']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['username']);
    }

    public function test_update_profile_rejects_duplicate_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['email' => 'taken@example.com']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_update_profile_validates_username_length(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['username' => 'ab'])
            ->assertUnprocessable()->assertJsonValidationErrors(['username']);

        $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['username' => str_repeat('a', 51)])
            ->assertUnprocessable()->assertJsonValidationErrors(['username']);
    }

    public function test_update_profile_validates_email_format(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->patchJson('/api/users/profile', ['email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->patchJson('/api/users/profile', ['username' => 'newname'])->assertUnauthorized();
    }

    // ── deleteProfile ─────────────────────────────────────────────────────────

    public function test_delete_profile_removes_user_and_returns_success_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->deleteJson('/api/users/profile')
            ->assertOk()
            ->assertJson(['message' => 'Account deleted']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_profile_requires_authentication(): void
    {
        $this->deleteJson('/api/users/profile')->assertUnauthorized();
    }

    public function test_delete_profile_deletes_avatar_from_s3_when_present(): void
    {
        $disk = Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create(['avatar_path' => 'avatars/test.jpg']);
        $disk->put('avatars/test.jpg', 'fake-image-content');

        $this->actingAs($user, 'api')->deleteJson('/api/users/profile')
            ->assertOk();

        $disk->assertMissing('avatars/test.jpg');
    }

    // ── uploadAvatar ──────────────────────────────────────────────────────────

    public function test_upload_avatar_requires_authentication(): void
    {
        $this->postJson('/api/users/avatar')->assertUnauthorized();
    }

    public function test_upload_avatar_rejects_missing_file(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/users/avatar', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_avatar_rejects_non_image_file(): void
    {
        Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user, 'api')
            ->postJson('/api/users/avatar', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_avatar_rejects_file_exceeding_size_limit(): void
    {
        Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('big.jpg')->size(6000);

        $this->actingAs($user, 'api')
            ->postJson('/api/users/avatar', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_avatar_stores_file_on_s3_and_returns_avatar_url(): void
    {
        $disk = Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($user, 'api')
            ->postJson('/api/users/avatar', ['file' => $file])
            ->assertOk()
            ->assertJsonStructure(['avatarUrl']);

        $newPath = $user->fresh()->avatar_path;
        $this->assertNotNull($newPath);
        $disk->assertExists($newPath);
    }

    public function test_upload_avatar_replaces_old_avatar_on_s3(): void
    {
        $disk = Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        $disk->put('avatars/old.jpg', 'old-image-content');

        $file = UploadedFile::fake()->image('new-avatar.jpg');

        $this->actingAs($user, 'api')
            ->postJson('/api/users/avatar', ['file' => $file])
            ->assertOk();

        $disk->assertMissing('avatars/old.jpg');
        $newPath = $user->fresh()->avatar_path;
        $disk->assertExists($newPath);
    }

    // ── deleteAvatar ──────────────────────────────────────────────────────────

    public function test_delete_avatar_requires_authentication(): void
    {
        $this->deleteJson('/api/users/avatar')->assertUnauthorized();
    }

    public function test_delete_avatar_returns_404_when_no_avatar(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['avatar_path' => null]);

        $this->actingAs($user, 'api')
            ->deleteJson('/api/users/avatar')
            ->assertNotFound()
            ->assertJson(['message' => 'No avatar to delete']);
    }

    public function test_delete_avatar_removes_file_from_s3_and_clears_avatar_path(): void
    {
        $disk = Storage::fake('s3');

        /** @var User $user */
        $user = User::factory()->create(['avatar_path' => 'avatars/photo.jpg']);
        $disk->put('avatars/photo.jpg', 'image-content');

        $this->actingAs($user, 'api')
            ->deleteJson('/api/users/avatar')
            ->assertOk()
            ->assertJsonPath('avatarUrl', null);

        $disk->assertMissing('avatars/photo.jpg');
        $this->assertNull($user->fresh()->avatar_path);
    }
}
