<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
