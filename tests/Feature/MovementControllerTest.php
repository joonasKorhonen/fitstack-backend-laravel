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
}
