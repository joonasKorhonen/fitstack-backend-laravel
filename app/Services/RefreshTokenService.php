<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

class RefreshTokenService
{
    private const TTL_DAYS = 7;

    /**
     * Persist a new refresh token and return the raw (unhashed) value to send in the cookie.
     *
     * @param  string|null  $family  Existing family UUID for rotation; null starts a new family.
     * @return string  Raw token — never stored, only sent to the client.
     */
    public function issue(User $user, ?string $family = null): string
    {
        $rawToken = Str::random(80);
        $family   = $family ?? hash('sha256', $rawToken . $user->id . microtime());

        RefreshToken::create([
            'token'        => hash('sha256', $rawToken),
            'token_family' => $family,
            'user_id'      => $user->id,
            'expires_at'   => now()->addDays(self::TTL_DAYS),
        ]);

        return $rawToken;
    }

    /**
     * Consume a refresh token and issue a new one in the same family (rotation).
     *
     * If the token is soft-deleted (already rotated), the entire family is force-deleted
     * to mitigate theft — a consumed token being re-presented implies the raw value leaked.
     *
     * @return RotationResult  Check $result->succeeded before accessing $result->user / $result->rawToken.
     */
    public function rotate(string $rawToken): RotationResult
    {
        $hashed = hash('sha256', $rawToken);
        $stored = RefreshToken::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if (! $stored) {
            // Soft-deleted match means the token was already rotated — possible theft.
            // Revoke the entire family to invalidate all derived tokens.
            $stolen = RefreshToken::withTrashed()->where('token', $hashed)->first();

            if ($stolen) {
                RefreshToken::withTrashed()
                    ->where('token_family', $stolen->token_family)
                    ->forceDelete();

                return RotationResult::failed('stolen');
            }

            return RotationResult::failed('invalid');
        }

        $user   = $stored->user;
        $family = $stored->token_family;

        $stored->delete();

        $newRawToken = $this->issue($user, $family);

        return RotationResult::success($user, $newRawToken);
    }

    /**
     * Revoke a single token by its raw value — used on logout.
     * Safe to call with a token that does not exist (no-op).
     */
    public function revokeToken(string $rawToken): void
    {
        RefreshToken::where('token', hash('sha256', $rawToken))->forceDelete();
    }

    /**
     * Revoke all active refresh tokens for a user — used on password reset.
     * Ensures no existing session survives a credential change.
     */
    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->forceDelete();
    }

    /** Cookie expiry in minutes, matching the token's database TTL. */
    public function ttlMinutes(): int
    {
        return self::TTL_DAYS * 24 * 60;
    }
}
