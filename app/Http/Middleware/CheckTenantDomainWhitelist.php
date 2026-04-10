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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            // No tenant context — allow the request to proceed
            // (this middleware only applies when tenant context is expected)
            return $next($request);
        }

        $host = $request->getHost();

        // Check if the host is in the tenant's active domain whitelist
        $isWhitelisted = $tenant->hasDomain($host);

        if (! $isWhitelisted) {
            // Also check subdomain as fallback
            $parts = explode('.', $host, 2);
            if (count($parts) === 2 && $tenant->subdomain === $parts[0]) {
                return $next($request);
            }

            abort(Response::HTTP_FORBIDDEN, 'Domain not whitelisted for this tenant.');
        }

        return $next($request);
    }
}
