<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromDomain
{
    public function __construct(
        protected TenantResolver $resolver
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use getHttpHost() instead of getHost() to include the port number.
        // This prevents host header injection attacks where an attacker sends
        // a manipulated Host header to resolve an unintended tenant.
        $host = $request->getHttpHost();

        // Validate the host header to prevent injection attacks.
        // A valid host must not contain null bytes, must have a valid format,
        // and the port (if present) must be a valid number.
        if (! $this->isValidHost($host)) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => 'Invalid Host header.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $tenant = $this->resolver->resolve($host);

        if ($tenant !== null) {
            Tenant::setCurrent($tenant);
            $request->attributes->set('tenant', $tenant);
        }

        return $next($request);
    }

    /**
     * Validate that the host header is well-formed and not malicious.
     */
    protected function isValidHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost') {
            return true;
        }

        // Reject null bytes (injection attempt)
        if (str_contains($host, "\0")) {
            return false;
        }

        // Extract host and port for validation
        $parsedHost = parse_url("http://{$host}", PHP_URL_HOST);
        $parsedPort = parse_url("http://{$host}", PHP_URL_PORT);

        if ($parsedHost === null || $parsedHost === false) {
            return false;
        }

        // Host must only contain valid characters: letters, digits, dots, hyphens
        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $parsedHost)) {
            return false;
        }

        // Port (if present) must be a valid number between 1 and 65535
        if ($parsedPort !== null && $parsedPort !== false) {
            if (! is_int($parsedPort) || $parsedPort < 1 || $parsedPort > 65535) {
                return false;
            }
        }

        return true;
    }
}
