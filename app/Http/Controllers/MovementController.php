<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MovementController extends Controller
{
    /**
     * List all custom movements for the authenticated user, sorted alphabetically.
     *
     * GET /api/movements
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  Array of movement objects.
     */
    public function index(Request $request): JsonResponse
    {
        $movements = $request->user()
            ->movements()
            ->orderBy('name')
            ->get();

        return response()->json($movements);
    }

    /**
     * Create a new custom movement for the authenticated user.
     * Name must be unique per user.
     *
     * POST /api/movements
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  The created movement (201).
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('movements')->where('user_id', $userId),
            ],
        ]);

        $movement = $request->user()->movements()->create(['name' => $data['name']]);

        return response()->json($movement, 201);
    }
}
