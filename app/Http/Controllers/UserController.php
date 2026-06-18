<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    /**
     * Return the authenticated user's profile.
     *
     * GET /api/users/profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * Update username and/or email for the authenticated user.
     *
     * PATCH /api/users/profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'username' => ['sometimes', 'string', 'min:3', 'max:50', Rule::unique('users')->ignore($userId)],
            'email'    => ['sometimes', 'nullable', 'email', Rule::unique('users')->ignore($userId)],
        ]);

        $request->user()->update($data);

        return response()->json(new UserResource($request->user()->fresh()));
    }

    /**
     * Permanently delete the authenticated user's account and their S3 avatar.
     * Blacklists the current access token so it cannot be reused after deletion.
     *
     * DELETE /api/users/profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
        }

        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
            // Token already expired or absent — safe to ignore
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted'])
            ->withoutCookie('refresh_token');
    }

    /**
     * Upload a new avatar image to S3, replacing any existing one.
     * The new file is uploaded before the old one is removed so a failed upload
     * never leaves the user without an avatar.
     * Stores the S3 object key in the database; URL is generated on read.
     * Accepts multipart/form-data with field "file" (JPEG/PNG/WebP, max 5 MB).
     *
     * POST /api/users/avatar
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse  { avatarUrl: string }
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,webp|max:5120',
        ]);

        $user    = $request->user();
        $oldPath = $user->avatar_path;

        // 1. Upload new file first — if this fails, nothing is lost
        // putFile() uploads with an auto-generated UUID filename and returns the stored path
        $newPath = Storage::disk('s3')->putFile('avatars', $request->file('file'));

        // 2. Persist the new S3 key
        $user->update(['avatar_path' => $newPath]);

        // 3. Delete the old file last — a failure here is harmless (orphaned object, not lost data)
        if ($oldPath) {
            Storage::disk('s3')->delete($oldPath);
        }

        return response()->json(new UserResource($user->fresh()));
    }

    /**
     * Remove the authenticated user's avatar from S3 and clear the stored path.
     *
     * DELETE /api/users/avatar
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->avatar_path) {
            return response()->json(['message' => 'No avatar to delete'], 404);
        }

        Storage::disk('s3')->delete($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return response()->json(new UserResource($user->fresh()));
    }
}
