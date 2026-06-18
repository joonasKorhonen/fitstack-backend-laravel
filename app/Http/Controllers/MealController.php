<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealController extends Controller
{
    /**
     * List all meals for the authenticated user, newest first.
     *
     * GET /api/meals
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  Array of meal objects.
     */
    public function index(Request $request): JsonResponse
    {
        $meals = $request->user()
            ->meals()
            ->orderByDesc('date')
            ->get();

        return response()->json($meals);
    }

    /**
     * Return a single meal belonging to the authenticated user.
     *
     * GET /api/meals/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $meal = $request->user()->meals()->findOrFail($id);

        return response()->json($meal);
    }

    /**
     * Create a new meal for the authenticated user.
     *
     * POST /api/meals
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  The created meal (201).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'     => 'sometimes|date',
            'title'    => 'required|string|max:255',
            'calories' => 'required|integer|min:0',
            'protein'  => 'nullable|integer|min:0',
            'carbs'    => 'nullable|integer|min:0',
            'fat'      => 'nullable|integer|min:0',
            'notes'    => 'nullable|string',
        ]);

        $meal = $request->user()->meals()->create($data);

        return response()->json($meal, 201);
    }

    /**
     * Update a meal owned by the authenticated user.
     *
     * PATCH /api/meals/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse  The updated meal.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $meal = $request->user()->meals()->findOrFail($id);

        $data = $request->validate([
            'date'     => 'sometimes|date',
            'title'    => 'sometimes|string|max:255',
            'calories' => 'sometimes|integer|min:0',
            'protein'  => 'nullable|integer|min:0',
            'carbs'    => 'nullable|integer|min:0',
            'fat'      => 'nullable|integer|min:0',
            'notes'    => 'nullable|string',
        ]);

        $meal->update($data);

        return response()->json($meal->fresh());
    }

    /**
     * Delete a meal owned by the authenticated user.
     *
     * DELETE /api/meals/{id}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $meal = $request->user()->meals()->findOrFail($id);
        $meal->delete();

        return response()->json(['message' => 'Meal deleted']);
    }
}
