<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict access to health endpoints by IP whitelist.
 *
 * Only allows requests from IPs listed in HEALTH_ALLOWED_IPS env variable.
 * This prevents unauthorized access to system health information.
 */
class RestrictHealthEndpoint
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = $this->getAllowedIps();

        // If no IPs are configured, allow access (development mode)
        if ($allowedIps === []) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if ($clientIp === null || ! in_array($clientIp, $allowedIps, true)) {
            Log::warning('Health endpoint access denied: IP not in whitelist.', [
                'ip' => $clientIp,
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Access denied.',
                'code' => 'IP_NOT_ALLOWED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Get the list of allowed IPs from environment.
     *
     * @return array<string>
     */
    protected function getAllowedIps(): array
    {
        $ips = (string) env('HEALTH_ALLOWED_IPS', '');

        if ($ips === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $ips))
        ));
    }
}
