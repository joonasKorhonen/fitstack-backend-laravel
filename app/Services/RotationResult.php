<?php

namespace App\Services;

use App\Models\User;

final readonly class RotationResult
{
    private function __construct(
        public bool $succeeded,
        public ?User $user = null,
        public ?string $rawToken = null,
        public ?string $failureReason = null,
    ) {}

    /** @param  string  $rawToken  Raw (unhashed) token to send in the cookie. */
    public static function success(User $user, string $rawToken): self
    {
        return new self(succeeded: true, user: $user, rawToken: $rawToken);
    }

    /** @param  string  $reason  'invalid' — token not found or expired; 'stolen' — family revoked. */
    public static function failed(string $reason): self
    {
        return new self(succeeded: false, failureReason: $reason);
    }
}
