<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $req) => 
            Limit::perMinute(5)->by($req->ip())
        );
        
        RateLimiter::for('api', fn (Request $req) => 
            Limit::perMinute(60)->by($req->user()?->id ?: $req->ip())
        );
        
        RateLimiter::for('weather-fetch', fn (Request $req) => 
            Limit::perMinute(30)->by($req->ip())
        );
    }
}
