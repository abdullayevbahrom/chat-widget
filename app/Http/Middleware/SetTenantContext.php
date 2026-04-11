<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isTenantUser()) {
            // Set the current tenant context for this request
            $tenant = $user->tenant;

            if ($tenant !== null) {
                // Check if tenant is active
                if (! $tenant->isActive()) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'Tenant account is not active.',
                    ], Response::HTTP_FORBIDDEN);
                }

                Tenant::setCurrent($tenant);
                $request->attributes->set('tenant', $tenant);
            }
        }

        return $next($request);
    }
}
