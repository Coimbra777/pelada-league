<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('webhook', function (Request $request) {
            $limit = (int) config('services.asaas.webhook_rate_limit', 60);

            return Limit::perMinute($limit)->by($request->ip());
        });
    }
}
