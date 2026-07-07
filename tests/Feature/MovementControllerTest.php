<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_only_authenticated_users_movements(): void
    {
        // Movements belonging to another user must never appear in the response.
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $user->movements()->create(['name' => 'Squat']);
        $other->movements()->create(['name' => 'Deadlift']);

        $response = $this->actingAs($user, 'api')->getJson('/api/movements');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertContains('Squat', $names);
        $this->assertNotContains('Deadlift', $names);
    }

    public function test_index_returns_movements_in_alphabetical_order(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->movements()->create(['name' => 'Squat']);
        $user->movements()->create(['name' => 'Bench Press']);
        $user->movements()->create(['name' => 'Deadlift']);

        $response = $this->actingAs($user, 'api')->getJson('/api/movements');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->values()->all();
        $this->assertSame(['Bench Press', 'Deadlift', 'Squat'], $names);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/movements')->assertUnauthorized();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_movement_and_returns_201(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/movements', ['name' => 'Squat']);

        $response->assertCreated()->assertJsonFragment(['name' => 'Squat', 'user_id' => $user->id]);
        $this->assertDatabaseHas('movements', ['name' => 'Squat', 'user_id' => $user->id]);
    }

    public function test_store_rejects_duplicate_name_for_same_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->movements()->create(['name' => 'Squat']);

        $response = $this->actingAs($user, 'api')->postJson('/api/movements', ['name' => 'Squat']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_store_allows_same_name_for_different_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        User::factory()->create()->movements()->create(['name' => 'Squat']);

        $response = $this->actingAs($user, 'api')->postJson('/api/movements', ['name' => 'Squat']);

        $response->assertCreated();
    }

    public function test_store_validates_required_and_max_length(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/movements', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->actingAs($user, 'api')->postJson('/api/movements', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/movements', ['name' => 'Squat'])->assertUnauthorized();
    }
}
