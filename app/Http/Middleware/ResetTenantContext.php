<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetTenantContext
{
    /**
     * Reset static tenant context on each request.
     *
     * In long-running server environments (Swoole, RoadRunner), the static
     * $currentTenant persists across requests. This middleware ensures it
     * is cleared at the start of each request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Tenant::clearCurrent();

        return $next($request);
    }
}
