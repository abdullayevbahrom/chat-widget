<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to all HTTP responses.
 *
 * These headers provide defense-in-depth protection against
 * common web attacks including clickjacking, MIME-type sniffing,
 * and man-in-the-middle attacks.
 */
class AddSecurityHeaders
{
    /**
     * Paths that are allowed to be embedded in iframes.
     *
     * The widget embed endpoint uses CSP frame-ancestors for
     * granular iframe control, so X-Frame-Options must not
     * block it.
     */
    protected const IFRAME_ALLOWED_PATHS = [
        'api/widget/embed',
        'api/widget/script',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME-type sniffing — forces browsers to use the declared
        // Content-Type, preventing XSS via disguised file uploads
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection — deny embedding in all frames.
        // EXCEPTION: Widget embed/script endpoints need to be embedded in iframes
        // on third-party sites. They use CSP frame-ancestors instead, which
        // provides more granular, origin-specific control.
        if (! $this->isIframeAllowedPath($request)) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        // XSS Protection header — legacy but still useful for older browsers.
        // Modern browsers rely on CSP instead, but this provides
        // defense-in-depth for older user agents.
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy — control how much referrer information is sent
        // with cross-origin requests. 'strict-origin-when-cross-origin'
        // sends full URL for same-origin, only origin for cross-origin.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS — enforce HTTPS for future requests.
        // Only set when the current request is over HTTPS to avoid
        // breaking local development environments.
        if ($request->isSecure()) {
            $maxAge = (int) env('HSTS_MAX_AGE', 31536000); // 1 year default
            $includeSubDomains = env('HSTS_INCLUDE_SUB_DOMAINS', true) ? '; includeSubDomains' : '';
            $preload = env('HSTS_PRELOAD', false) ? '; preload' : '';

            $response->headers->set(
                'Strict-Transport-Security',
                "max-age={$maxAge}{$includeSubDomains}{$preload}"
            );
        }

        // Remove server header to avoid information disclosure
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * Check if the request path is allowed to be embedded in iframes.
     */
    protected function isIframeAllowedPath(Request $request): bool
    {
        $path = $request->path();

        foreach (self::IFRAME_ALLOWED_PATHS as $allowedPath) {
            if (str_starts_with($path, $allowedPath)) {
                return true;
            }
        }

        return false;
    }
}
