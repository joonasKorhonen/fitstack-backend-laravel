<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', fn($req) =>
            Limit::perMinute(60)->by($req->user()?->id ?: $req->ip())
        );

        RateLimiter::for('register', fn($req) =>
            Limit::perMinute(10)->by($req->ip())
        );

        RateLimiter::for('uploads', fn($req) =>
            Limit::perHour(20)->by($req->user()?->id ?: $req->ip())
        );

        RateLimiter::for('password-reset', fn($req) =>
            Limit::perMinute(5)->by($req->ip())
        );
    }
}
