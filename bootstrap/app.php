<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API rate limiting is configured explicitly in AppServiceProvider
        // using RateLimiter::for('api', ...) with per-user/per-IP limits.

        // CSRF protection — API endpoint'lari CSRF dan ististno
        // API token-based auth (Sanctum) uchun CSRF kerak emas
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // TrustProxies: Configure trusted proxies for correct IP detection
        // behind load balancers / reverse proxies (Issue #11).
        // The TRUSTED_PROXIES env variable should be set in production
        // to the IP(s) of your load balancer or reverse proxy.
        $middleware->trustProxies(at: function (Request $request) {
            return [
                'headers' => Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_HOST |
                    Request::HEADER_X_FORWARDED_PORT |
                    Request::HEADER_X_FORWARDED_PROTO |
                    Request::HEADER_X_FORWARDED_AWS_ELB,
                'proxies' => config('app.trusted_proxies', env('TRUSTED_PROXIES', '*')),
            ];
        });

        // Visitor tracking middleware — track visitor sessions on web routes
        $middleware->web(append: [
            \App\Http\Middleware\TrackVisitors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
