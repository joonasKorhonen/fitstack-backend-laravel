<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── register ──────────────────────────────────────────────────────────────

    public function test_register_creates_user_and_returns_access_token_with_refresh_cookie(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'newuser',
            'email'    => 'new@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['accessToken', 'user' => ['id', 'username', 'email', 'avatarUrl', 'createdAt']])
            ->assertJsonPath('user.username', 'newuser')
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonPath('user.avatarUrl', null)
            ->assertCookie('refresh_token');

        $this->assertDatabaseHas('users', ['username' => 'newuser', 'email' => 'new@example.com']);

        // Password is stored hashed, never in plaintext.
        $user = User::where('username', 'newuser')->first();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));

        // A refresh token was issued for the new user.
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id]);
    }

    public function test_register_rejects_duplicate_username_and_email(): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'username' => 'taken',
            'email'    => 'taken@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_register_validates_input(): void
    {
        // Missing fields → required errors.
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email', 'password']);

        // Present but invalid → format/length errors (username min:3, email format, password min:8).
        $this->postJson('/api/auth/register', [
            'username' => 'ab',
            'email'    => 'not-an-email',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email', 'password']);
    }
}
