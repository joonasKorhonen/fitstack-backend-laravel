# FitStack Backend (Laravel)

[![CI](https://github.com/joonasKorhonen/fitstack-backend-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/joonasKorhonen/fitstack-backend-laravel/actions/workflows/ci.yml)

Laravel 11 REST API for **FitStack**, a fitness tracking app where users log workouts (with sets, reps, weight, and intensity), define custom exercises, track meals with macronutrients, and manage their profile.

This is an alternative backend to [fitstack-backend](https://github.com/joonasKorhonen/fitstack-backend) (NestJS). Both backends share the same PostgreSQL database and expose an identical API — switching between them requires only a one-line change in the frontend's `.env`.

## Tech Stack

- **Laravel 11** (PHP 8.3)
- **PostgreSQL 16** via **Eloquent** ORM
- **JWT** auth with refresh token rotation (`tymon/jwt-auth`)
- **AWS S3** for profile picture storage
- **AWS SES** for password reset emails
- Validation via Laravel's built-in request validation

## Features

- **Auth** — register, login, refresh tokens, logout, forgot/reset password
- **Users** — profile fetch/update/delete, avatar upload (jpeg/png/webp, 5 MB max)
- **Workouts** — log workouts with multiple sets, exercises, reps, weight, intensity, and notes
- **Movements** — user-defined custom exercise library
- **Meals** — track meals with calories, protein, carbs, fat
- All routes (except `auth/*`) are JWT-protected

## Prerequisites

- PHP 8.3+
- Composer
- PostgreSQL 16 (shared with fitstack-backend, or standalone)
- AWS account with:
  - An S3 bucket for avatar uploads
  - A verified SES sender identity for password reset emails

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Required variables:

| Variable                | Purpose                                                     |
| ----------------------- | ----------------------------------------------------------- |
| `DB_HOST`               | PostgreSQL host                                             |
| `DB_DATABASE`           | Database name                                               |
| `DB_USERNAME`           | Database user                                               |
| `DB_PASSWORD`           | Database password                                           |
| `JWT_SECRET`            | Secret for signing access tokens                            |
| `AWS_ACCESS_KEY_ID`     | IAM user access key                                         |
| `AWS_SECRET_ACCESS_KEY` | IAM user secret                                             |
| `AWS_DEFAULT_REGION`    | AWS region for S3 & SES (e.g. `eu-north-1`)                 |
| `AWS_BUCKET`            | S3 bucket name for avatar uploads                           |
| `MAIL_FROM_ADDRESS`     | Verified SES sender address                                 |
| `FRONTEND_URL`          | Frontend origin — used for CORS and password reset links    |

### 3. Run migrations

If using an existing fitstack-backend database (tables already created by Prisma):

```bash
php artisan migrate --pretend   # verify nothing conflicts
```

Then mark the migrations as run without executing them:

```sql
INSERT INTO migrations (migration, batch) VALUES
  ('2024_01_01_000001_create_users_table', 1), ...
```

If starting from scratch:

```bash
php artisan migrate
```

### 4. Start the server

```bash
php artisan serve
```

The API listens on `http://localhost:8000/api` by default.

## Switching between backends

The frontend ([fitstack-frontend](https://github.com/joonasKorhonen/fitstack-frontend)) reads `NEXT_PUBLIC_API_URL` to know which backend to talk to. Change one line in `.env.local` and restart the frontend:

```bash
# NestJS backend
NEXT_PUBLIC_API_URL=http://localhost:3000

# Laravel backend
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Both backends share the same PostgreSQL database, so all data persists across switches.

## API Overview

All routes are mounted under `/api`.

### Auth (`/auth`)

| Method | Path               | Description                                              |
| ------ | ------------------ | -------------------------------------------------------- |
| POST   | `/register`        | Create account (`username`, `password`, optional `email`) |
| POST   | `/login`           | Returns access token + sets refresh token cookie         |
| POST   | `/refresh`         | Rotate refresh token, issue new access token             |
| POST   | `/logout`          | Invalidate refresh token and blacklist access token      |
| POST   | `/forgot-password` | Send reset link to verified email                        |
| POST   | `/reset-password`  | Submit token + new password                              |

### Users (`/users`) — JWT required

| Method | Path       | Description                                          |
| ------ | ---------- | ---------------------------------------------------- |
| GET    | `/profile` | Current user                                         |
| PATCH  | `/profile` | Update `username` or `email`                         |
| DELETE | `/profile` | Delete account (also removes avatar from S3)         |
| POST   | `/avatar`  | Upload profile picture (multipart, field `file`)     |
| DELETE | `/avatar`  | Remove current avatar                                |

### Workouts (`/workouts`) — JWT required

CRUD for workouts and their sets, plus an endpoint to append sets to an existing workout.

### Movements (`/movements`) — JWT required

GET and POST for the user's custom exercise library.

### Meals (`/meals`) — JWT required

Full CRUD for meal entries with macronutrient fields.

## Project Structure

```
app/
├── Http/
│   ├── Controllers/    AuthController, UserController, WorkoutController, MealController, MovementController
│   ├── Middleware/     HandleCors
│   └── Resources/      UserResource
├── Models/             User, Workout, WorkoutSet, Meal, Movement, RefreshToken, PasswordResetToken
database/
└── migrations/         One migration per table matching the shared schema
resources/views/
└── emails/             reset-password.blade.php
routes/
└── api.php
```

## CI

Every push and pull request to `main` runs via GitHub Actions:

1. **Migrate** — applies migrations against a fresh PostgreSQL instance
2. **Lint** — PHP syntax check across all source files
3. **Test** — `php artisan test`
