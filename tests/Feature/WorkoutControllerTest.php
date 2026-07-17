<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_only_authenticated_users_workouts(): void
    {
        // Workouts belonging to another user must never appear in the response.
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $user->workouts()->create(['exercise' => 'Squat', 'reps' => 5, 'date' => now()]);
        $other->workouts()->create(['exercise' => 'Bench Press', 'reps' => 8, 'date' => now()]);

        $response = $this->actingAs($user, 'api')->getJson('/api/workouts');

        $response->assertOk();
        $exercises = collect($response->json())->pluck('exercise');
        $this->assertContains('Squat', $exercises);
        $this->assertNotContains('Bench Press', $exercises);
    }

    public function test_index_returns_workouts_newest_first(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $user->workouts()->create(['exercise' => 'Squat', 'reps' => 5, 'date' => '2026-07-10 08:00:00']);
        $user->workouts()->create(['exercise' => 'Deadlift', 'reps' => 3, 'date' => '2026-07-12 18:00:00']);
        $user->workouts()->create(['exercise' => 'Bench Press', 'reps' => 8, 'date' => '2026-07-11 12:00:00']);

        $response = $this->actingAs($user, 'api')->getJson('/api/workouts');

        $response->assertOk();
        $exercises = collect($response->json())->pluck('exercise')->values()->all();
        $this->assertSame(['Deadlift', 'Bench Press', 'Squat'], $exercises);
    }

    public function test_index_includes_sets_with_their_movement(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $movement = $user->movements()->create(['name' => 'Back Squat']);
        $workout  = $user->workouts()->create(['exercise' => 'Squat', 'reps' => 5, 'date' => now()]);
        $workout->sets()->create(['movement_id' => $movement->id, 'reps' => 5, 'weight' => 100]);
        $workout->sets()->create(['exercise' => 'Front Squat', 'reps' => 8]);

        $response = $this->actingAs($user, 'api')->getJson('/api/workouts');

        $response->assertOk()->assertJsonCount(1);
        $sets = $response->json('0.sets');
        $this->assertCount(2, $sets);
        $this->assertSame('Back Squat', $sets[0]['movement']['name']);
        $this->assertNull($sets[1]['movement']);
        $this->assertSame('Front Squat', $sets[1]['exercise']);
    }

    public function test_index_returns_empty_array_when_no_workouts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/workouts')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/workouts')->assertUnauthorized();
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_workout_belonging_to_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $workout = $user->workouts()->create(['exercise' => 'Squat', 'reps' => 5, 'date' => now()]);

        $this->actingAs($user, 'api')->getJson("/api/workouts/{$workout->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $workout->id, 'exercise' => 'Squat', 'reps' => 5]);
    }

    public function test_show_includes_sets_with_their_movement(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $movement = $user->movements()->create(['name' => 'Back Squat']);
        $workout  = $user->workouts()->create(['exercise' => 'Squat', 'reps' => 5, 'date' => now()]);
        $workout->sets()->create(['movement_id' => $movement->id, 'reps' => 5, 'weight' => 100]);

        $response = $this->actingAs($user, 'api')->getJson("/api/workouts/{$workout->id}");

        $response->assertOk();
        $sets = $response->json('sets');
        $this->assertCount(1, $sets);
        $this->assertSame('Back Squat', $sets[0]['movement']['name']);
    }

    public function test_show_returns_404_for_another_users_workout(): void
    {
        /** @var User $user */
        $user  = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        $workout = $other->workouts()->create(['exercise' => 'Bench Press', 'reps' => 8, 'date' => now()]);

        $this->actingAs($user, 'api')->getJson("/api/workouts/{$workout->id}")
            ->assertNotFound();
    }

    public function test_show_returns_404_for_nonexistent_workout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/workouts/999999')
            ->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/workouts/1')->assertUnauthorized();
    }
}
