<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefreshTokenService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RefreshTokenService();
        $this->user    = User::factory()->create();
    }

    public function test_rotate_returns_success_with_user_and_new_token(): void
    {
        $rawToken = $this->service->issue($this->user);

        $result = $this->service->rotate($rawToken);

        $this->assertTrue($result->succeeded);
        $this->assertSame($this->user->id, $result->user->id);
        $this->assertNotSame($rawToken, $result->rawToken);
    }

    public function test_rotate_soft_deletes_the_consumed_token(): void
    {
        // Soft-delete is required for theft detection: a force-deleted token
        // leaves no trace, making it impossible to detect when a consumed
        // token is presented again.
        $rawToken = $this->service->issue($this->user);
        $hashed   = hash('sha256', $rawToken);

        $this->service->rotate($rawToken);

        $this->assertSoftDeleted('refresh_tokens', ['token' => $hashed]);
    }

    public function test_rotate_issues_new_token_in_same_family(): void
    {
        // Family continuity is what makes theft detection work across multiple rotations.
        // If each rotation created a new family, revoking a stolen token's family would
        // only affect one token — tokens derived from an earlier breach would remain valid.
        $rawToken       = $this->service->issue($this->user);
        $originalFamily = RefreshToken::where('token', hash('sha256', $rawToken))->value('token_family');

        $result = $this->service->rotate($rawToken);

        $newFamily = RefreshToken::where('token', hash('sha256', $result->rawToken))->value('token_family');
        $this->assertSame($originalFamily, $newFamily);
    }

    public function test_rotate_returns_failed_for_unknown_token(): void
    {
        // A token that was never issued (or has been force-deleted) must be rejected
        // without triggering theft detection. Distinct from the expired and stolen cases.
        $result = $this->service->rotate('nonexistent-token');

        $this->assertFalse($result->succeeded);
        $this->assertSame('invalid', $result->failureReason);
    }

    public function test_rotate_returns_failed_for_expired_token(): void
    {
        // An expired token must not trigger theft detection (family revocation).
        // It is still present in the database (not soft-deleted), so withTrashed()
        // would incorrectly identify it as stolen — onlyTrashed() is the fix.
        $rawToken = $this->service->issue($this->user);

        RefreshToken::where('token', hash('sha256', $rawToken))
            ->update(['expires_at' => now()->subMinute()]);

        $result = $this->service->rotate($rawToken);

        $this->assertFalse($result->succeeded);
        $this->assertSame('invalid', $result->failureReason);
    }

    public function test_rotate_revokes_entire_family_when_stolen_token_is_reused(): void
    {
        // Revoking only the re-presented token is insufficient: the attacker
        // may already hold a newer token from a previous successful rotation.
        // The entire family must be revoked to invalidate all derived tokens.
        $rawToken = $this->service->issue($this->user);
        $family   = RefreshToken::where('token', hash('sha256', $rawToken))->value('token_family');

        // First rotation — consumes the original token (soft-delete)
        $this->service->rotate($rawToken);

        // Same token presented again — simulates theft
        $result = $this->service->rotate($rawToken);

        $this->assertFalse($result->succeeded);
        $this->assertSame('stolen', $result->failureReason);

        // Entire family must be permanently removed (force-deleted, not soft-deleted)
        $remaining = RefreshToken::withTrashed()->where('token_family', $family)->count();
        $this->assertSame(0, $remaining);
    }
}
