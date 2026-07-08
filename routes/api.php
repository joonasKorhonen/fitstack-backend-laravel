<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkoutController;

// ── Auth (public) ─────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',         [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login',            [AuthController::class, 'login']);
    Route::post('refresh',          [AuthController::class, 'refresh']);
    Route::post('logout',           [AuthController::class, 'logout']);
    Route::post('forgot-password',  [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('reset-password',   [AuthController::class, 'resetPassword']);
});

// ── Protected routes (JWT required) ──────────────────────────────────────────
Route::middleware('auth:api')->group(function () {

    // Users
    Route::prefix('users')->group(function () {
        Route::get('profile',    [UserController::class, 'profile']);
        Route::patch('profile',  [UserController::class, 'updateProfile']);
        Route::delete('profile', [UserController::class, 'deleteProfile']);
        Route::post('avatar',    [UserController::class, 'uploadAvatar'])->middleware('throttle:uploads');
        Route::delete('avatar',  [UserController::class, 'deleteAvatar']);
    });

    // Workouts + sets
    Route::prefix('workouts')->group(function () {
        Route::get('/',     [WorkoutController::class, 'index']);
        Route::post('/',    [WorkoutController::class, 'store']);
        Route::get('{id}',  [WorkoutController::class, 'show']);
        Route::patch('{id}', [WorkoutController::class, 'update']);
        Route::delete('{id}', [WorkoutController::class, 'destroy']);

        Route::post('{id}/sets',                     [WorkoutController::class, 'addSets']);
        Route::patch('{workoutId}/sets/{setId}',     [WorkoutController::class, 'updateSet']);
        Route::delete('{workoutId}/sets/{setId}',    [WorkoutController::class, 'destroySet']);
    });

    // Meals
    Route::prefix('meals')->group(function () {
        Route::get('/',      [MealController::class, 'index']);
        Route::post('/',     [MealController::class, 'store']);
        Route::get('{id}',   [MealController::class, 'show']);
        Route::patch('{id}', [MealController::class, 'update']);
        Route::delete('{id}', [MealController::class, 'destroy']);
    });

    // Movements
    Route::prefix('movements')->group(function () {
        Route::get('/',   [MovementController::class, 'index']);
        Route::post('/',  [MovementController::class, 'store']);
    });
});
