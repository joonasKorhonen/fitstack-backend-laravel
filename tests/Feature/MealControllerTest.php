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

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_meal_and_returns_201(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/meals', [
            'title'    => 'Chicken Salad',
            'calories' => 450,
            'protein'  => 40,
            'carbs'    => 20,
            'fat'      => 15,
            'notes'    => 'Post-workout meal',
        ]);

        $response->assertCreated()->assertJsonFragment([
            'title'    => 'Chicken Salad',
            'calories' => 450,
            'protein'  => 40,
            'carbs'    => 20,
            'fat'      => 15,
            'notes'    => 'Post-workout meal',
        ]);

        $this->assertDatabaseHas('meals', [
            'user_id'  => $user->id,
            'title'    => 'Chicken Salad',
            'calories' => 450,
        ]);
    }

    public function test_store_creates_meal_with_only_required_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/meals', [
            'title'    => 'Snack',
            'calories' => 200,
        ])->assertCreated();

        $this->assertDatabaseHas('meals', [
            'user_id'  => $user->id,
            'title'    => 'Snack',
            'calories' => 200,
        ]);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/meals', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'calories']);
    }

    public function test_store_rejects_negative_macro_values(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/meals', [
            'title'    => 'Bad Meal',
            'calories' => -100,
            'protein'  => -5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['calories', 'protein']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/meals', ['title' => 'Snack', 'calories' => 200])
            ->assertUnauthorized();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_given_fields_and_keeps_others(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $meal = $user->meals()->create([
            'title'    => 'Oatmeal',
            'calories' => 350,
            'protein'  => 12,
        ]);

        $response = $this->actingAs($user, 'api')->patchJson("/api/meals/{$meal->id}", [
            'calories' => 400,
        ]);

        $response->assertOk()->assertJsonFragment([
            'title'    => 'Oatmeal',
            'calories' => 400,
            'protein'  => 12,
        ]);

        $this->assertDatabaseHas('meals', [
            'id'       => $meal->id,
            'title'    => 'Oatmeal',
            'calories' => 400,
        ]);
    }

    public function test_update_returns_404_for_another_users_meal(): void
    {
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $meal = $other->meals()->create(['title' => 'Pizza', 'calories' => 900]);

        $this->actingAs($user, 'api')->patchJson("/api/meals/{$meal->id}", ['calories' => 500])
            ->assertNotFound();

        $this->assertDatabaseHas('meals', ['id' => $meal->id, 'calories' => 900]);
    }

    public function test_update_returns_404_for_nonexistent_meal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->patchJson('/api/meals/999999', ['calories' => 500])
            ->assertNotFound();
    }

    public function test_update_rejects_invalid_values(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $meal = $user->meals()->create(['title' => 'Oatmeal', 'calories' => 350]);

        $this->actingAs($user, 'api')->patchJson("/api/meals/{$meal->id}", [
            'calories' => -1,
            'title'    => str_repeat('a', 256),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['calories', 'title']);
    }

    public function test_update_requires_authentication(): void
    {
        $this->patchJson('/api/meals/1', ['calories' => 500])->assertUnauthorized();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_meal_and_returns_success_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $meal = $user->meals()->create(['title' => 'Oatmeal', 'calories' => 350]);

        $this->actingAs($user, 'api')->deleteJson("/api/meals/{$meal->id}")
            ->assertOk()
            ->assertJson(['message' => 'Meal deleted']);

        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_destroy_returns_404_for_another_users_meal(): void
    {
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $meal = $other->meals()->create(['title' => 'Pizza', 'calories' => 900]);

        $this->actingAs($user, 'api')->deleteJson("/api/meals/{$meal->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('meals', ['id' => $meal->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_meal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->deleteJson('/api/meals/999999')
            ->assertNotFound();
    }

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/meals/1')->assertUnauthorized();
    }
}
