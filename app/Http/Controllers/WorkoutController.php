<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    /**
     * List all workouts for the authenticated user, newest first, with their sets.
     *
     * GET /api/workouts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  Array of workout objects with nested sets.
     */
    public function index(Request $request): JsonResponse
    {
        $workouts = $request->user()
            ->workouts()
            ->with('sets.movement')
            ->orderByDesc('date')
            ->get();

        return response()->json($workouts);
    }

    /**
     * Return a single workout belonging to the authenticated user.
     *
     * GET /api/workouts/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $workout = $request->user()
            ->workouts()
            ->with('sets.movement')
            ->findOrFail($id);

        return response()->json($workout);
    }

    /**
     * Create a new workout for the authenticated user.
     * top-level exercise/reps are derived from the first set when not supplied,
     * matching the NestJS backend's behaviour.
     *
     * POST /api/workouts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  The created workout with sets (201).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'              => 'sometimes|date',
            'exercise'          => 'sometimes|nullable|string|max:255',
            'reps'              => 'sometimes|nullable|integer|min:0',
            'weight'            => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
            'sets'              => 'sometimes|array|min:1',
            'sets.*.exercise'   => 'nullable|string|max:255',
            'sets.*.movementId' => 'nullable|integer|exists:movements,id',
            'sets.*.reps'       => 'required_with:sets|integer|min:0',
            'sets.*.weight'     => 'nullable|numeric|min:0',
            'sets.*.intensity'  => 'nullable|integer|min:1|max:10',
            'sets.*.notes'      => 'nullable|string',
        ]);

        $sets      = $data['sets'] ?? [];
        $firstSet  = $sets[0] ?? [];

        // Derive top-level fields from the first set when not explicitly provided
        $exercise = $data['exercise'] ?? ($firstSet['exercise'] ?? 'Movement');
        $reps     = $data['reps'] ?? ($firstSet['reps'] ?? 0);
        $weight   = $data['weight'] ?? ($firstSet['weight'] ?? null);

        $workout = $request->user()->workouts()->create([
            'date'     => $data['date'] ?? now(),
            'exercise' => $exercise,
            'reps'     => $reps,
            'weight'   => $weight,
            'notes'    => $data['notes'] ?? null,
        ]);

        foreach ($sets as $setData) {
            $workout->sets()->create([
                'exercise'    => $setData['exercise'] ?? null,
                'movement_id' => $setData['movementId'] ?? null,
                'reps'        => $setData['reps'],
                'weight'      => $setData['weight'] ?? null,
                'intensity'   => $setData['intensity'] ?? null,
                'notes'       => $setData['notes'] ?? null,
            ]);
        }

        return response()->json($workout->load('sets.movement'), 201);
    }

    /**
     * Update the metadata fields of a workout owned by the authenticated user.
     *
     * PATCH /api/workouts/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse  The updated workout with sets.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workout = $request->user()->workouts()->findOrFail($id);

        $data = $request->validate([
            'date'     => 'sometimes|date',
            'exercise' => 'sometimes|string|max:255',
            'reps'     => 'sometimes|integer|min:0',
            'weight'   => 'nullable|numeric|min:0',
            'notes'    => 'nullable|string',
        ]);

        $workout->update($data);

        return response()->json($workout->load('sets.movement'));
    }

    /**
     * Delete a workout (and its sets via cascade) owned by the authenticated user.
     *
     * DELETE /api/workouts/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $workout = $request->user()->workouts()->findOrFail($id);
        $workout->delete();

        return response()->json(['message' => 'Workout deleted']);
    }

    /**
     * Add one or more sets to an existing workout.
     *
     * POST /api/workouts/{id}/sets
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Workout ID.
     * @return \Illuminate\Http\JsonResponse  The workout with all sets (201).
     */
    public function addSets(Request $request, int $id): JsonResponse
    {
        $workout = $request->user()->workouts()->findOrFail($id);

        $data = $request->validate([
            'sets'              => 'required|array|min:1',
            'sets.*.exercise'   => 'nullable|string|max:255',
            'sets.*.movementId' => 'nullable|integer|exists:movements,id',
            'sets.*.reps'       => 'required|integer|min:0',
            'sets.*.weight'     => 'nullable|numeric|min:0',
            'sets.*.intensity'  => 'nullable|integer|min:1|max:10',
            'sets.*.notes'      => 'nullable|string',
        ]);

        foreach ($data['sets'] as $setData) {
            $workout->sets()->create([
                'exercise'    => $setData['exercise'] ?? null,
                'movement_id' => $setData['movementId'] ?? null,
                'reps'        => $setData['reps'],
                'weight'      => $setData['weight'] ?? null,
                'intensity'   => $setData['intensity'] ?? null,
                'notes'       => $setData['notes'] ?? null,
            ]);
        }

        return response()->json($workout->load('sets.movement'), 201);
    }

    /**
     * Update a specific set within a workout owned by the authenticated user.
     * Uses array_key_exists checks so explicit null values are preserved.
     *
     * PATCH /api/workouts/{workoutId}/sets/{setId}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $workoutId
     * @param  int  $setId
     * @return \Illuminate\Http\JsonResponse  The updated set.
     */
    public function updateSet(Request $request, int $workoutId, int $setId): JsonResponse
    {
        $workout = $request->user()->workouts()->findOrFail($workoutId);
        $set     = $workout->sets()->findOrFail($setId);

        $data = $request->validate([
            'exercise'   => 'sometimes|nullable|string|max:255',
            'movementId' => 'sometimes|nullable|integer|exists:movements,id',
            'reps'       => 'sometimes|integer|min:0',
            'weight'     => 'sometimes|nullable|numeric|min:0',
            'intensity'  => 'sometimes|nullable|integer|min:1|max:10',
            'notes'      => 'sometimes|nullable|string',
        ]);

        $set->update([
            'exercise'    => $data['exercise'] ?? $set->exercise,
            'movement_id' => array_key_exists('movementId', $data) ? $data['movementId'] : $set->movement_id,
            'reps'        => $data['reps'] ?? $set->reps,
            'weight'      => array_key_exists('weight', $data) ? $data['weight'] : $set->weight,
            'intensity'   => array_key_exists('intensity', $data) ? $data['intensity'] : $set->intensity,
            'notes'       => array_key_exists('notes', $data) ? $data['notes'] : $set->notes,
        ]);

        return response()->json($set->fresh()->load('movement'));
    }

    /**
     * Delete a specific set from a workout owned by the authenticated user.
     *
     * DELETE /api/workouts/{workoutId}/sets/{setId}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $workoutId
     * @param  int  $setId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroySet(Request $request, int $workoutId, int $setId): JsonResponse
    {
        $workout = $request->user()->workouts()->findOrFail($workoutId);
        $set     = $workout->sets()->findOrFail($setId);
        $set->delete();

        return response()->json(['message' => 'Set deleted']);
    }
}
