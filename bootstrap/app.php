<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // This is where you might have existing API middleware group configurations
        // $middleware->api(prepend: [ ... ]);

        // Define your named rate limiters here
        /* RateLimiter::for('api', function (Request $request) {
            // Limit to 100 requests per hour per authenticated user (API token)
            // No user -> limited by IP.
            // For Sanctum token-based auth, $request->user() will give the authenticated user.
            return Limit::perHour(100)->by($request->user()?->id ?: $request->ip());
        }); */

        // You might also want a more general throttle for unauthenticated requests if needed
        // RateLimiter::for('global', function (Request $request) {
        //     return Limit::perMinute(60)->by($request->ip());
        // });

        // Add throttle middleware alias if not already available by default in L11's slim setup
        // or ensure it's part of a group.
        // Laravel 11 handles common middleware like 'throttle' a bit differently.
        // Often, you apply it directly to routes or route groups.
        // The key is defining the limiter with RateLimiter::for().
        // Then you use 'throttle:name_of_limiter' on routes.
})
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
