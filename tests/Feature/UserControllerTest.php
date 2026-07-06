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
}
