<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWidgetDomain
{
    /**
     * Handle an incoming request.
     *
     * Validates the widget request by checking the Origin/Referer header
     * against the projects table. Results are cached for 1 hour.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $this->resolveProjectFromOrigin($request);

        if ($project === null) {
            $origin = $this->extractOrigin($request);

            Log::warning('Widget request rejected: domain not found.', [
                'origin' => $origin,
                'ip' => $request->ip(),
                'route' => $request->route()?->getName(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid or unregistered domain. Please add this domain to your project settings.',
            ], 400);
        }

        if (! $project->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'This widget is currently disabled.',
            ], 403);
        }

        $request->attributes->set('project', $project);
        $request->attributes->set('widget_origin', $this->extractOrigin($request));

        return $next($request);
    }

    /**
     * Resolve project from the request's Origin/Referer header.
     * Uses caching to avoid repeated database queries.
     * Caches only the project ID to avoid serialization issues.
     */
    protected function resolveProjectFromOrigin(Request $request): ?Project
    {
        $origin = $this->extractOrigin($request);

        if (blank($origin)) {
            return null;
        }

        // Cache the domain-to-project mapping for 1 hour
        $cacheKey = "widget:domain:{$origin}";

        // Get cached project ID
        $projectId = Cache::get($cacheKey);

        // If not cached, find project and cache the ID
        if ($projectId === null) {
            $domain = $this->normalizeDomain($origin);

            $project = Project::withoutGlobalScopes()
                ->where('domain', $domain)
                ->where('is_active', true)
                ->first();

            if ($project === null) {
                // Cache negative result for 5 minutes to avoid repeated DB queries
                Cache::put($cacheKey, 'not_found', now()->addMinutes(5));

                return null;
            }

            // Cache the project ID
            Cache::put($cacheKey, $project->id, now()->addHour());
            $projectId = $project->id;
        }

        // Handle 'not_found' cache value
        if ($projectId === 'not_found') {
            return null;
        }

        // Load and return the project
        return Project::withoutGlobalScopes()->find($projectId);
    }

    /**
     * Extract the origin domain from the request.
     * Tries Origin header first, then Referer header, then query params.
     */
    protected function extractOrigin(Request $request): ?string
    {
        // 1. Try Origin header (standard for CORS requests)
        $origin = $request->header('Origin');
        if (filled($origin)) {
            return $origin;
        }

        // 2. Try Referer header (fallback for non-CORS requests)
        $referer = $request->header('Referer');
        if (filled($referer)) {
            $parsed = parse_url($referer);
            if (isset($parsed['host'])) {
                $scheme = $parsed['scheme'] ?? 'https';

                return $scheme.'://'.$parsed['host'];
            }
        }

        // 3. Try query parameter (for script tag embedding)
        $domain = $request->query('domain');
        if (filled($domain)) {
            return 'https://'.$domain;
        }

        return null;
    }

    /**
     * Normalize domain to match what's stored in the database.
     * Extracts just the domain part from a full URL.
     */
    protected function normalizeDomain(string $origin): string
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        $domain = preg_replace('#^https?://#', '', $origin);
        $domain = preg_replace('#:\d+$#', '', (string) $domain);
        $domain = rtrim((string) $domain, '/');

        return strtolower($domain);
    }

    /**
     * Clear the cache for a specific domain.
     * Call this when a project's domain is updated.
     */
    public static function clearCache(string $domain): void
    {
        Cache::forget("widget:domain:https://{$domain}");
        Cache::forget("widget:domain:http://{$domain}");
        Cache::forget("widget:domain:{$domain}");
    }
}
