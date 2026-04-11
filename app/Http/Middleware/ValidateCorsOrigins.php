<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to validate CORS origins and reject wildcard patterns.
 *
 * This ensures that only properly formatted, specific origins are allowed,
 * preventing security issues with wildcard (*) or malformed origins.
 */
class ValidateCorsOrigins
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        if ($origin !== null) {
            if (! $this->isValidOrigin($origin)) {
                Log::warning('CORS origin validation failed: invalid origin rejected.', [
                    'origin' => $origin,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Invalid origin.',
                    'code' => 'INVALID_ORIGIN',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }

    /**
     * Validate that an origin is properly formatted and not a wildcard.
     */
    protected function isValidOrigin(string $origin): bool
    {
        // Reject empty origins
        if (trim($origin) === '') {
            return false;
        }

        // Reject wildcard origins
        if (str_contains($origin, '*')) {
            return false;
        }

        // Validate URL format: must have scheme://host[:port]
        $parsed = parse_url($origin);

        if ($parsed === false) {
            return false;
        }

        // Must have a scheme (http or https)
        if (! isset($parsed['scheme']) || ! in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }

        // Must have a host
        if (! isset($parsed['host']) || trim($parsed['host']) === '') {
            return false;
        }

        // Host must be a valid domain or IP
        $host = $parsed['host'];

        // Reject localhost with invalid port format
        if ($host === 'localhost' && isset($parsed['port']) && ! is_numeric($parsed['port'])) {
            return false;
        }

        // Validate domain format (basic check)
        if (! filter_var($host, FILTER_VALIDATE_DOMAIN) && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Must not have path, query, or fragment
        if (isset($parsed['path']) || isset($parsed['query']) || isset($parsed['fragment'])) {
            return false;
        }

        return true;
    }
}
