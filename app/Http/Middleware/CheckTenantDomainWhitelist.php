<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantDomainWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            // No tenant context -- allow the request to proceed
            // (this middleware only applies when tenant context is expected)
            return $next($request);
        }

        // Use getHttpHost() instead of getHost() to include the port number.
        // This prevents host header injection attacks where an attacker could
        // send a request without a port and bypass whitelist checks.
        $host = $request->getHttpHost();

        // Check if the host is in the tenant's active domain whitelist
        $isWhitelisted = $this->isHostWhitelisted($tenant, $host);

        if (! $isWhitelisted) {
            // Also check subdomain as fallback
            $parts = explode('.', $request->getHost(), 2);

            if (count($parts) === 2 && $tenant->subdomain === $parts[0]) {
                return $next($request);
            }

            abort(Response::HTTP_FORBIDDEN, 'Domain not whitelisted for this tenant.');
        }

        return $next($request);
    }

    /**
     * Check if the given host (including port) is in the tenant's whitelist.
     *
     * Compares both with and without port to handle cases where the tenant
     * domain is stored without a port but the request includes one.
     */
    protected function isHostWhitelisted(Tenant $tenant, string $fullHost): bool
    {
        // Direct match with port
        if ($tenant->hasDomain($fullHost)) {
            return true;
        }

        // Fallback: check without port (host only)
        $hostOnly = $this->extractHostWithoutPort($fullHost);

        if ($hostOnly !== $fullHost && $tenant->hasDomain($hostOnly)) {
            return true;
        }

        return false;
    }

    /**
     * Extract the host portion without the port number.
     */
    protected function extractHostWithoutPort(string $fullHost): string
    {
        // Remove port if present (e.g., "example.com:8080" -> "example.com")
        $colonPos = strrpos($fullHost, ':');

        if ($colonPos !== false) {
            $potentialPort = substr($fullHost, $colonPos + 1);

            if (ctype_digit($potentialPort)) {
                return substr($fullHost, 0, $colonPos);
            }
        }

        return $fullHost;
    }
}
