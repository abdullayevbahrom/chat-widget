<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate that the authenticated Sanctum token's tenant scope
 * matches the current request's tenant context.
 *
 * This prevents a user from using a token created for one tenant
 * to access resources of another tenant.
 */
class ValidateSanctumTenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only applies to authenticated users with Sanctum tokens
        if ($user === null) {
            return $next($request);
        }

        // Super admins bypass tenant scope validation
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Get the current tenant context
        $currentTenant = $user->tenant;

        if ($currentTenant === null) {
            // No tenant context - let other middleware handle this
            return $next($request);
        }

        // Get the Sanctum access token used for this request
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            // Check if token has tenant metadata (from the tenant_id column added by migration)
            $tokenTenantId = $accessToken->tenant_id ?? null;

            // Fallback: check abilities array for tenant_id (backward compatibility)
            if ($tokenTenantId === null && is_array($accessToken->abilities)) {
                foreach ($accessToken->abilities as $ability) {
                    if (str_starts_with($ability, 'tenant:')) {
                        $tokenTenantId = (int) substr($ability, 7);
                        break;
                    }
                }
            }

            if ($tokenTenantId !== null && (int) $tokenTenantId !== $currentTenant->id) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Token tenant scope does not match the current request tenant.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Also validate that the user's tenant matches the current tenant
        if ($user->tenant_id !== null && $user->tenant_id !== $currentTenant->id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'User tenant does not match the current request tenant.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
