<?php

use App\Http\Middleware\TrackVisitors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(
            array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
            static fn (string $proxy): bool => $proxy !== ''
        ));

        // API rate limiting is configured explicitly in AppServiceProvider
        // using RateLimiter::for('api', ...) with per-user/per-IP limits.

        // CSRF protection — only exclude webhook and widget message endpoints
        // that are called by external services (Telegram, widget SDK) and cannot
        // include CSRF tokens. All other API routes (tenant CRUD, project management)
        // remain CSRF-protected for SPA cookie-based auth with Sanctum.
        //
        // SECURITY NOTE: `api/widget/messages` is excluded from CSRF because the
        // widget runs in a cross-origin iframe and cannot read/forward CSRF tokens.
        // Protection is provided by:
        //  1. ValidateWidgetKey middleware (widget key authentication)
        //  2. EnsureVerifiedWidgetDomain middleware (domain origin verification)
        //  3. Rate limiting (widget-message limiter, 30 req/min per key)
        //  4. Encrypted visitor binding tokens (cookie-based, HttpOnly, Secure)
        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook/*',
            'api/widget/messages',
            'api/widget/conversation/close',
        ]);

        // TrustProxies: Configure trusted proxies for correct IP detection
        // behind load balancers / reverse proxies (Issue #11).
        // The TRUSTED_PROXIES env variable should be set in production
        // to the IP(s) of your load balancer or reverse proxy.
        $middleware->trustProxies(
            at: $trustedProxies === [] ? null : $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Visitor tracking middleware — track visitor sessions on web routes
        $middleware->web(append: [
            TrackVisitors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
