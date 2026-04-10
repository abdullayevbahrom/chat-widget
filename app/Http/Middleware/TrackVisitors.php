<?php

namespace App\Http\Middleware;

use App\Services\VisitorTrackingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitors
{
    /**
     * Maximum number of tracking events per IP per minute.
     * Prevents abuse from rapid-fire requests (Issue #13).
     */
    protected const MAX_TRACKING_EVENTS_PER_MINUTE = 60;

    /**
     * Handle an incoming request.
     *
     * Delegates all tracking logic to VisitorTrackingService.
     * The middleware is only responsible for:
     * 1. Checking if the request should be tracked
     * 2. Rate limiting
     * 3. Dispatching to the service
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // All tracking logic (bot check, extension filter, data extraction, sanitization)
        // is handled by VisitorTrackingService — no duplication here (Issue #2)
        $this->trackVisitor($request);

        return $response;
    }

    /**
     * Track the visitor, with rate limiting protection.
     */
    protected function trackVisitor(Request $request): void
    {
        // Rate limiting: prevent abuse from rapid requests from same IP (Issue #13)
        $key = 'visitor-track:'.$this->resolveRateLimitKey($request);

        if ($this->isRateLimited($key)) {
            return;
        }

        // Delegate all tracking logic to the service
        $service = app(VisitorTrackingService::class);

        // The service handles:
        // - Session ID extraction
        // - Bot detection (if track_bots is false)
        // - File extension filtering
        // - Data extraction, sanitization, and encryption
        // - Database upsert (atomic create-or-update)
        $service->track($request);

        // Record this tracking event for rate limiting
        $this->recordRateLimit($key);
    }

    /**
     * Resolve the rate limiting key for the request.
     * Uses IP address as the primary key.
     */
    protected function resolveRateLimitKey(Request $request): string
    {
        return $request->ip() ?? $request->server('REMOTE_ADDR', 'unknown');
    }

    /**
     * Check if the request is rate limited.
     */
    protected function isRateLimited(string $key): bool
    {
        return RateLimiter::tooManyAttempts(
            $key,
            self::MAX_TRACKING_EVENTS_PER_MINUTE
        );
    }

    /**
     * Record a tracking attempt for rate limiting.
     */
    protected function recordRateLimit(string $key): void
    {
        RateLimiter::hit($key, 60); // 60 seconds decay
    }
}
