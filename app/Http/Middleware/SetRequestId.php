<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure every request has a unique X-Request-ID.
 *
 * If the client already provided an X-Request-ID, it will be reused.
 * Otherwise, a new UUID is generated. The ID is added to both the
 * request context (for logging) and the response headers.
 */
class SetRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Use client-provided ID or generate a new one
        $requestId = $request->header('X-Request-ID');

        if ($requestId === null || trim($requestId) === '') {
            $requestId = (string) Str::uuid();
        }

        // Store in request for later access
        $request->attributes->set('request_id', $requestId);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
