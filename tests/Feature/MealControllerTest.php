<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_only_authenticated_users_meals(): void
    {
        // Meals belonging to another user must never appear in the response.
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $user->meals()->create(['title' => 'Oatmeal', 'calories' => 350]);
        $other->meals()->create(['title' => 'Pizza', 'calories' => 900]);

        $response = $this->actingAs($user, 'api')->getJson('/api/meals');

        $response->assertOk();
        $titles = collect($response->json())->pluck('title');
        $this->assertContains('Oatmeal', $titles);
        $this->assertNotContains('Pizza', $titles);
    }

    public function test_index_returns_meals_newest_first(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $user->meals()->create(['title' => 'Breakfast', 'calories' => 400, 'date' => '2026-07-10 08:00:00']);
        $user->meals()->create(['title' => 'Dinner', 'calories' => 700, 'date' => '2026-07-12 18:00:00']);
        $user->meals()->create(['title' => 'Lunch', 'calories' => 600, 'date' => '2026-07-11 12:00:00']);

        $response = $this->actingAs($user, 'api')->getJson('/api/meals');

        $response->assertOk();
        $titles = collect($response->json())->pluck('title')->values()->all();
        $this->assertSame(['Dinner', 'Lunch', 'Breakfast'], $titles);
    }

    public function test_index_returns_empty_array_when_no_meals(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/meals')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/meals')->assertUnauthorized();
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_meal_belonging_to_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $meal = $user->meals()->create(['title' => 'Oatmeal', 'calories' => 350]);

        $this->actingAs($user, 'api')->getJson("/api/meals/{$meal->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $meal->id, 'title' => 'Oatmeal', 'calories' => 350]);
    }

    public function test_show_returns_404_for_another_users_meal(): void
    {
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $meal = $other->meals()->create(['title' => 'Pizza', 'calories' => 900]);

        $this->actingAs($user, 'api')->getJson("/api/meals/{$meal->id}")
            ->assertNotFound();
    }

    public function test_show_returns_404_for_nonexistent_meal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/meals/999999')
            ->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/meals/1')->assertUnauthorized();
    }
}
