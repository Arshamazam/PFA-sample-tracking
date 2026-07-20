<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the configured SMS gateway. Adding a provider = a new driver
        // class + a config entry; nothing here changes.
        $this->app->bind(SmsGateway::class, function () {
            $driver = config('sms.driver', 'log');
            $class = config("sms.drivers.{$driver}.class");

            if ($class === null) {
                throw new \InvalidArgumentException("Unknown SMS driver [{$driver}].");
            }

            return $this->app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login: 5 attempts per minute per IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // General API surface: 60/min per authenticated user, else per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Public tracking pages: 30/min/IP. Public dispute filing: 3/day/IP.
        RateLimiter::for('public-track', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('public-dispute', fn (Request $request) => Limit::perDay(3)->by($request->ip()));

        // SMS notification listeners in app/Listeners are auto-discovered by the
        // framework from their typed handle() signatures — no explicit mapping needed.
    }
}
