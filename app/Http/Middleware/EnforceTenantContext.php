<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce that a tenant context exists for the current request.
 *
 * This middleware should be placed AFTER tenant resolution middleware
 * (SetTenantContext or ResolveTenantFromDomain). If no tenant context
 * is set and the user is not a super admin, it returns 403 Forbidden.
 *
 * Super admins bypass this check (they can access all tenants).
 */
class EnforceTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Super admins bypass tenant context enforcement
        if (Auth::check()) {
            $user = Auth::user();

            if ($user !== null && $user->isSuperAdmin()) {
                return $next($request);
            }
        }

        $tenant = Tenant::current();

        if ($tenant === null) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Tenant context is required for this request.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Optionally check if tenant is active
        if (! $tenant->isActive()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Tenant account is not active.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
