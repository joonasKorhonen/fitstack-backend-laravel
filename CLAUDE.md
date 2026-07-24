# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Start dev server (server + queue + log tail + vite concurrently)
composer dev

# Start server only
php artisan serve

# Run all tests
php artisan test
# or
composer test   # also clears config cache first

# Run a single test class or method
php artisan test --filter WorkoutControllerTest
php artisan test tests/Feature/MealControllerTest.php

# Lint (PHP syntax check — matches CI)
find app routes config database -name "*.php" | xargs php -l

# Code formatting (Laravel Pint)
vendor/bin/pint

# Database migrations
php artisan migrate

# After first install: generate app key and JWT secret
php artisan key:generate
php artisan jwt:secret
```

## Commit messages

Plain imperative subject line, **no type prefix** — this matches the existing history.

- Start the subject with a capitalized imperative verb (`Add`, `Remove`, `Fix`, `Clarify`) describing what the commit does. Do **not** use Conventional Commit prefixes (`fix:`, `feat:`, `chore:`, …).
- Keep the subject concise and specific — say what changed and, when it fits, the effect: e.g. `Add feature tests for MealController destroy endpoint`, `Clarify updateSet docblock on null-handling asymmetry`.
- For anything non-trivial, add a body (blank line, then wrapped ~72 chars) explaining the *why* and the mechanism — the failure it fixes or the contract it relies on — not a restatement of the diff.

## Architecture

### Overview

Laravel 11 REST API serving a fitness tracking frontend. It is an alternative to a NestJS backend — both share the **same PostgreSQL database** (originally managed by Prisma) and expose an identical API. All routes live under `/api` (configured in `bootstrap/app.php`).

### Auth Flow

Two-token scheme implemented manually in `AuthController`:

- **Access token**: short-lived JWT signed with `JWT_SECRET`, sent in the `Authorization: Bearer` header. Uses `tymon/jwt-auth` (`auth:api` guard, configured in `config/auth.php`).
- **Refresh token**: 7-day opaque token stored as `sha256` hash in the `refresh_tokens` table, sent/received as an HttpOnly `Lax` cookie named `refresh_token`.

Refresh token rotation uses **token families** (a shared `token_family` UUID across rotations). When a token is consumed it is soft-deleted. If a soft-deleted token is presented again, the entire family is force-deleted to mitigate theft.

### Schema Compatibility

The migrations mirror the Prisma schema from the NestJS backend. Two notable constraints:

1. `User` model sets `const UPDATED_AT = null` — the `users` table has no `updated_at` column.
2. Login normalises bcrypt prefixes: Node.js produces `$2b$` hashes, PHP expects `$2y$`. They are cryptographically identical; `AuthController::login()` does a `str_replace` before `Hash::check()`.

### S3 Avatars

`User` stores only the S3 object key in `avatar_path`. A virtual Eloquent attribute `avatarUrl` calls `Storage::disk('s3')->url($this->avatar_path)` at read time — no URL is stored in the database. `UserController::uploadAvatar()` uploads the new file before deleting the old one to avoid data loss on failure.

### Data Model

- `User` → has many `Workout`, `Meal`, `Movement`, `RefreshToken`, `PasswordResetToken`
- `Workout` → has many `WorkoutSet`; also carries redundant top-level `exercise/reps/weight` fields (derived from the first set on create, for NestJS compatibility)
- `WorkoutSet` → nullable `movement_id` FK to `movements` table; also accepts a plain string `exercise` field when no Movement record exists
- `RefreshToken` uses `SoftDeletes` (consumed tokens are soft-deleted, not removed, enabling theft detection)
- `PasswordResetToken` stores a `sha256` hash of the raw token; raw token is only ever in the email link

### Response Shaping

`UserResource` is the only API Resource class and shapes all user-related responses (camelCase keys, virtual `avatarUrl`). Workout, meal, and movement responses return raw Eloquent model JSON directly.

### Testing

`phpunit.xml` configures tests to use **SQLite in-memory** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). CI runs against PostgreSQL 16. Tests that rely on PostgreSQL-specific behaviour will not behave identically locally.

### CI / CD

GitHub Actions (`.github/workflows/ci.yml`) on every push/PR to `main`: migrate → lint → test. On push to `main` only, deploys to EC2 via SSH (`appleboy/ssh-action`): pulls, `composer install --no-dev`, migrates, clears config/cache, restarts `php8.3-fpm`.
