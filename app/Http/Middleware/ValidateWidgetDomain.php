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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
     */
    protected function resolveProjectFromOrigin(Request $request): ?Project
    {
        $origin = $this->extractOrigin($request);

        if (blank($origin)) {
            return null;
        }

        // Cache the domain-to-project mapping for 1 hour
        $cacheKey = "widget:domain:{$origin}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($origin) {
            $domain = $this->normalizeDomain($origin);

            return Project::query()
                ->where('domain', $domain)
                ->where('is_active', true)
                ->first();
        });
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
                return $scheme . '://' . $parsed['host'];
            }
        }

        // 3. Try query parameter (for script tag embedding)
        $domain = $request->query('domain');
        if (filled($domain)) {
            return 'https://' . $domain;
        }

        return null;
    }

    /**
     * Normalize domain to match what's stored in the database.
     * Extracts just the domain part from a full URL.
     */
    protected function normalizeDomain(string $origin): string
    {
        // Remove scheme
        $domain = preg_replace('#^https?://#', '', $origin);

        // Remove port
        $domain = preg_replace('/:\d+$/', '', $domain);

        // Remove trailing slash
        $domain = rtrim($domain, '/');

        // Convert to lowercase
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
    }
}
