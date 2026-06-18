<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\PasswordResetToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const REFRESH_TTL_DAYS = 7;
    private const RESET_TTL_HOURS  = 1;

    /**
     * Register a new user and return an access token + refresh token cookie.
     *
     * POST /api/auth/register
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users',
            'email'    => 'nullable|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'username' => $data['username'],
            'email'    => $data['email'] ?? null,
            'password' => $data['password'],
        ]);

        $accessToken = JWTAuth::fromUser($user);
        $rawRefresh  = $this->issueRefreshToken($user);

        return response()
            ->json(['accessToken' => $accessToken, 'user' => new UserResource($user)], 201)
            ->cookie(...$this->refreshCookie($rawRefresh));
    }

    /**
     * Authenticate a user by username + password.
     *
     * POST /api/auth/login
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['username'])->first();

        // Node's bcrypt produces $2b$ prefixed hashes; PHP expects $2y$.
        // They are cryptographically identical — normalise before checking.
        $hash = str_replace('$2b$', '$2y$', (string) ($user?->password ?? ''));

        if (! $user || ! Hash::check($data['password'], $hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $accessToken = JWTAuth::fromUser($user);
        $rawRefresh  = $this->issueRefreshToken($user);

        return response()
            ->json(['accessToken' => $accessToken, 'user' => new UserResource($user)])
            ->cookie(...$this->refreshCookie($rawRefresh));
    }

    /**
     * Issue a new access token using the HttpOnly refresh token cookie.
     * Rotates the refresh token on every call. If a previously-rotated
     * (soft-deleted) token is presented, the entire token family is revoked
     * to mitigate refresh token theft.
     *
     * POST /api/auth/refresh
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $rawRefresh = $request->cookie('refresh_token');

        if (! $rawRefresh) {
            return response()->json(['message' => 'No refresh token'], 401);
        }

        $hashed = hash('sha256', $rawRefresh);
        $stored = RefreshToken::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if (! $stored) {
            // A soft-deleted match means the token was already rotated,
            // indicating possible theft — revoke the entire family.
            $stolen = RefreshToken::withTrashed()->where('token', $hashed)->first();

            if ($stolen) {
                RefreshToken::withTrashed()
                    ->where('token_family', $stolen->token_family)
                    ->forceDelete();
            }

            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $user   = $stored->user;
        $family = $stored->token_family;

        // Soft-delete the consumed token so the theft check above can trace it
        $stored->delete();

        $newAccessToken = JWTAuth::fromUser($user);
        $newRawRefresh  = $this->issueRefreshToken($user, $family);

        return response()
            ->json(['accessToken' => $newAccessToken])
            ->cookie(...$this->refreshCookie($newRawRefresh));
    }

    /**
     * Revoke the current refresh token and clear the cookie.
     *
     * POST /api/auth/logout
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $rawRefresh = $request->cookie('refresh_token');

        if ($rawRefresh) {
            RefreshToken::where('token', hash('sha256', $rawRefresh))->forceDelete();
        }

        // Blacklist the current access token so it cannot be reused until it expires
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
            // No token present (e.g. already expired) — safe to ignore
        }

        return response()
            ->json(['message' => 'Logged out'])
            ->withoutCookie('refresh_token');
    }

    /**
     * Send a password reset email if the given address belongs to an account.
     * Always returns 200 to prevent email enumeration.
     *
     * POST /api/auth/forgot-password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
        }

        PasswordResetToken::where('user_id', $user->id)->delete();

        $rawToken = Str::random(64);
        PasswordResetToken::create([
            'token_hash' => hash('sha256', $rawToken),
            'user_id'    => $user->id,
            'expires_at' => now()->addHours(self::RESET_TTL_HOURS),
        ]);

        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $rawToken;

        Mail::send('emails.reset-password', ['resetUrl' => $resetUrl, 'user' => $user], function ($msg) use ($user) {
            $msg->to($user->email)->subject('Reset your FitStack password');
        });

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * Validate a reset token and update the user's password.
     * All existing sessions are revoked on success.
     *
     * POST /api/auth/reset-password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $record = PasswordResetToken::where('token_hash', hash('sha256', $data['token']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        $user = $record->user;
        $user->update(['password' => $data['password']]);

        RefreshToken::where('user_id', $user->id)->forceDelete();
        $record->delete();

        // Blacklist the current access token if one was provided with this request
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
            // No token present — safe to ignore
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    // ── private helpers ───────────────────────────────────────────────────────

    /**
     * Persist a new refresh token for the user and return the raw (unhashed) value.
     *
     * @param  \App\Models\User  $user
     * @param  string|null  $family  Existing family ID for rotation; null creates a new family.
     * @return string  The raw (unhashed) refresh token to send in the cookie.
     */
    private function issueRefreshToken(User $user, ?string $family = null): string
    {
        $rawToken = Str::random(80);
        $family   = $family ?? hash('sha256', $rawToken . $user->id . microtime());

        RefreshToken::create([
            'token'        => hash('sha256', $rawToken),
            'token_family' => $family,
            'user_id'      => $user->id,
            'expires_at'   => now()->addDays(self::REFRESH_TTL_DAYS),
        ]);

        return $rawToken;
    }

    /**
     * Build the argument list for response()->cookie() for the refresh token.
     * Spreads directly into the cookie() call via the splat operator.
     * The secure flag is enabled only in production.
     *
     * @param  string  $rawToken
     * @return array{
     *   0: string,
     *   1: string,
     *   2: int,
     *   3: string,
     *   4: null,
     *   5: bool,
     *   6: bool,
     *   7: bool,
     *   8: string
     * }
     */
    private function refreshCookie(string $rawToken): array
    {
        return [
            'refresh_token',              // name
            $rawToken,                    // value (raw token sent to the client)
            self::REFRESH_TTL_DAYS * 24 * 60, // minutes until expiry
            '/',                          // path
            null,                         // domain (current domain)
            app()->isProduction(),        // secure (HTTPS only in production)
            true,                         // httpOnly (not accessible via JS)
            false,                        // raw (do not URL-encode the value)
            'Lax',                        // sameSite
        ];
    }

}
